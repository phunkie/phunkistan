<?php

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
    public function __construct(
        public readonly string $path,
        public readonly string $relativePath,
    ) {
    }

    public function read(): string
    {
        return (string) file_get_contents($this->path);
    }
}
