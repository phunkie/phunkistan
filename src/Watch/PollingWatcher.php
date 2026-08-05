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

namespace Phunkie\Stan\Watch;

use Phunkie\Stan\Source\Sources;
use Phunkie\Stan\Source\UnreadablePath;

/**
 * Notices changes by looking.
 *
 * There is no portable way to be told about them: inotify, kqueue and
 * ReadDirectoryChangesW are three different extensions, none of them shipped
 * with PHP, so polling is what works everywhere.
 */
final class PollingWatcher implements Watcher
{
    private const INTERVAL = 250_000;

    /**
     * @param int $intervalMicroseconds How long to wait between looks
     */
    public function __construct(
        private readonly int $intervalMicroseconds = self::INTERVAL,
    ) {
    }

    /**
     * Watches paths, calling back each time any of them changes.
     *
     * @param list<string>                $paths    Files or directories to watch
     * @param callable(list<string>):void $onChange Receives the paths that changed
     */
    public function watch(array $paths, callable $onChange): void
    {
        $previous = $this->snapshot($paths);

        while (true) {
            usleep($this->intervalMicroseconds);

            $current = $this->snapshot($paths);
            $changed = $current->changedSince($previous);
            $previous = $current;

            if ($changed !== []) {
                $onChange($changed);
            }
        }
    }

    /**
     * A path that cannot be read right now contributes nothing rather than
     * ending the watch. Directories come and go while a branch is switched, and
     * the next look will find whatever is there then.
     *
     * @param list<string> $paths
     */
    private function snapshot(array $paths): Snapshot
    {
        $fingerprints = [];

        foreach ($paths as $path) {
            try {
                $sources = (new Sources($path))->all();
            } catch (UnreadablePath) {
                continue;
            }

            foreach ($sources as $source) {
                $fingerprints[$source->relativePath] = $source->fingerprint();
            }
        }

        return new Snapshot($fingerprints);
    }
}
