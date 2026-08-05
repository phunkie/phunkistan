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
     * A file that cannot be read is not a file with nothing in it. Answering
     * with an empty string would make it parse, and an unreadable source would
     * then be reported as faultless, which is the one answer nobody can act on.
     *
     * @throws UnreadablePath If the file has gone, or cannot be opened
     *
     * @return string The file's contents
     */
    public function read(): string
    {
        if (!is_readable($this->path)) {
            throw UnreadablePath::notFound($this->relativePath);
        }

        $contents = file_get_contents($this->path);

        if ($contents === false) {
            throw UnreadablePath::notFound($this->relativePath);
        }

        return $contents;
    }
}
