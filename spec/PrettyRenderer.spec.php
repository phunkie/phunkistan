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
use Phunkie\Stan\Diagnostic\PrettyRenderer;
use Phunkie\Stan\Diagnostic\Span;

describe("PrettyRenderer", function () {
    $diagnostic = new Diagnostic(
        "E0001",
        "SYNTAX ERROR",
        "Syntax error, unexpected token \";\"",
        new Span("src/Todo.phunkie", 3, 13)
    );

    it("banners the category and the position", function () use ($diagnostic) {
        expect((new PrettyRenderer())->render([$diagnostic]))
            ->toContain("SYNTAX ERROR")
            ->toContain("src/Todo.phunkie:3:13");
    });

    it("states the rule that was broken", function () use ($diagnostic) {
        expect((new PrettyRenderer())->render([$diagnostic]))
            ->toContain("Syntax error, unexpected token \";\"");
    });

    // The code is how a reader asks for more, so it is printed even though
    // nothing can explain it yet. Leaving it out until then would train people
    // not to look for it.
    it("names the code and how to ask about it", function () use ($diagnostic) {
        expect((new PrettyRenderer())->render([$diagnostic]))
            ->toContain("E0001")
            ->toContain("phunkistan explain E0001");
    });

    it("says nothing at all when there is nothing wrong", function () {
        expect((new PrettyRenderer())->render([]))->toBe("");
    });
});
