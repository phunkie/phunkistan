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

namespace Phunkie\Stan\Source;

/**
 * A place in a source, counted the way the reader counts.
 *
 * Lines and columns both start at one, and a column counts characters rather
 * than bytes. Every other convention in the world is somebody else's protocol,
 * and is converted to at the edge that speaks it.
 */
final class Position
{
    /**
     * @param int $line   Line number, counting from one
     * @param int $column Column in characters, counting from one
     */
    public function __construct(
        public readonly int $line,
        public readonly int $column,
    ) {
    }
}
