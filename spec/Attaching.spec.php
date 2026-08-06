<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use PhpParser\Node\Param;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Phunkie\Stan\Source\OpeningTag;
use Phunkie\Stan\Type\Attaching;
use Phunkie\Stan\Type\Notation;

describe("Attaching", function () {
    $onto = function (string $php): array {
        $opened = (new OpeningTag())->open($php);
        $read = (new Notation())->readFrom($opened->text());
        $nodes = (new ParserFactory())->createForNewestSupportedVersion()->parse($read->php) ?? [];

        return [$nodes, (new Attaching())->attach($nodes, $read->types), $read];
    };

    // The notation is blanked to spaces of the same length, so a type's region
    // falls exactly on the declaration it was written on. Nothing is matched by
    // name or by counting.
    it("puts a parameter's type on that parameter", function () use ($onto) {
        [$nodes, $placed] = $onto('function f(ImmList<Int> $xs): Int { return 1; }');

        $param = (new NodeFinder())->findFirstInstanceOf($nodes, Param::class);

        expect($placed)->toBeGreaterThan(0);
        expect((string) $param->getAttribute(Attaching::ATTRIBUTE))->toBe('ImmList<Int>');
    });

    // A Param sits inside a ClassMethod inside a Class_, and all three contain
    // the region. Only the innermost is what the type was written on.
    it("chooses the smallest node that contains it", function () use ($onto) {
        [$nodes] = $onto('class A { public function f(ImmMap<String, Int> $m): Int { return 1; } }');

        $param = (new NodeFinder())->findFirstInstanceOf($nodes, Param::class);

        expect((string) $param->getAttribute(Attaching::ATTRIBUTE))->toBe('ImmMap<String, Int>');
    });

    it("places every type it was given", function () use ($onto) {
        [, $placed, $read] = $onto('function f(ImmList<Int> $xs, Option<String> $o): ImmSet<Int> { return $xs; }');

        expect($placed)->toBe(count($read->types));
    });

    it("leaves a node alone when nothing was written on it", function () use ($onto) {
        [$nodes] = $onto('function f(ImmList $xs): Int { return 1; }');

        $param = (new NodeFinder())->findFirstInstanceOf($nodes, Param::class);

        expect($param->getAttribute(Attaching::ATTRIBUTE))->toBeNull();
    });
});
