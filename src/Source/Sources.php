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
use UnexpectedValueException;

/**
 * The sources a path names, whether that is one file or a tree of them.
 *
 * A path that names nothing readable is a failure and says so. Answering with
 * an empty list would be indistinguishable from a directory of faultless code,
 * and a checker that reports success for work it did not do is worse than one
 * that does not run: a mistyped path in CI stays green forever.
 *
 * Only `.phunkie` files are collected when walking. A project keeps compiled
 * output beside its sources often enough that reading whatever is there would
 * mean reporting on generated code, which is the one thing this tool must never
 * do. A file named explicitly is taken at its word, because naming it is the
 * clearer instruction.
 */
final class Sources
{
    private const EXTENSION = 'phunkie';

    /**
     * @param string $path File or directory the caller named
     */
    public function __construct(
        private readonly string $path,
    ) {
    }

    /**
     * Every source the path names.
     *
     * @throws UnreadablePath If the path names nothing that can be read
     *
     * @return list<Source> Sources found, each named as the caller named the path
     */
    public function all(): array
    {
        if (is_file($this->path)) {
            return [new Source($this->path, $this->path)];
        }

        if (!is_dir($this->path)) {
            throw UnreadablePath::notFound($this->path);
        }

        return $this->walk();
    }

    /**
     * @throws UnreadablePath
     *
     * @return list<Source>
     */
    private function walk(): array
    {
        $root = (string) realpath($this->path);
        $prefix = rtrim($this->path, '/');
        $sources = [];
        $seen = [];

        foreach ($this->files() as $file) {
            if ($file->isDir() || $file->getExtension() !== self::EXTENSION) {
                continue;
            }

            $resolved = $file->getRealPath();

            // A link that points nowhere, or out of the tree being checked.
            // Either way there is no honest name to give it under the path the
            // caller asked about, and inventing one puts a diagnostic on a file
            // that does not exist.
            if ($resolved === false || !str_starts_with($resolved, $root . '/')) {
                continue;
            }

            if (isset($seen[$resolved])) {
                continue;
            }

            $seen[$resolved] = true;
            $sources[] = new Source($resolved, $prefix . '/' . substr($resolved, strlen($root) + 1));
        }

        return $sources;
    }

    /**
     * @throws UnreadablePath
     *
     * @return list<SplFileInfo>
     */
    private function files(): array
    {
        try {
            return iterator_to_array(
                new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::LEAVES_ONLY,
                    RecursiveIteratorIterator::CATCH_GET_CHILD
                ),
                false
            );
        } catch (UnexpectedValueException $couldNotOpen) {
            throw UnreadablePath::cannotOpen($this->path, $couldNotOpen);
        }
    }
}
