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

/**
 * What reading a source's notation found.
 */
final class ReadNotation
{
    /**
     * @param list<Type>              $types         Every use of a type that was understood
     * @param list<DeclarationHeader> $headers       Every declaration head, with what it binds
     * @param list<TypeSyntaxError>   $errors        Notation that could not be read, with offsets
     * @param string                  $php           The source with its notation blanked out
     * @param list<Region>            $erasures      What the compiler should remove, in source order
     * @param list<Region>            $substitutions Where the compiler should adopt the stand-in's
     *                                               text as its own, the same length by construction
     */
    public function __construct(
        public readonly array $types,
        public readonly array $headers,
        public readonly array $errors,
        public readonly string $php,
        public readonly array $erasures = [],
        public readonly array $substitutions = [],
    ) {
    }

    /**
     * Every type parameter any declaration in the file introduced.
     *
     * Flattened deliberately for scope, where the question is only whether a
     * name was bound anywhere in this file. Arity asks a different question and
     * reads the headers, where a parameter is still attached to what bound it.
     *
     * @return list<TypeParameterDeclaration>
     */
    public function declarations(): array
    {
        $parameters = [];

        foreach ($this->headers as $header) {
            $parameters = array_merge($parameters, $header->parameters);
        }

        return $parameters;
    }

    /**
     * @return bool Whether anything could not be read
     */
    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
