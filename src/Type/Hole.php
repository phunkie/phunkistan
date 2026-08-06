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
 * The gap in a type that is not finished yet.
 *
 * `F<_>` says F takes one argument of its own, without saying what. That is the
 * whole of what makes a type constructor writable: `Functor<F<_>>` means
 * something for every F that has a map, and could not be said at all if F had
 * to be completed first.
 */
final class Hole implements Type
{
    /**
     * @param Region $region Where the underscore was written
     */
    public function __construct(
        public readonly Region $region,
    ) {
    }

    /**
     * @return Region Where the underscore was written
     */
    public function region(): Region
    {
        return $this->region;
    }

    /**
     * @return string The underscore, as it was written
     */
    public function __toString(): string
    {
        return '_';
    }
}
