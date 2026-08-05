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

describe("Notation", function () {
    $read = fn (string $php) => (new Notation())->readFrom('<?php ' . $php);

    $types = fn (string $php) => implode(' | ', array_map('strval', (new Notation())->readFrom('<?php ' . $php)->types));

    it("finds a type argument and blanks only its brackets", function () use ($read) {
        $found = $read('function f(ImmList<Int> $x): int { return 1; }');

        expect((string) $found->types[0])->toBe('ImmList<Int>');
        expect($found->php)->toContain('ImmList')->not()->toContain('<Int>');
    });

    // Every byte after the notation keeps the offset it had, which is what
    // lets a position from PHP's parser be a position in the file the reader
    // wrote with nothing in between to get wrong.
    it("leaves the source exactly as long as it found it", function () use ($read) {
        $php = 'function f(ImmList<Option<Int>> $x): ImmList<Int> { return $x; }';

        expect(strlen($read($php)->php))->toBe(strlen('<?php ' . $php));
    });

    it("reads a name PHP accepts and this grammar nearly did not", function () use ($types) {
        expect($types('function f(Caf' . "\u{e9}" . '<Int> $x): int { return 1; }'))->toBe('Caf' . "\u{e9}" . '<Int>');
    });

    // Cursor::takeName was written for qualified names from the start. The
    // detector was not, so a fully qualified type reached PHP untouched and was
    // reported as an unexpected "<", which is the message this milestone exists
    // to stop emitting.
    it("finds a type however it is qualified", function () use ($types) {
        expect($types('function f(Phunkie\Types\ImmList<Int> $x): int { return 1; }'))
            ->toBe('Phunkie\Types\ImmList<Int>');
        expect($types('function f(\ImmList<Int> $x): int { return 1; }'))->toBe('\ImmList<Int>');
    });

    it("finds a type named by a word PHP keeps for itself", function () use ($types) {
        expect($types('function f(list<Int> $x): int { return 1; }'))->toBe('list<Int>');
        expect($types('function f(callable<Int> $x): int { return 1; }'))->toBe('callable<Int>');
    });

    it("reads a type written with a space before its arguments", function () use ($types) {
        expect($types('function f(ImmList <Int> $x): int { return 1; }'))->toBe('ImmList<Int>');
    });

    // Read once, not once per level. The nesting is already in the type that
    // was read, so reading the inner names again says the same thing three more
    // times, and says it three more times when it is wrong.
    it("reads a nested type once", function () use ($types) {
        expect($types('function f(A<B<C<Int>>> $x): int { return 1; }'))->toBe('A<B<C<Int>>>');
    });

    it("reports a mistake in a nested type once", function () use ($read) {
        expect(count($read('function f(ImmList<Option<Int $x): int { return 1; }')->errors))->toBe(1);
    });

    // Arithmetic that looks like notation is not judged here. This object
    // reports what it could not read, and whether that was ever notation is a
    // question only PHP can answer, so the checker asks it.
    it("has nothing to read where no name stands in front of the bracket", function () use ($read) {
        foreach (['$b = 8 >> 2;', '$s = $a < $b;'] as $php) {
            expect(count($read($php)->errors))->toBe(0);
        }
    });

    // A name in front of a bracket is read as a type and fails, which is
    // correct: this object cannot tell a comparison from broken notation, and
    // guessing was how eight pieces of valid PHP came to be reported as errors.
    it("reports what it could not read and leaves the judgement to the checker", function () use ($read) {
        expect(count($read('$c = MAX < 3;')->errors))->toBe(1);
    });

    it("has nothing to say about PHP that only looks like notation", function () use ($read) {
        $php = <<<'PHP'
            $s = "ImmList<Int>";
            // ImmList<Int>
            /* ImmList<Int> */
            $h = <<<TXT
            ImmList<Int>
            TXT;
            $c = Foo::class;
            $f = strlen(...);
            PHP;

        expect(count($read($php)->errors))->toBe(0);
    });
});
