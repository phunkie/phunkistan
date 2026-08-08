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

describe("Companion notation", function () {
    $read = fn (string $php) => (new Notation())->readFrom('<?php ' . $php);

    it("reads a bare attribute as the mirror of the primary constructor", function () use ($read) {
        $found = $read("#[Companion]\nfinal class Some<T>(T \$value) extends Option<T>;");

        expect(count($found->companions))->toBe(1);
        $companion = $found->companions[0];
        expect($companion->class)->toBe('Some');
        expect($companion->withArguments)->toBe(true);
        expect($companion->singleton)->toBe(false);
        expect(count($companion->parameters))->toBe(1);
        expect($companion->parameters[0]->name)->toBe('value');
        expect($companion->parameters[0]->phpType)->toBe(null);
    });

    // The braces-back form's synthesis region is only the constructor clause,
    // which starts after the class name; the attribute still has to find it.
    it("finds the parameters when the braces came back", function () use ($read) {
        $found = $read("#[Companion]\nfinal class Some<T>(T \$value) extends Option<T>\n{\n    public function isDefined(): bool\n    {\n        return true;\n    }\n}");

        expect(count($found->companions[0]->parameters))->toBe(1);
        expect($found->companions[0]->parameters[0]->name)->toBe('value');
    });

    it("keeps the PHP type a parameter promised", function () use ($read) {
        $found = $read("#[Companion]\nfinal class Account(Balance \$balance);");

        expect($found->companions[0]->parameters[0]->phpType)->toBe('Balance');
    });

    it("reads a singleton whose bare name is also the value", function () use ($read) {
        $found = $read("#[Companion(singleton: true, withArguments: false)]\nfinal class None extends Option;");

        $companion = $found->companions[0];
        expect($companion->class)->toBe('None');
        expect($companion->singleton)->toBe(true);
        expect($companion->withArguments)->toBe(false);
        expect($companion->parameters)->toBe([]);
    });

    it("reads the variadic recipe on a sealed head by name alone", function () use ($read) {
        $found = $read("#[Companion(variadic: [NonEmptyList, Nil])]\nabstract class ImmList<T> implements Monad\n{\n}");

        $companion = $found->companions[0];
        expect($companion->class)->toBe('ImmList');
        expect($companion->variadic)->toBe(['NonEmptyList', 'Nil']);
        expect($companion->parameters)->toBe([]);
    });

    it("reads the nullable recipe beside another attribute", function () use ($read) {
        $found = $read("#[Sealed(classes: [Some, None])]\n#[Companion(nullable: [Some, None])]\nabstract class Option<T>\n{\n}");

        $companion = $found->companions[0];
        expect($companion->class)->toBe('Option');
        expect($companion->nullable)->toBe(['Some', 'None']);
    });

    // A class may have more than one companion, and a companion may carry a
    // name of its own: Nel is the variadic face of NonEmptyList.
    it("reads a second, named companion on the same class", function () use ($read) {
        $found = $read("#[Companion]\n#[Companion(named: Nel, variadic: [NonEmptyList, Nil])]\nfinal class NonEmptyList<T>(T \$head, ImmList<T> \$tail) extends ImmList<T>;");

        expect(count($found->companions))->toBe(2);
        expect($found->companions[0]->named)->toBe(null);
        expect($found->companions[1]->named)->toBe('Nel');
        expect($found->companions[1]->class)->toBe('NonEmptyList');
        expect($found->companions[1]->variadic)->toBe(['NonEmptyList', 'Nil']);
    });

    // The attribute is legal PHP and generation is the compiler's; the reader
    // only reports it, so every byte keeps its offset and the attribute rides
    // through to the output as its own documentation.
    it("leaves the attribute standing at its own offsets", function () use ($read) {
        $php = "#[Companion(singleton: true, withArguments: false)]\nfinal class None extends Option;";
        $found = $read($php);

        expect(strlen($found->php))->toBe(strlen('<?php ' . $php));
        expect($found->php)->toContain('#[Companion(singleton: true, withArguments: false)]');
    });

    it("finds nothing where nothing is declared", function () use ($read) {
        expect($read('final class Plain {}')->companions)->toBe([]);
    });

    it("refuses a recipe that does not name both cases", function () use ($read) {
        $found = $read("#[Companion(variadic: [NonEmptyList])]\nabstract class ImmList<T>\n{\n}");

        expect($found->companions)->toBe([]);
        expect(count($found->errors))->toBe(1);
        expect($found->errors[0]->getMessage())->toContain('two');
    });
});
