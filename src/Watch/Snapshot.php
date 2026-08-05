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
 * What every source looked like at one moment.
 *
 * Sources are fingerprinted by their contents rather than by their modification
 * time. `filemtime` has one second resolution, so two saves inside the same
 * second are indistinguishable by time and the second one would be missed,
 * which is exactly the save you make when you are fixing something in a hurry.
 */
final class Snapshot
{
    /**
     * @param array<string, string> $fingerprints Fingerprint of each source, by path
     */
    public function __construct(
        private readonly array $fingerprints,
    ) {
    }

    /**
     * Which sources are not as they were.
     *
     * A source that has appeared or gone counts as changed, because either
     * changes what the project says as a whole.
     *
     * @param Snapshot $previous The moment to compare against
     *
     * @return list<string> Paths that differ, in a stable order
     */
    public function changedSince(self $previous): array
    {
        $changed = [];

        foreach ($this->fingerprints as $path => $fingerprint) {
            if (($previous->fingerprints[$path] ?? null) !== $fingerprint) {
                $changed[] = $path;
            }
        }

        foreach ($previous->fingerprints as $path => $fingerprint) {
            if (!array_key_exists($path, $this->fingerprints)) {
                $changed[] = $path;
            }
        }

        sort($changed);

        return $changed;
    }
}
