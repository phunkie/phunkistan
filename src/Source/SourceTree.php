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
    private const EXTENSION = 'phunkie';

    /**
     * @param string $directory Directory to walk, or a path that is not one
     */
    public function __construct(
        private readonly string $directory,
    ) {
    }

    /**
     * Every source under the directory, however deep.
     *
     * A path that is not a directory yields nothing rather than failing, so a
     * mistyped argument is reported once by the caller instead of throwing from
     * here, where all that is known is that a walk could not start.
     *
     * Each source is named the way the caller named the directory, so asking
     * about `src` gets answers about `src/Todo.phunkie`. A reader who is told
     * about `Todo.phunkie` has to work out which one, and an editor cannot open
     * it at all.
     *
     * @return list<Source> Sources found, in no particular order
     */
    public function files(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $root = (string) realpath($this->directory);
        $prefix = rtrim($this->directory, '/');
        $sources = [];

        foreach ($this->walk() as $file) {
            if ($file->isDir() || $file->getExtension() !== self::EXTENSION) {
                continue;
            }

            $path = (string) $file->getRealPath();
            $under = ltrim(substr($path, strlen($root)), '/');
            $sources[] = new Source($path, $prefix . '/' . $under);
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
