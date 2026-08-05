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

describe("OpeningTag", function () {
    it("opens a source that did not open itself", function () {
        expect((new OpeningTag())->ensure('$x = 1;'))->toBe('<?php $x = 1;');
    });

    it("leaves a source that opened itself alone", function () {
        expect((new OpeningTag())->ensure("<?php\n\$x = 1;"))->toBe("<?php\n\$x = 1;");
    });

    // The tag goes on the line that was already there rather than one of its
    // own, so every line keeps the number it was written on. A diagnostic that
    // points one line off is worse than no diagnostic, because it sends the
    // reader to code that is fine.
    it("leaves every line where the reader wrote it", function () {
        $source = "function name(): string\n{\n    \$todo = ;\n}";

        $tagged = (new OpeningTag())->ensure($source);

        expect(substr_count($tagged, "\n"))->toBe(substr_count($source, "\n"));
        expect(explode("\n", $tagged)[2])->toBe('    $todo = ;');
    });
});
