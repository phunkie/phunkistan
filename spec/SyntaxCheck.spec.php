<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Phunkie\Stan\Check\SyntaxCheck;
use Phunkie\Stan\Source\Source;

describe("SyntaxCheck", function () {
    $checking = function (string $code): array {
        $file = sys_get_temp_dir() . "/phunkistan-syntax-" . uniqid() . ".phunkie";
        file_put_contents($file, $code);

        try {
            return (new SyntaxCheck())->on(new Source($file, "src/Todo.phunkie"));
        } finally {
            unlink($file);
        }
    };

    it("has nothing to say about a source that parses", function () use ($checking) {
        expect($checking("function name(): string\n{\n    return \"ada\";\n}"))->toBe([]);
    });

    // A source needs no opening tag, so one is supplied before parsing. It goes
    // on the line that was already there, which is why the line reported here
    // is the line the reader is looking at.
    it("reports the line the source broke on, counting as the reader counts", function () use ($checking) {
        $diagnostics = $checking("function name(): string\n{\n    \$todo = ;\n\n    return \"ada\";\n}");

        expect(count($diagnostics))->toBe(1);
        expect($diagnostics[0]->span->line)->toBe(3);
    });

    it("names the source the reader named, not the one the machine resolved", function () use ($checking) {
        expect($checking("\$todo = ;")[0]->span->file)->toBe("src/Todo.phunkie");
    });

    it("gives the failure a category and a code", function () use ($checking) {
        $diagnostic = $checking("\$todo = ;")[0];

        expect($diagnostic->category)->toBe("SYNTAX ERROR");
        expect($diagnostic->code)->toBe("E0001");
    });
});
