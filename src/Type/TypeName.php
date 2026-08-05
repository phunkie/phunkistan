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

/**
 * A named type, and whatever it was given to hold.
 *
 * `Int` is a name with nothing in it. `ImmList<Int>` is the same shape with one
 * argument, and `ImmMap<String, Int>` with two, so nothing here knows how many
 * a particular name is supposed to take. That is a question about the type
 * being named, which is a whole-program question, and the answer lives in a
 * later milestone.
 */
final class TypeName implements Type
{
    /**
     * @param string     $name      Name as it was written
     * @param list<Type> $arguments Type arguments, empty where none were given
     */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments = [],
    ) {
    }

    /**
     * @return string The name, with its arguments in angle brackets
     */
    public function __toString(): string
    {
        if ($this->arguments === []) {
            return $this->name;
        }

        return sprintf('%s<%s>', $this->name, implode(', ', array_map('strval', $this->arguments)));
    }
}
