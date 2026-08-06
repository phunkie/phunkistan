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
 * A type parameter being introduced.
 *
 * A binder, not a use. `class Stack<Itn>` declares a parameter unfortunately
 * spelled `Itn` and is entirely legal, because nothing here is being looked up:
 * the name is being brought into existence. Scope checking must never ask what
 * one of these refers to.
 *
 * Its own brackets state its arity rather than its arguments. `F<_>` says F
 * takes one argument of its own, which is what lets `Functor<F<_>>` mean
 * something for every F that has a map.
 */
final class TypeParameterDeclaration
{
    /**
     * @param string $name   Name being introduced
     * @param int    $arity  How many arguments this parameter takes itself
     * @param Region $region Where the name was written
     */
    public function __construct(
        public readonly string $name,
        public readonly int $arity,
        public readonly Region $region,
    ) {
    }

    /**
     * @return string The parameter, with its arity where it has one
     */
    public function __toString(): string
    {
        return $this->arity === 0 ? $this->name : sprintf('%s<%s>', $this->name, implode(', ', array_fill(0, $this->arity, '_')));
    }
}
