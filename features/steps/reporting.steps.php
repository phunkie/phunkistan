<?php

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
