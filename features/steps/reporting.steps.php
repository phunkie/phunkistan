<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

given("there is a source {string} containing:", function (string $path, string $source) {
    $file = $this->workspace . '/' . $path;

    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0o777, true);
    }

    file_put_contents($file, $source);
});

when("I check {string}", function (string $path) {
    ($this->run)($path);
});

then("it should have passed", function () {
    expect($this->exitCode)->toBe(0);
});

then("it should have said nothing", function () {
    expect($this->output)->toBeEmpty();
});

then("it should have failed", function () {
    expect($this->exitCode)->not()->toBe(0);
});

then("it should have reported {string} at line {int}", function (string $path, int $line) {
    expect($this->output)->toContain($path);
    expect($this->output)->toContain(':' . $line . ':');
});

then("it should have shown me the line I wrote", function () {
    expect($this->output)->toContain('$todo = ;');
    expect($this->output)->toContain('^');
});

when("I check {string} as json", function (string $path) {
    ($this->run)('--format=json', $path);
});

then("it should have emitted one diagnostic for {string}", function (string $path) {
    $diagnostics = json_decode($this->output, true, 512, JSON_THROW_ON_ERROR);

    expect(count($diagnostics))->toBe(1);
    expect($diagnostics[0]['uri'])->toBe($path);
    expect($diagnostics[0]['source'])->toBe('phunkistan');
});
