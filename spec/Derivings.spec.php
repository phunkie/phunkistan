<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Phunkie\Stan\Type\Notation;

describe("Deriving notation", function () {
    $read = fn (string $php) => (new Notation())->readFrom('<?php ' . $php);

    it("reads the clause on a bodyless class", function () use ($read) {
        $found = $read('final class Coin(string $currency, int $amount) deriving Show, Eq;');

        expect(count($found->syntheses))->toBe(1);
        expect($found->syntheses[0]->derivings)->toBe(['Show', 'Eq']);
        expect($found->php)->not()->toContain('deriving');
    });

    it("reads the clause when the braces came back", function () use ($read) {
        $php = "final class Some<T>(T \$value) extends Option<T> deriving Show\n{\n}";
        $found = $read($php);

        expect($found->syntheses[0]->derivings)->toBe(['Show']);
        expect($found->php)->not()->toContain('deriving');
        expect(strlen($found->php))->toBe(strlen('<?php ' . $php));
    });

    // A head with no primary constructor is not a synthesis, so the clause
    // travels on its own record: whose it is, what it grants, whether an
    // implements is already there to join, and where the body opens so the
    // compiler knows where the powers' methods go.
    it("reads the clause on a plain head", function () use ($read) {
        $php = "abstract class ImmList<T> implements Monad deriving Show\n{\n}";
        $found = $read($php);

        expect(count($found->derivings))->toBe(1);
        $deriving = $found->derivings[0];
        expect($deriving->class)->toBe('ImmList');
        expect($deriving->powers)->toBe(['Show']);
        expect($deriving->joinsImplements)->toBe(true);
        expect($found->php)->not()->toContain('deriving');
        expect(strlen($found->php))->toBe(strlen('<?php ' . $php));
        expect(substr($php, $deriving->bodyOpen - strlen('<?php '), 1))->toBe('{');
    });

    it("knows when there is no implements to join", function () use ($read) {
        $found = $read("class Wallet deriving Show\n{\n}");

        expect($found->derivings[0]->joinsImplements)->toBe(false);
        expect($found->derivings[0]->class)->toBe('Wallet');
    });

    it("finds nothing where nothing is derived", function () use ($read) {
        expect($read('final class Plain {}')->derivings)->toBe([]);
    });
});
