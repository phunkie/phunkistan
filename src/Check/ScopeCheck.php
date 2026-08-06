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

namespace Phunkie\Stan\Check;

use PhpToken;
use Phunkie\Stan\Diagnostic\Diagnostic;
use Phunkie\Stan\Diagnostic\Span;
use Phunkie\Stan\Source\OpeningTag;
use Phunkie\Stan\Source\Source;
use Phunkie\Stan\Type\Names;
use Phunkie\Stan\Type\Notation;
use Phunkie\Stan\Type\TypeNameUse;

/**
 * Reports a name written in a type that means nothing where it was written.
 *
 * This is scope without meaning. It can say `ImmList<Itn>` is wrong, because
 * `Itn` names nothing here, and it cannot say what `ImmList` is or how many
 * arguments it should take. Both of those live in another file.
 *
 * It only speaks about a file with no `namespace`. There an unqualified name
 * resolves in the global namespace, so a name that is not imported, not
 * declared here and not one phunkie ships is a typo that can be proved. In a
 * namespaced file it stays quiet, because the name may be declared in the next
 * file along and reading that file is what a symbol table is for. That
 * condition is temporary, and closing it deletes the condition rather than
 * this class.
 */
final class ScopeCheck implements Check
{
    public const CODE = 'E0004';
    public const CATEGORY = 'UNKNOWN TYPE';

    public function __construct(
        private readonly Notation $notation = new Notation(),
        private readonly OpeningTag $openingTag = new OpeningTag(),
        private readonly KnownNames $known = new KnownNames(),
        private readonly Names $names = new Names(),
    ) {
    }

    /**
     * Checks every name a source uses in a type.
     *
     * @param Source $source Source to read
     *
     * @return list<Diagnostic> One for each name that means nothing here
     */
    public function on(Source $source): array
    {
        $code = $source->read();
        $opened = $this->openingTag->open($code);
        $tokens = PhpToken::tokenize($opened->text());

        if ($this->isNamespaced($tokens)) {
            return [];
        }

        $read = $this->notation->readFrom($opened->text());

        if ($read->hasErrors()) {
            return [];
        }

        $inScope = $this->inScope($tokens, $read->declarations);
        $diagnostics = [];

        foreach ($this->names->usedIn($read->types) as $use) {
            if ($this->resolves($use, $inScope)) {
                continue;
            }

            $position = $opened->positionOf($use->region()->from);

            $diagnostics[] = new Diagnostic(
                self::CODE,
                self::CATEGORY,
                sprintf('Nothing here is called "%s".', $use->name),
                new Span($source->relativePath, $position->line, $position->column),
                $code
            );
        }

        return $diagnostics;
    }

    /**
     * @param array<string, true> $inScope
     */
    private function resolves(TypeNameUse $use, array $inScope): bool
    {
        // A qualified name says where it lives, and whether anything is there is
        // a question for a symbol table rather than for this file.
        if (str_contains($use->name, '\\')) {
            return true;
        }

        return isset($inScope[$use->name]) || $this->known->knows($use->name);
    }

    /**
     * Every name this file puts in scope: what it declares, what it imports, and
     * the type parameters its declarations bind.
     *
     * The parameters are one flat set rather than a scope per declaration, so a
     * name bound by one method satisfies a use in another. That over-accepts
     * and never over-reports, which is the right way round while the rule is
     * young.
     *
     * @param list<PhpToken>                 $tokens
     * @param list<\Phunkie\Stan\Type\TypeParameterDeclaration> $declarations
     *
     * @return array<string, true>
     */
    private function inScope(array $tokens, array $declarations): array
    {
        $inScope = [];

        foreach ($declarations as $declaration) {
            $inScope[$declaration->name] = true;
        }

        $count = count($tokens);

        for ($at = 0; $at < $count; $at++) {
            if ($tokens[$at]->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM])) {
                $name = $this->nameAfter($tokens, $at);

                if ($name !== null) {
                    $inScope[$name] = true;
                }

                continue;
            }

            if ($tokens[$at]->is(T_USE)) {
                foreach ($this->imported($tokens, $at) as $name) {
                    $inScope[$name] = true;
                }
            }
        }

        return $inScope;
    }

    /**
     * The names a `use` statement brings into the file, under whatever they are
     * called here: an alias is the name the reader then writes.
     *
     * @param list<PhpToken> $tokens
     *
     * @return list<string>
     */
    private function imported(array $tokens, int $at): array
    {
        $names = [];
        $count = count($tokens);
        $last = null;

        for ($at++; $at < $count && $tokens[$at]->text !== ';' && $tokens[$at]->text !== '{'; $at++) {
            if ($tokens[$at]->is(T_AS)) {
                $alias = $this->nameAfter($tokens, $at);
                $last = $alias ?? $last;

                continue;
            }

            if ($tokens[$at]->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])) {
                $last = $this->shortNameOf($tokens[$at]->text);
            }
        }

        if ($last !== null) {
            $names[] = $last;
        }

        return $names;
    }

    private function shortNameOf(string $name): string
    {
        $at = strrpos($name, '\\');

        return $at === false ? $name : substr($name, $at + 1);
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function nameAfter(array $tokens, int $at): ?string
    {
        $count = count($tokens);

        for ($at++; $at < $count; $at++) {
            if ($tokens[$at]->is(T_WHITESPACE)) {
                continue;
            }

            return $tokens[$at]->is(T_STRING) ? $tokens[$at]->text : null;
        }

        return null;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function isNamespaced(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($token->is(T_NAMESPACE)) {
                return true;
            }
        }

        return false;
    }
}
