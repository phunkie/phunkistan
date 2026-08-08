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

    // `array` is a word PHP keeps for itself and also a type it enforces, which
    // no other reserved word here is. Blanking it whole cost the reader the one
    // check PHP could still have made, and left `array<User> $users` weaker
    // than the plain `array $users` it was written to improve on.
    it("keeps a reserved word PHP would still enforce", function () use ($read) {
        expect($read('function f(array<User> $x): int { return 1; }')->php)
            ->toContain('array')->not()->toContain('<User>');
    });

    // What the compiler should remove, said as regions rather than left to be
    // worked out by comparing the two strings. It has to remove the same
    // stretches this read, or the two disagree about the language again, which
    // is the drift this package exists to end.
    //
    // Not the same question as what was put out of PHP's way. A type says
    // something about the program and is gone once it has been checked. Notation
    // that is part of the program has to be stood in for so PHP can read around
    // it and has to survive, because something downstream rewrites it. Only the
    // rule that matched knows which of the two this was.
    it("says exactly what the compiler should erase", function () {
        $php = 'function f(ImmList<Int> $x): (int) => string { return "a"; }';
        $found = (new Notation())->readFrom($source = '<?php ' . $php);

        $taken = array_map(
            static fn ($region): string => substr($source, $region->from, $region->to - $region->from),
            $found->erasures
        );

        expect($taken)->toBe(['<Int>', ': ', '(int) => string']);
    });

    // `typeclass` is phunkie's keyword and nothing to PHP, so the stand-in
    // swaps it for `interface`, which PHP can hold it as. The two words are
    // the same length, so the swap moves nothing.
    it("reads a typeclass header the way it reads an interface", function () use ($read) {
        $found = $read('typeclass Functor<F<_>> { }');

        expect(count($found->headers))->toBe(1);
        expect($found->headers[0]->name)->toBe('Functor');
        expect($found->php)->toContain('interface Functor');
    });

    // The compiler needs to write `interface` too, and it must not have a
    // second opinion about where the keyword was. The regions in
    // substitutions say: here, adopt the stand-in's text as your own.
    it("says where the compiler should adopt the stand-in's text", function () {
        $source = '<?php typeclass Functor<F<_>> { }';
        $found = (new Notation())->readFrom($source);

        expect(count($found->substitutions))->toBe(1);

        $region = $found->substitutions[0];

        expect(substr($source, $region->from, $region->to - $region->from))->toBe('typeclass');
        expect(substr($found->php, $region->from, $region->to - $region->from))->toBe('interface');
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
    // Valid PHP, and the bracket that closed the type belonged to the shift.
    // Read as notation it becomes `MIN<MAX>`, a type nobody wrote, and the
    // shift is quietly erased from a source that was correct.
    it("does not take a shift's bracket for the end of a type", function () use ($read, $types) {
        expect($types('$a = MIN < MAX >> 2;'))->toBe('');
        expect(count($read('$a = MIN < MAX >> 2;')->errors))->toBe(0);
    });

    // A bodyless class is all notation: PHP has no class that ends in a
    // semicolon. It stands in as spaces ending at its own semicolon, an empty
    // statement, and everything the compiler needs to write the real class
    // travels in the synthesis: the head, the parent, and the parameters that
    // become properties.
    it("reads a bodyless class with a primary constructor", function () {
        $source = '<?php final class Some<T>(T $value) extends Option<T>;';
        $found = (new Notation())->readFrom($source);

        expect(count($found->syntheses))->toBe(1);

        $synthesis = $found->syntheses[0];

        expect($synthesis->head)->toBe('final class Some');
        expect($synthesis->parent)->toBe('Option');
        expect(count($synthesis->parameters))->toBe(1);
        expect($synthesis->parameters[0]->name)->toBe('value');
        expect($synthesis->parameters[0]->phpType)->toBe(null);
        expect($found->php)->toBe('<?php' . str_repeat(' ', strlen($source) - strlen('<?php') - 1) . ';');
    });

    it("reads a bodyless class that only extends", function () {
        $found = (new Notation())->readFrom('<?php final class None extends Option;');

        expect(count($found->syntheses))->toBe(1);
        expect($found->syntheses[0]->head)->toBe('final class None');
        expect($found->syntheses[0]->parent)->toBe('Option');
        expect($found->syntheses[0]->parameters)->toBe([]);
    });

    it("keeps a concrete parameter type for PHP in a synthesis", function () {
        $found = (new Notation())->readFrom('<?php class Account(Balance $balance, AccountHolder $holder);');

        $parameters = $found->syntheses[0]->parameters;

        expect($parameters[0]->phpType)->toBe('Balance');
        expect($parameters[1]->phpType)->toBe('AccountHolder');
    });

    // Braces come back the moment there is something to put in them. The
    // constructor clause is still notation, so it blanks and its parameters
    // travel in the synthesis, but the class itself stays in the source: only
    // the clause region is the compiler's to rewrite, and bodyOpen says where
    // the generated members go.
    it("reads a primary constructor on a class that keeps a body", function () {
        $source = '<?php final class Some<T>(T $value) extends Option<T> { public function x() { return 1; } }';
        $found = (new Notation())->readFrom($source);

        expect(count($found->syntheses))->toBe(1);

        $synthesis = $found->syntheses[0];

        expect($synthesis->parameters[0]->name)->toBe('value');
        expect($synthesis->bodyOpen)->not()->toBe(null);
        expect($found->php)->toContain('final class Some')
            ->toContain('extends Option')
            ->toContain('public function x()')
            ->not()->toContain('$value)')
            ->not()->toContain('<T>');
        expect(substr($found->php, $synthesis->bodyOpen, 1))->toBe('{');

        // The whole point of a stand-in: PHP can read it.
        token_get_all($found->php, TOKEN_PARSE);
    });

    it("still declares the parameters a bodyless header binds", function () {
        $found = (new Notation())->readFrom('<?php final class Some<T>(T $value) extends Option<T>;');

        expect(count($found->headers))->toBe(1);
        expect($found->headers[0]->parameters[0]->name)->toBe('T');
    });

    // A block property is a method written as a value. The object it is
    // called through is never a parameter: the arms match $this, and any
    // parameters the block declares are the call's own arguments. None of it
    // is PHP, so the whole declaration stands in as spaces, and what the
    // compiler needs to write the method travels in the record: name,
    // parameters, and the arms as the reader wrote them.
    it("reads a block property that is only a match", function () use ($read) {
        $found = $read('class Option { public Block $get = {
            Some($v) => $v,
            None     => throw new RuntimeException("no")
        }; }');

        expect(count($found->blockMethods))->toBe(1);

        $method = $found->blockMethods[0];

        expect($method->name)->toBe('get');
        expect($method->parameters)->toBe([]);
        expect($method->arms)->toContain('Some($v) => $v');
        expect($found->php)->not()->toContain('$get');
    });

    // The initializer declares the type: a property whose default is a block
    // IS a Block, so writing the word is optional and the shape is the rule.
    it("reads a block property that never wrote the word Block", function () use ($read) {
        $found = $read('class Option { public $isEmpty = {
            Some($v) => false,
            None     => true
        }; }');

        expect(count($found->blockMethods))->toBe(1);
        expect($found->blockMethods[0]->name)->toBe('isEmpty');
        expect($found->php)->not()->toContain('$isEmpty');
    });

    it("reads a block property whose parameters are the call's own", function () use ($read) {
        $found = $read('class Option { public Block $getOrElse = { $default =>
            Some($v) => $v,
            None     => $default
        }; }');

        expect($found->blockMethods[0]->parameters)->toBe(['default']);
    });

    // A variadic is `...`, and a single `.` is concatenation. Reading one as
    // the other made `[(FOO) => BAR . 'x']` a callable type, and the compiler,
    // which removes what this says it found, took a working array apart.
    it("does not take concatenation for the variadic that may follow a type", function () use ($read, $types) {
        expect($types('$a = [(FOO) => BAR . "x"];'))->toBe('');
        expect(count($read('$a = [(FOO) => BAR . "x"];')->erasures))->toBe(0);
    });

    it("still reads a type that a variadic follows", function () use ($types) {
        expect($types('function f((int) => string ...$fs): int { return 1; }'))->toBe('(int) => string');
    });

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
