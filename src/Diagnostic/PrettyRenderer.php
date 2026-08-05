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
 * Writes diagnostics for a person reading a terminal.
 *
 * Nothing here is carried by colour. The banner, the position and the code do
 * the work as characters, so the message a screen reader is given is the same
 * message everyone else gets rather than a stripped one. That also makes the
 * output safe to snapshot, which is how the layout stays still.
 */
final class PrettyRenderer implements Renderer
{
    public const WIDTH = 76;

    /**
     * @param SourceFrame $sourceFrame Draws the reader's own code under the headline
     * @param int         $width       Columns the banner rule is drawn to
     */
    public function __construct(
        private readonly SourceFrame $sourceFrame = new SourceFrame(),
        private readonly int $width = self::WIDTH,
    ) {
    }

    /**
     * Renders every diagnostic, in the order they were found.
     *
     * @param list<Diagnostic> $diagnostics Diagnostics to write
     *
     * @return string The rendered report, empty when there is nothing to say
     */
    public function render(array $diagnostics): string
    {
        if ($diagnostics === []) {
            return '';
        }

        return implode("\n", array_map($this->one(...), $diagnostics));
    }

    private function one(Diagnostic $diagnostic): string
    {
        return sprintf(
            "%s\n\n  %s\n%s\n  %s  phunkistan explain %s\n",
            $this->banner($diagnostic),
            $diagnostic->headline,
            $this->frame($diagnostic),
            $diagnostic->code,
            $diagnostic->code
        );
    }

    /**
     * A diagnostic that arrived without its source still says everything else
     * it knows, rather than rendering an empty frame nobody can read.
     */
    private function frame(Diagnostic $diagnostic): string
    {
        if ($diagnostic->source === '') {
            return '';
        }

        return "\n" . $this->sourceFrame->around($diagnostic->source, $diagnostic->span) . "\n";
    }

    /**
     * The category and the position, on one rule that runs the width of the
     * report, so a page of diagnostics reads as a list rather than a wall.
     */
    private function banner(Diagnostic $diagnostic): string
    {
        $left = sprintf('── %s ', $diagnostic->category);
        $right = sprintf(' %s ──', $diagnostic->span);
        $fill = max(1, $this->width - mb_strlen($left) - mb_strlen($right));

        return $left . str_repeat('─', $fill) . $right;
    }
}
