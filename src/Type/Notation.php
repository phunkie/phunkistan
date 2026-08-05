<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phunkie\Stan\Type;

use PhpToken;

/**
 * Finds phunkie's type notation in a source, and takes it back out.
 *
 * What is left behind is PHP, which is the point: PHP's own parser can then
 * read the file, and everything the notation said has been read by a grammar
 * that knows what it means rather than passed through by one that does not.
 *
 * The notation is replaced by spaces rather than removed. Every byte after it
 * therefore keeps the offset it had, so a position from PHP's parser is already
 * a position in the file the reader wrote, with nothing in between to get
 * wrong. It is the cheapest possible source map: none.
 */
final class Notation
{
    public function __construct(
        private readonly TypeParser $parser = new TypeParser(),
    ) {
    }

    /**
     * Reads every type expression in a source.
     *
     * @param string $source Source, already opened with a PHP tag
     *
     * @return ReadNotation What was found, what could not be read, and the PHP left over
     */
    public function readFrom(string $source): ReadNotation
    {
        $tokens = PhpToken::tokenize($source);
        $types = [];
        $errors = [];
        $blanked = $source;

        foreach ($this->notationIn($tokens) as [$keep, $from, $at, $certain]) {
            $cursor = new Cursor(substr($source, $at), $at);

            try {
                $types[] = $this->parser->type($cursor);
            } catch (TypeSyntaxError $error) {
                // A name followed by `<` is only notation if it reads as a
                // type. `MAX < 3` is a comparison, and backing off leaves it to
                // PHP, which is the one that should have an opinion about it.
                // A callable was recognised by where it sits, so a failure to
                // read it is a mistake in the notation. A bracket group was
                // recognised by one character, so it has to be asked again.
                if ($certain || $this->looksLikeNotation($source, $at)) {
                    $errors[] = $error;
                }

                continue;
            }

            $blanked = $this->blank($blanked, $at + $keep, $cursor->offset());

            // A callable type leaves nothing behind for PHP to enforce, so the
            // colon in front of it goes too: `function f():  {` is not PHP,
            // where `function f()    {` is.
            if ($from < $at) {
                $blanked = $this->blank($blanked, $from, $at);
            }
        }

        return new ReadNotation($types, $errors, $blanked);
    }

    /**
     * Every place the notation appears, as how much of it PHP should keep, what
     * to blank from, and where the type itself begins.
     *
     * A named type keeps its name, so `ImmList<Int>` leaves `ImmList` for PHP
     * to enforce and only the arguments go. A callable type leaves nothing, so
     * it goes whole, and its colon with it.
     *
     * @param list<PhpToken> $tokens
     *
     * @return list<array{int, int, int, bool}>
     */
    private function notationIn(array $tokens): array
    {
        $found = [];
        $count = count($tokens);

        for ($at = 0; $at < $count; $at++) {
            $token = $tokens[$at];
            $next = $this->skipSpace($tokens, $at + 1);

            // The bracket is only tested for at its start. PHP's lexer runs it
            // together with whatever follows, so an empty group arrives as
            // `<>`, which is the not-equal operator, and never as a bracket at
            // all.
            if ($token->is([T_STRING, T_ARRAY]) && $next < $count && str_starts_with($tokens[$next]->text, '<')) {
                $found[] = [strlen($token->text), $token->pos, $token->pos, false];

                continue;
            }

            if ($this->opensACallableType($tokens, $at)) {
                $before = $this->previous($tokens, $at);
                $from = $before !== null && $tokens[$before]->text === ':' ? $tokens[$before]->pos : $token->pos;

                $found[] = [0, $from, $token->pos, true];
            }
        }

        return $found;
    }

    /**
     * Whether a parenthesis begins a callable type rather than a call, an
     * arrow function or a match arm.
     *
     * All three of those have something in front of the bracket: a name, a
     * variable, or `fn`. A type has a place where a type belongs, which is
     * after `(`, `,` or `:`, and nothing else.
     *
     * @param list<PhpToken> $tokens
     */
    private function opensACallableType(array $tokens, int $at): bool
    {
        $close = $this->closeOfGroup($tokens, $at);

        if ($close === null) {
            return false;
        }

        $before = $this->previous($tokens, $at);

        if ($before === null || !in_array($tokens[$before]->text, ['(', ',', ':'], true)) {
            return false;
        }

        $after = $this->skipSpace($tokens, $close + 1);

        return $after < count($tokens) && $tokens[$after]->is(T_DOUBLE_ARROW);
    }

    /**
     * Where a parenthesised group starting here closes.
     *
     * `(string)` never reaches this as a bracket at all: PHP's lexer reads it
     * as one cast token, because in PHP that is what it is. A parameter list
     * naming PHP's own scalar types therefore looks nothing like a parameter
     * list until the cast is recognised as the group it is written as.
     *
     * @param list<PhpToken> $tokens
     */
    private function closeOfGroup(array $tokens, int $at): ?int
    {
        if ($tokens[$at]->is([T_ARRAY_CAST, T_BOOL_CAST, T_DOUBLE_CAST, T_INT_CAST, T_OBJECT_CAST, T_STRING_CAST, T_UNSET_CAST])) {
            return $at;
        }

        return $tokens[$at]->text === '(' ? $this->matching($tokens, $at) : null;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function matching(array $tokens, int $open): ?int
    {
        $depth = 0;

        for ($at = $open; $at < count($tokens); $at++) {
            $depth += match ($tokens[$at]->text) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };

            if ($depth === 0) {
                return $at;
            }
        }

        return null;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function previous(array $tokens, int $at): ?int
    {
        $at--;

        while ($at >= 0 && $tokens[$at]->is(T_WHITESPACE)) {
            $at--;
        }

        return $at < 0 ? null : $at;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function skipSpace(array $tokens, int $at): int
    {
        while ($at < count($tokens) && $tokens[$at]->is(T_WHITESPACE)) {
            $at++;
        }

        return $at;
    }

    /**
     * Whether what was written was meant to be a type at all.
     *
     * What follows the bracket decides it. A type argument begins with a name,
     * a parenthesis or a hole, and an empty group begins by closing. A
     * comparison has a value there instead, so `MAX < 3` is arithmetic and
     * `ImmList<Int $xs` is notation with a bracket missing.
     *
     * Getting this wrong in one direction reports arithmetic as a type error,
     * and in the other lets broken notation reach PHP, which then says
     * something true about tokens and useless about types.
     */
    private function looksLikeNotation(string $source, int $at): bool
    {
        $bracket = strpos($source, '<', $at);

        if ($bracket === false) {
            return false;
        }

        return preg_match('/^<\s*([A-Za-z_\\\\(>]|$)/', substr($source, $bracket, 40)) === 1;
    }

    /**
     * Replaces a stretch of source with spaces of the same length.
     */
    private function blank(string $source, int $from, int $to): string
    {
        return substr_replace($source, str_repeat(' ', $to - $from), $from, $to - $from);
    }
}
