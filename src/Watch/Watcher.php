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

/**
 * Reports sources as they change, for as long as the process runs.
 */
interface Watcher
{
    /**
     * Watches paths, calling back each time any of them changes.
     *
     * It does not return: a watch ends when the process is stopped.
     *
     * @param list<string>             $paths    Files or directories to watch
     * @param callable(list<string>):void $onChange Receives the paths that changed
     */
    public function watch(array $paths, callable $onChange): void;
}
