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
 * A function's shape: what it takes, and what it answers with.
 *
 * The parentheses are always written, even around nothing, because they are
 * what says where the parameters stop and the answer begins.
 */
final class CallableType implements Type
{
    /**
     * @param list<Type> $parameters What it takes, in order
     * @param Type       $returns    What it answers with
     * @param Region     $region     Where the whole shape was written
     */
    public function __construct(
        public readonly array $parameters,
        public readonly Type $returns,
        public readonly Region $region,
    ) {
    }

    /**
     * @return Region Where the whole shape was written
     */
    public function region(): Region
    {
        return $this->region;
    }

    /**
     * The parentheses around the parameter list are the ones that keep two
     * arrows apart: a callable parameter is already inside them, and a callable
     * return has nothing after it to be confused with. Neither needs a pair of
     * its own, and adding one would say something the reader did not write.
     *
     * @return string The shape, as the reader would write it
     */
    public function __toString(): string
    {
        return sprintf(
            '(%s) => %s',
            implode(', ', array_map('strval', $this->parameters)),
            $this->returns
        );
    }
}
