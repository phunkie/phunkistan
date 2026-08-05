<?php

declare(strict_types=1);

namespace Phunkie\Stan\Source;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The sources under a directory.
 *
 * Only `.phunkie` files are read. A project holds compiled output beside its
 * sources often enough that checking whatever happens to be there would report
 * on generated code, which is the one thing this tool must never do.
 */
final class SourceTree
{
    public function __construct(
        private readonly string $directory,
    ) {
    }

    /**
     * @return list<Source>
     */
    public function files(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $root = realpath($this->directory);
        $sources = [];

        foreach ($this->walk() as $file) {
            if ($file->isDir() || $file->getExtension() !== 'phunkie') {
                continue;
            }

            $path = (string) $file->getRealPath();
            $sources[] = new Source($path, ltrim(substr($path, strlen((string) $root)), '/'));
        }

        return $sources;
    }

    /**
     * @return iterable<SplFileInfo>
     */
    private function walk(): iterable
    {
        return new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS)
        );
    }
}
