<?php

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

    it("finds every source, however deep, and nothing else", function () use ($inWorkspace) {
        $inWorkspace(function (string $directory) {
            file_put_contents($directory . "/Root.phunkie", "<?php\n");
            file_put_contents($directory . "/App/Todo.phunkie", "<?php\n");
            file_put_contents($directory . "/App/Todo.php", "<?php\n");
            file_put_contents($directory . "/App/notes.md", "hello");

            $paths = array_map(fn ($source) => $source->relativePath, (new SourceTree($directory))->files());
            sort($paths);

            expect($paths)->toBe(["App/Todo.phunkie", "Root.phunkie"]);
        });
    });
});
