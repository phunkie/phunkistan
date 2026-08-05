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
 * One source, and the two names it answers to.
 *
 * The absolute path is what reads the file. The relative one is what a
 * diagnostic prints, because a reader recognises the path they typed rather
 * than the one the machine resolved.
 */
final class Source
{
    /**
     * @param string $path         Absolute path the file is read from
     * @param string $relativePath Path as the reader wrote it, used in diagnostics
     */
    public function __construct(
        public readonly string $path,
        public readonly string $relativePath,
    ) {
    }

    /**
     * Reads the source in full.
     *
     * A source that cannot be read comes back empty rather than throwing. The
     * file was there when the tree was walked, so its disappearing since is a
     * race with something else, and refusing to check the rest of a project
     * over it would be the worse answer.
     *
     * @return string The file's contents, or an empty string if it cannot be read
     */
    public function read(): string
    {
        if (!is_readable($this->path)) {
            return '';
        }

        return (string) file_get_contents($this->path);
    }
}
