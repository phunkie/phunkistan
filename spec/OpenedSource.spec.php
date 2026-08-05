<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Phunkie\Stan\Source\OpeningTag;

describe("OpenedSource", function () {
    $opened = fn (string $source) => (new OpeningTag())->open($source);

    it("hands a parser a source it can read", function () use ($opened) {
        expect($opened('$x = 1;')->text())->toBe('<?php $x = 1;');
        expect($opened("<?php\n\$x = 1;")->text())->toBe("<?php\n\$x = 1;");
    });

    // The tag goes on the line that was already there rather than one of its
    // own, so every line keeps the number it was written on. A diagnostic that
    // points one line off is worse than none, because it sends the reader to
    // code that is fine.
    it("leaves every line where the reader wrote it", function () use ($opened) {
        $source = "function name(): string\n{\n    \$todo = ;\n}";

        $text = $opened($source)->text();

        expect(substr_count($text, "\n"))->toBe(substr_count($source, "\n"));
        expect(explode("\n", $text)[2])->toBe('    $todo = ;');
    });

    // Everything downstream reports positions, so this is the one place a
    // parser's idea of where something is becomes the reader's. Getting it
    // wrong sends people to code that is fine, and they stop believing the tool
    // long before they work out why.
    it("puts the first line back where the reader had it", function () use ($opened) {
        $source = '$todo = ;';

        expect($opened($source)->positionOf(strpos($opened($source)->text(), ';'))->column)->toBe(9);
    });

    it("leaves a source that opened itself alone", function () use ($opened) {
        $source = "<?php\n\$todo = ;";
        $position = $opened($source)->positionOf(strpos($opened($source)->text(), ';'));

        expect($position->line)->toBe(2);
        expect($position->column)->toBe(9);
    });

    it("counts lines from one, as the reader's editor does", function () use ($opened) {
        $source = "function f()\n{\n    \$todo = ;\n}";

        expect($opened($source)->positionOf(strpos($opened($source)->text(), ';'))->line)->toBe(3);
    });

    // A column is a count of characters, not of bytes. An accented identifier
    // earlier on the line would otherwise push the caret one place right for
    // every one of them, on a line the reader can see is only so long.
    it("counts characters, not bytes", function () use ($opened) {
        $source = "    \$caf\u{e9} = ;";

        expect($opened($source)->positionOf(strpos($opened($source)->text(), ';'))->column)->toBe(13);
    });

    it("answers for a position at the very start", function () use ($opened) {
        $position = $opened('$x')->positionOf(strlen(OpeningTag::TAG));

        expect($position->line)->toBe(1);
        expect($position->column)->toBe(1);
    });
});
