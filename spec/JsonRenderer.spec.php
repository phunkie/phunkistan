<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Phunkie\Stan\Diagnostic\Diagnostic;
use Phunkie\Stan\Diagnostic\JsonRenderer;
use Phunkie\Stan\Diagnostic\Span;

describe("JsonRenderer", function () {
    $diagnostic = new Diagnostic(
        "E0001",
        "SYNTAX ERROR",
        "Syntax error, unexpected \";\"",
        new Span("src/Todo.phunkie", 3, 13),
        "a\nb\n    \$todo = ;\n"
    );

    $decode = fn (string $json): array => json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    it("emits one entry for each diagnostic", function () use ($diagnostic, $decode) {
        expect(count($decode((new JsonRenderer())->render([$diagnostic, $diagnostic]))))->toBe(2);
    });

    // LSP counts lines and characters from zero, and a reader counts from one.
    // Getting this wrong puts every squiggle in an editor one line above the
    // mistake, which is the failure mode that makes a tool untrustworthy.
    it("counts from zero, the way an editor does", function () use ($diagnostic, $decode) {
        $range = $decode((new JsonRenderer())->render([$diagnostic]))[0]["range"];

        expect($range["start"]["line"])->toBe(2);
        expect($range["start"]["character"])->toBe(12);
    });

    it("carries the code, the message and where it came from", function () use ($diagnostic, $decode) {
        $entry = $decode((new JsonRenderer())->render([$diagnostic]))[0];

        expect($entry["code"])->toBe("E0001");
        expect($entry["message"])->toBe("Syntax error, unexpected \";\"");
        expect($entry["source"])->toBe("phunkistan");
        expect($entry["uri"])->toContain("src/Todo.phunkie");
    });

    it("says every diagnostic is an error, for now", function () use ($diagnostic, $decode) {
        expect($decode((new JsonRenderer())->render([$diagnostic]))[0]["severity"])->toBe(1);
    });

    it("emits an empty list rather than nothing when all is well", function () use ($decode) {
        expect($decode((new JsonRenderer())->render([])))->toBe([]);
    });
});
