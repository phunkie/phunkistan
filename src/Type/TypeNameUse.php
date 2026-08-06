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
 * A name written where a type belongs, whose meaning is not settled yet.
 *
 * Deliberately not called a reference. Whether `A` names a type parameter bound
 * by the method around it, a class in this file, or nothing at all cannot be
 * known from the grammar: the same text means different things depending on
 * what is in scope where it was written. This says only that a name was
 * written here, and leaves connecting it to a declaration to the phase that can
 * see the scopes.
 */
final class TypeNameUse implements Type
{
    /**
     * @param string $name   Name exactly as it was written
     * @param Region $region Where it was written
     */
    public function __construct(
        public readonly string $name,
        public readonly Region $region,
    ) {
    }

    /**
     * @return Region Where the name itself was written, which is what a
     *                diagnostic about it should underline
     */
    public function region(): Region
    {
        return $this->region;
    }

    /**
     * @return string The name, as the reader wrote it
     */
    public function __toString(): string
    {
        return $this->name;
    }
}
