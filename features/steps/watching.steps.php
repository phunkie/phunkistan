<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use function PhpSpec\attach;

$phunkistan = dirname(__DIR__, 2) . '/bin/phunkistan';

/**
 * Waits for a condition instead of sleeping a fixed time. How long a watcher
 * takes to notice a save is not something a test can know, and guessing is what
 * makes this kind of test flaky.
 */
$eventually = function (Closure $condition, float $seconds = 10.0): bool {
    $deadline = microtime(true) + $seconds;

    do {
        if ($condition()) {
            return true;
        }

        usleep(50_000);
    } while (microtime(true) < $deadline);

    // The helper is the only thing that knows how long it waited, and returning
    // a bool throws that away. Said here rather than at each call site, so every
    // caller is served and none of them has to remember.
    attach('waited', sprintf('%.1f seconds, and the condition never held.', $seconds));

    return false;
};

$write = function (string $workspace, string $path, string $source): void {
    $file = $workspace . '/' . $path;

    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0o777, true);
    }

    file_put_contents($file, $source . "\n");
};

$start = function (object $world, string $path) use ($phunkistan): void {
    $world->watchLog = $world->workspace . '/watch.log';

    // Registered when the watch starts and read only if a step fails, so it
    // holds everything the watcher printed by then rather than the nothing it
    // has printed at this moment.
    attach('watch log', fn () => is_file($world->watchLog) ? (string) file_get_contents($world->watchLog) : '(no log)');

    // The array form of proc_open runs the binary directly, with no shell in
    // between, so terminating the process afterwards terminates the watcher
    // rather than a shell that outlives it.
    $world->watch = proc_open(
        [PHP_BINARY, $phunkistan, '--watch', $path],
        [1 => ['file', $world->watchLog, 'w'], 2 => ['file', $world->watchLog, 'a']],
        $pipes,
        $world->workspace
    );
};

given("there is a source {string} containing {string}", function (string $path, string $source) use ($write) {
    $write($this->workspace, $path, $source);
});

when("the checker starts watching {string}", function (string $path) use ($start) {
    $start($this, $path);
});

// The step promises the checker is watching, so it waits until it says so.
// Saving before the first snapshot is taken would be caught by the check that
// runs at startup instead, and the scenario would pass without the watch having
// done anything at all.
given("the checker is watching {string}", function (string $path) use ($start, $eventually) {
    $start($this, $path);

    $watching = $eventually(
        fn () => is_file($this->watchLog) && str_contains((string) file_get_contents($this->watchLog), 'Watching')
    );

    expect($watching)->toBeTrue();
});

when("I save {string} containing {string}", function (string $path, string $source) use ($write) {
    $write($this->workspace, $path, $source);
});

then("the watch log should eventually contain {string}", function (string $needle) use ($eventually) {
    $arrived = $eventually(
        fn () => is_file($this->watchLog) && str_contains((string) file_get_contents($this->watchLog), $needle)
    );

    expect($arrived)->toBeTrue();
});

// Registered before the hook that removes the workspace, so the watcher is gone
// before the directory it is polling is.
afterScenario(function () {
    if (!isset($this->watch) || !is_resource($this->watch)) {
        return;
    }

    proc_terminate($this->watch);
    proc_close($this->watch);
});
