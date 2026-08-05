<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$phunkistan = dirname(__DIR__, 2) . '/bin/phunkistan';

beforeScenario(function () use ($phunkistan) {
    $this->workspace = sys_get_temp_dir() . '/phunkistan-features-' . uniqid();
    mkdir($this->workspace, 0o777, true);

    $this->run = function (string ...$arguments) use ($phunkistan) {
        $command = sprintf(
            'cd %s && %s %s %s 2>&1',
            escapeshellarg($this->workspace),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($phunkistan),
            implode(' ', array_map('escapeshellarg', $arguments))
        );

        exec($command, $lines, $exitCode);

        $this->output = implode("\n", $lines);
        $this->exitCode = $exitCode;
    };
});

afterScenario(function () {
    exec('rm -rf ' . escapeshellarg($this->workspace));
});
