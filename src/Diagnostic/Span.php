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
 * Where in a source something happened.
 *
 * Always a position in a `.phunkie` file. Nothing here ever describes generated
 * PHP, which is what lets a diagnostic be trusted: there is no mapping in
 * between to get wrong.
 */
final class Span
{
    /**
     * @param string $file   Path as the reader named it
     * @param int    $line   Line number, counting from one
     * @param int    $column Column number, counting from one
     */
    public function __construct(
        public readonly string $file,
        public readonly int $line,
        public readonly int $column,
    ) {
    }

    /**
     * The position as a reader and an editor both understand it.
     *
     * @return string The span written as `file:line:column`
     */
    public function __toString(): string
    {
        return sprintf('%s:%d:%d', $this->file, $this->line, $this->column);
    }
}
