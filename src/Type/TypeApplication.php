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
 * A type constructor with its arguments supplied.
 *
 * The constructor is a node rather than a name, which is the whole reason this
 * is separate from the name it is usually written with. In `F<B>` inside a
 * class that declared `<F<_>>`, `F` is a parameter bound by the class and will
 * never be a name any symbol table holds, so a shape that could only hold a
 * name could not express it at all.
 */
final class TypeApplication implements Type
{
    /**
     * @param Type       $constructor What is being applied
     * @param list<Type> $arguments   What it is applied to, in order
     * @param Region     $region      Where the whole application was written
     */
    public function __construct(
        public readonly Type $constructor,
        public readonly array $arguments,
        public readonly Region $region,
    ) {
    }

    /**
     * @return Region Where the whole application was written, constructor and
     *                arguments together
     */
    public function region(): Region
    {
        return $this->region;
    }

    /**
     * @return string The application, as the reader would write it
     */
    public function __toString(): string
    {
        return sprintf('%s<%s>', $this->constructor, implode(', ', array_map('strval', $this->arguments)));
    }
}
