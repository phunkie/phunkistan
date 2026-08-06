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

use Phunkie\Stan\Source\Region;

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
        $headers = [];
        $errors = [];
        $blanked = $source;

        $read = 0;

        foreach ($this->notationIn($tokens) as [$keep, $from, $at, $isHeader]) {
            // A nested type is already whole in the type that contains it, so
            // its inner names are candidates that have been read once. Reading
            // them again says the same thing twice, and when the notation is
            // broken it says the same mistake twice.
            if ($at < $read) {
                continue;
            }

            $cursor = new Cursor(substr($source, $at), $at);

            try {
                // A declaration header is not a type. `class Stack<T>` declares
                // a type constructor and binds a parameter, and reading it as a
                // type would both invent one nobody wrote and put `T` where
                // something is later going to try to look it up.
                if ($isHeader) {
                    $cursor->take($keep);
                    $headers[] = new DeclarationHeader(
                        substr($source, $at, $keep),
                        $this->parser->parameters($cursor),
                        new Region($at, $cursor->offset())
                    );
                } else {
                    $types[] = $this->parser->type($cursor);
                }
            } catch (TypeSyntaxError $error) {
                $read = $error->offset;
                $error = new TypeSyntaxError($error->getMessage(), $error->offset, $at);

                // Reported as a suspicion, not a verdict. Whether this was
                // broken notation or a comparison that was never notation is
                // settled by the checker, which can ask PHP where it gave up on
                // the same source and see whether the two agree.
                $errors[] = $error;

                continue;
            }

            // What follows a completed type is what settles whether it was one.
            // A shift's bracket closes a type that was never opened, and an
            // array key or a match arm reads as a callable that nothing could
            // ever be declared as. Both are arithmetic wearing the notation's
            // shape, and both are only visible from the far end of it.
            if (!$isHeader && !$this->isFollowedProperly($source, $cursor->offset(), end($types))) {
                array_pop($types);

                continue;
            }

            $read = $cursor->offset();
            $blanked = $this->blank($blanked, $at + $keep, $cursor->offset());

            // A callable type leaves nothing behind for PHP to enforce, so the
            // colon in front of it goes too: `function f():  {` is not PHP,
            // where `function f()    {` is.
            if ($from < $at) {
                $blanked = $this->blank($blanked, $from, $at);
            }
        }

        return new ReadNotation($types, $headers, $errors, $blanked);
    }

    /**
     * Every place the notation appears, as how much of it PHP should keep, what
     * to blank from, and where the type itself begins.
     *
     * A named type PHP could enforce keeps its name, so `ImmList<Int>` leaves
     * `ImmList` behind and only the arguments go. Anything PHP could not accept
     * as a type name goes whole, and so does a callable type, and a return
     * type's colon goes with it.
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
            if ($this->readsAsAName($token) && $next < $count && str_starts_with($tokens[$next]->text, '<')) {
                // Grammar position says unambiguously which this is, which is
                // why the parser can mark binders as declarations and never has
                // to guess.
                if ($this->isHeader($tokens, $at)) {
                    $found[] = [strlen($token->text), $token->pos, $token->pos, true];

                    continue;
                }

                $keep = $this->keepableLength($token);

                $found[] = [$keep, $keep === 0 ? $this->blankFrom($tokens, $at) : $token->pos, $token->pos, false];

                continue;
            }

            if ($this->opensACallableType($tokens, $at)) {
                $found[] = [0, $this->blankFrom($tokens, $at), $token->pos, false];
            }
        }

        return $found;
    }

    /**
     * Whether a name is the one being declared rather than one being used.
     *
     * What sits in front settles it and nothing else can: after `class` the
     * brackets introduce parameters, and everywhere else they supply arguments.
     *
     * @param list<PhpToken> $tokens
     */
    private function isHeader(array $tokens, int $at): bool
    {
        $before = $this->previous($tokens, $at);

        return $before !== null
            && $tokens[$before]->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM, T_FUNCTION]);
    }

    /**
     * Whether a token reads as a name in this grammar.
     *
     * Asked of the text rather than of the token's kind, because PHP has around
     * forty words it keeps for itself and `Function<A, B>` and `Try<A>` are not
     * unlikely names in a functional language. Enumerating the ones it would be
     * a shame to lose is a list that is never finished; asking the grammar what
     * a name is has no list in it at all.
     */
    private function readsAsAName(PhpToken $token): bool
    {
        return preg_match('/^[A-Za-z_\x80-\xff\\\\][A-Za-z0-9_\x80-\xff\\\\]*$/', $token->text) === 1;
    }

    /**
     * How much of a named type is worth leaving for PHP.
     *
     * A name PHP would accept as a class stays, so `ImmList<Int>` still tells
     * PHP there is an `ImmList` and lets it say when there is not. A name PHP
     * keeps for itself goes with its arguments, because `Function` alone is not
     * a type PHP will read however few brackets follow it.
     *
     * The token's kind decides this, which is safe where deciding detection by
     * it was not: being wrong here means blanking more than necessary, and
     * being wrong there meant missing notation entirely.
     */
    private function keepableLength(PhpToken $token): int
    {
        $names = [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];

        return $token->is($names) ? strlen($token->text) : 0;
    }

    /**
     * Where blanking should start for a type that leaves nothing behind.
     *
     * A return type's colon has to go with it. `function f():  {` is not PHP,
     * where `function f()    {` is, and PHP has nothing left to enforce either
     * way once the name it would have enforced is gone.
     *
     * @param list<PhpToken> $tokens
     */
    private function blankFrom(array $tokens, int $at): int
    {
        $before = $this->previous($tokens, $at);

        if ($before !== null && $tokens[$before]->text === ':') {
            return $tokens[$before]->pos;
        }

        return $tokens[$at]->pos;
    }

    /**
     * Whether a parenthesis might begin a callable type.
     *
     * Only might: what is in front of a bracket cannot tell a type from a call,
     * an arrow function or a match arm, and guessing from it both missed a
     * callable property and reported a parenthesised array key. What settles it
     * is what comes after the whole type, which is not known until it has been
     * read, so the question is asked again once it has.
     *
     * @param list<PhpToken> $tokens
     */
    private function opensACallableType(array $tokens, int $at): bool
    {
        $close = $this->closeOfGroup($tokens, $at);

        if ($close === null) {
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
     * Whether what comes after a type is something a type may be followed by.
     *
     * A callable type is only ever declared somewhere a declaration can go, so
     * a variable, a reference, a variadic, a body or a semicolon follows it.
     * `[1, (FOO) => BAR]` and a match arm read as callables and are followed by
     * a comma, which no declaration ever is.
     *
     * A named type is asked only whether another `>` follows, which would mean
     * the bracket it just consumed belonged to a shift: `MIN < MAX >> 2`.
     */
    private function isFollowedProperly(string $source, int $at, Type|false $type): bool
    {
        $rest = substr($source, $at, 8);

        if ($type instanceof CallableType) {
            return preg_match('/^\s*[$&.{;]/', $rest) === 1;
        }

        return preg_match('/^\s*>/', $rest) !== 1;
    }

    /**
     * Replaces a stretch of source with spaces of the same length.
     */
    private function blank(string $source, int $from, int $to): string
    {
        return substr_replace($source, str_repeat(' ', $to - $from), $from, $to - $from);
    }
}
