<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Phunkie\Stan\Source\SourceTree;

describe("SourceTree", function () {
    $inWorkspace = function (Closure $body): void {
        $directory = sys_get_temp_dir() . "/phunkistan-source-tree-" . uniqid();
        mkdir($directory . "/App", 0o777, true);

        try {
            $body($directory);
        } finally {
            exec(sprintf("rm -rf %s", escapeshellarg($directory)));
        }
    };

    $pathsIn = function (string $directory): array {
        $paths = array_map(fn ($source) => $source->relativePath, (new SourceTree($directory))->files());
        sort($paths);

        return $paths;
    };

    it("finds every source, however deep, and nothing else", function () use ($inWorkspace, $pathsIn) {
        $inWorkspace(function (string $directory) use ($pathsIn) {
            file_put_contents($directory . "/Root.phunkie", "<?php\n");
            file_put_contents($directory . "/App/Todo.phunkie", "<?php\n");
            file_put_contents($directory . "/App/Todo.php", "<?php\n");
            file_put_contents($directory . "/App/notes.md", "hello");

            expect($pathsIn($directory))->toBe([
                $directory . "/App/Todo.phunkie",
                $directory . "/Root.phunkie",
            ]);
        });
    });

    // A diagnostic has to name a path the reader can act on. They asked about a
    // directory, so they are answered about files under the name they used, not
    // under one resolved behind their back.
    it("names a source the way the caller named the directory", function () use ($inWorkspace, $pathsIn) {
        $inWorkspace(function (string $directory) use ($pathsIn) {
            file_put_contents($directory . "/App/Todo.phunkie", "<?php\n");

            $here = getcwd();
            chdir(dirname($directory));

            try {
                expect($pathsIn(basename($directory)))->toBe([basename($directory) . "/App/Todo.phunkie"]);
            } finally {
                chdir((string) $here);
            }
        });
    });

    it("finds nothing where there is nothing to find", function () use ($inWorkspace, $pathsIn) {
        $inWorkspace(function (string $directory) use ($pathsIn) {
            expect($pathsIn($directory))->toBe([]);
            expect($pathsIn($directory . "/nowhere"))->toBe([]);
        });
    });
});
