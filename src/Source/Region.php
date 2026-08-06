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
 * A stretch of a source, in bytes.
 *
 * Deliberately not a `Span`. A span is a file, a line and a column, and belongs
 * to the vocabulary of saying what is wrong; a region belongs to the vocabulary
 * of reading. Keeping them apart is what stops the grammar depending on the
 * diagnostics, and `OpenedSource` is already the one translator between them.
 */
final class Region
{
    /**
     * @param int $from Byte offset it starts at
     * @param int $to   Byte offset just past its end
     */
    public function __construct(
        public readonly int $from,
        public readonly int $to,
    ) {
    }

    /**
     * @param int $offset Byte offset to test
     *
     * @return bool Whether the offset falls inside this region
     */
    public function covers(int $offset): bool
    {
        return $offset >= $this->from && $offset < $this->to;
    }
}
