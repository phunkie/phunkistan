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
 * A source a PHP parser can read, and the only thing that knows what that cost.
 *
 * Opening a tag over a source that had none moves the first line sideways. That
 * is a small fact, but it is the fact every position in the tool depends on, so
 * it is held in one place with the text it applies to rather than left for each
 * caller to remember and apply correctly.
 *
 * Callers hand back the offset a parser gave them and get a place a reader
 * recognises. Nothing else has to know a tag was ever inserted.
 */
final class OpenedSource
{
    /**
     * Byte offset at which each line begins, indexed from zero for line one.
     *
     * @var list<int>
     */
    private readonly array $lineStarts;

    /**
     * @param string $text         Source as a parser will read it
     * @param int    $columnOffset Bytes inserted at the start of the first line
     */
    public function __construct(
        private readonly string $text,
        private readonly int $columnOffset,
    ) {
        $this->lineStarts = $this->indexLines($text);
    }

    /**
     * The source as a parser will read it.
     *
     * @return string Text guaranteed to open a PHP tag
     */
    public function text(): string
    {
        return $this->text;
    }

    /**
     * Where a parser's offset falls, in the reader's own coordinates.
     *
     * @param int $offset Byte offset into the text a parser was given
     *
     * @return Position Line and character column, both counting from one
     */
    public function positionOf(int $offset): Position
    {
        $offset = max(0, min($offset, strlen($this->text)));
        $line = $this->lineAt($offset);
        $start = $this->lineStarts[$line - 1];
        $characters = mb_strlen(substr($this->text, $start, $offset - $start));

        // Only the first line was moved, and only sideways, so that is the
        // whole of the correction.
        $column = $characters + 1 - ($line === 1 ? $this->columnOffset : 0);

        return new Position($line, max(1, $column));
    }

    /**
     * The line an offset falls on, found by bisecting the line starts rather
     * than counting newlines, because a checker asks this once per diagnostic
     * and a file may hold a great many.
     */
    private function lineAt(int $offset): int
    {
        $low = 0;
        $high = count($this->lineStarts) - 1;

        while ($low < $high) {
            $middle = intdiv($low + $high + 1, 2);

            if ($this->lineStarts[$middle] <= $offset) {
                $low = $middle;

                continue;
            }

            $high = $middle - 1;
        }

        return $low + 1;
    }

    /**
     * @return list<int>
     */
    private function indexLines(string $text): array
    {
        $starts = [0];
        $at = 0;

        while (($at = strpos($text, "\n", $at)) !== false) {
            $at++;
            $starts[] = $at;
        }

        return $starts;
    }
}
