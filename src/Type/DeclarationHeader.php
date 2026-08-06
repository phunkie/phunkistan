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
 * The head of a declaration, and the type parameters it introduces.
 *
 * Not a type. `class Stack<T>` declares a type constructor and binds a
 * parameter; `map<A, B>` names a method and binds two. Keeping the parameters
 * attached to what declared them is what lets `Stack<Int, String>` be told
 * apart from `Stack<Int>` later, which a flat list of binders cannot do.
 */
final class DeclarationHeader
{
    /**
     * @param string                         $name       Name being declared
     * @param list<TypeParameterDeclaration> $parameters What it binds, in order
     * @param Region                         $region     Where the head was written
     */
    public function __construct(
        public readonly string $name,
        public readonly array $parameters,
        public readonly Region $region,
    ) {
    }

    /**
     * @return int How many type arguments a use of this name must supply
     */
    public function arity(): int
    {
        return count($this->parameters);
    }
}
