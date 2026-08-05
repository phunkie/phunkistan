<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Phunkie\Stan\Diagnostic\SourceFrame;
use Phunkie\Stan\Diagnostic\Span;

describe("SourceFrame", function () {
    $source = "function name(): string\n{\n    \$todo = ;\n\n    return \"ada\";\n}\n";

    // Shown exactly as it was written, never pretty printed. A reader who has
    // to match reformatted code against what is on their screen is doing the
    // work the frame was supposed to save them.
    it("shows the line it broke on, verbatim", function () use ($source) {
        expect((new SourceFrame())->around($source, new Span("t.phunkie", 3, 13)))
            ->toContain('    $todo = ;');
    });

    it("shows the lines either side, for somewhere to stand", function () use ($source) {
        $frame = (new SourceFrame())->around($source, new Span("t.phunkie", 3, 13));

        expect($frame)->toContain('{')->toContain('return "ada";');
    });

    it("numbers the lines as the reader's editor numbers them", function () use ($source) {
        expect((new SourceFrame())->around($source, new Span("t.phunkie", 3, 13)))
            ->toContain('3 │');
    });

    it("puts a caret under the column that broke", function () use ($source) {
        $lines = explode("\n", (new SourceFrame())->around($source, new Span("t.phunkie", 3, 13)));
        $caretLine = null;

        foreach ($lines as $index => $line) {
            if (str_contains($line, '^')) {
                $caretLine = [$lines[$index - 1], $line];
            }
        }

        expect($caretLine[0])->toContain('$todo = ;');
        expect(strpos($caretLine[1], '^'))->toBe(strpos($caretLine[0], ';'));
    });

    it("does not run off the top or the bottom of a short source", function () {
        expect((new SourceFrame())->around("\$x = ;\n", new Span("t.phunkie", 1, 6)))
            ->toContain('1 │');
    });
});
