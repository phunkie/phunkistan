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
 * Something written where a type belongs.
 *
 * Implementations are what phunkie's notation can say: a name with arguments,
 * a function's shape, and a hole where a type is still waiting for one of its
 * own.
 */
interface Type
{
    /**
     * The type as the reader would write it.
     *
     * Diagnostics print this rather than a class name, because the reader has
     * to recognise what they typed in what they are told.
     *
     * @return string The surface syntax of this type
     */
    public function __toString(): string;

    /**
     * Where this was written.
     *
     * @return Region The bytes it occupies in the source it was read from
     */
    public function region(): Region;
}
