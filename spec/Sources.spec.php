<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Phunkie\Stan\Source\Sources;
use Phunkie\Stan\Source\UnreadablePath;

describe("Sources", function () {
    $inWorkspace = function (Closure $body): void {
        $directory = sys_get_temp_dir() . "/phunkistan-sources-" . uniqid();
        mkdir($directory . "/App", 0o777, true);

        try {
            $body($directory);
        } finally {
            exec(sprintf("rm -rf %s", escapeshellarg($directory)));
        }
    };

    $pathsIn = function (string $path): array {
        $paths = array_map(fn ($source) => $source->relativePath, (new Sources($path))->all());
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

    // Naming a file is the clearer instruction, and it is what an editor does
    // on save and what a commit hook does per staged file.
    it("takes a file the caller named at its word", function () use ($inWorkspace) {
        $inWorkspace(function (string $directory) {
            file_put_contents($directory . "/App/Todo.phunkie", "<?php\n");

            $sources = (new Sources($directory . "/App/Todo.phunkie"))->all();

            expect(count($sources))->toBe(1);
            expect($sources[0]->relativePath)->toBe($directory . "/App/Todo.phunkie");
        });
    });

    // Answering nothing would be the same shape as a directory of faultless
    // code and mean the opposite. A path that drifts after a rename would then
    // keep a CI job green for ever.
    it("refuses a path that is not there rather than finding nothing in it", function () use ($inWorkspace) {
        $inWorkspace(function (string $directory) {
            expect(fn () => (new Sources($directory . "/nowhere"))->all())->toThrow(UnreadablePath::class);
        });
    });

    it("refuses a directory it cannot open", function () use ($inWorkspace) {
        $inWorkspace(function (string $directory) {
            $closed = $directory . "/closed";
            mkdir($closed, 0o000);

            try {
                expect(fn () => (new Sources($closed))->all())->toThrow(UnreadablePath::class);
            } finally {
                chmod($closed, 0o755);
            }
        });
    });

    it("passes over a link that points nowhere", function () use ($inWorkspace, $pathsIn) {
        $inWorkspace(function (string $directory) use ($pathsIn) {
            symlink($directory . "/gone.phunkie", $directory . "/App/Dangling.phunkie");

            expect($pathsIn($directory))->toBe([]);
        });
    });

    // A link out of the tree resolves to a path that cannot be expressed under
    // the one the caller asked about. Naming it anyway produces a diagnostic
    // against a file that does not exist.
    it("passes over a link that leaves the tree", function () use ($inWorkspace, $pathsIn) {
        $inWorkspace(function (string $directory) use ($pathsIn) {
            mkdir($directory . "/outside");
            file_put_contents($directory . "/outside/Far.phunkie", "<?php\n");
            mkdir($directory . "/inside");
            symlink($directory . "/outside/Far.phunkie", $directory . "/inside/Near.phunkie");

            expect($pathsIn($directory . "/inside"))->toBe([]);
        });
    });

    it("reports a source reachable twice only once", function () use ($inWorkspace, $pathsIn) {
        $inWorkspace(function (string $directory) use ($pathsIn) {
            file_put_contents($directory . "/App/Todo.phunkie", "<?php\n");
            symlink($directory . "/App/Todo.phunkie", $directory . "/Again.phunkie");

            expect(count($pathsIn($directory)))->toBe(1);
        });
    });
});
