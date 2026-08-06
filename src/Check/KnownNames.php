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

/**
 * The names that mean something without anybody having to say so.
 *
 * phunkie's own types are listed rather than read from the installed package,
 * so that checking a source does not require phunkie to be installed beside it.
 * The cost is that this list follows phunkie's releases by hand, and the type
 * names are the part of phunkie that moves least.
 */
final class KnownNames
{
    private const PHUNKIE = [
        'Int', 'String', 'Float', 'Double', 'Boolean', 'Bool', 'Unit', 'Nothing', 'Mixed',
        'ImmList', 'ImmMap', 'ImmSet', 'NonEmptyList', 'Option', 'Some', 'None',
        'Either', 'Left', 'Right', 'Validation', 'Success', 'Failure',
        'Pair', 'Tuple', 'Function1', 'IO', 'Kind', 'Show', 'Ord', 'Eq',
    ];

    private const PHP = [
        'int', 'string', 'float', 'bool', 'array', 'callable', 'iterable', 'object',
        'mixed', 'void', 'null', 'never', 'false', 'true', 'self', 'static', 'parent',
    ];

    /**
     * @param string $name Name as it was written
     *
     * @return bool Whether it means something everywhere
     */
    public function knows(string $name): bool
    {
        return in_array($name, self::PHUNKIE, true) || in_array($name, self::PHP, true);
    }

    /**
     * How many arguments a known type takes, or null where it is not known.
     *
     * @param string $name Name as it was written
     *
     * @return int|null The arity, or null if this says nothing about it
     */
    public function arityOf(string $name): ?int
    {
        return match ($name) {
            'ImmList', 'ImmSet', 'NonEmptyList', 'Option', 'Some', 'IO' => 1,
            'ImmMap', 'Either', 'Validation', 'Pair', 'Function1' => 2,
            default => null,
        };
    }
}
