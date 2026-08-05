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

namespace Phunkie\Stan\Diagnostic;

/**
 * The reader's own code, with a mark under the part that went wrong.
 *
 * A position on its own asks the reader to decode a line and a column against
 * what is on their screen. Showing the source removes that step, and showing it
 * verbatim removes a second one: reformatted code has to be matched back
 * against what they actually wrote before it can be read at all.
 */
final class SourceFrame
{
    private const CONTEXT = 2;

    private const GUTTER = ' │  ';

    /**
     * Renders the lines around a span, with a caret under its column.
     *
     * @param string $source Source exactly as the reader wrote it
     * @param Span   $span   Position to mark
     *
     * @return string The frame, without a trailing newline
     */
    public function around(string $source, Span $span): string
    {
        $lines = explode("\n", rtrim($source, "\n"));
        $first = max(1, $span->line - self::CONTEXT);
        $last = min(count($lines), $span->line + self::CONTEXT);
        $width = strlen((string) $last);
        $frame = [];

        for ($number = $first; $number <= $last; $number++) {
            $frame[] = $this->numbered($number, $width, $lines[$number - 1]);

            if ($number === $span->line) {
                $frame[] = $this->caret($width, $span->column);
            }
        }

        return implode("\n", $frame);
    }

    private function numbered(int $number, int $width, string $line): string
    {
        return sprintf('  %' . $width . 'd%s%s', $number, self::GUTTER, $line);
    }

    /**
     * The caret sits under the column it names, so the gutter is reproduced at
     * its full width rather than guessed at.
     */
    private function caret(int $width, int $column): string
    {
        return '  ' . str_repeat(' ', $width) . self::GUTTER . str_repeat(' ', max(0, $column - 1)) . '^';
    }
}
