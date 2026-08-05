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
 * Writes diagnostics out in one particular shape.
 *
 * The interface lives beside the diagnostics rather than beside the command
 * line, because the command line is not the only consumer: a language server
 * and phunkiec both want to render these, and an abstraction owned by the CLI
 * would drag the CLI into both of them.
 */
interface Renderer
{
    /**
     * Renders every diagnostic, in the order they were found.
     *
     * @param list<Diagnostic> $diagnostics Diagnostics to write
     *
     * @return string The rendered report
     */
    public function render(array $diagnostics): string;
}
