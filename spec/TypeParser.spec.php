<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Phunkie\Stan\Type\Cursor;
use Phunkie\Stan\Type\TypeParser;
use Phunkie\Stan\Type\TypeSyntaxError;

describe("TypeParser", function () {
    // Parsed and printed back, because a type that survives the round trip is
    // one the parser understood rather than merely accepted. It is also what
    // diagnostics print, so it has to read like what was typed.
    $reads = fn (string $notation): string => (string) (new TypeParser())->parse($notation);

    it("reads a bare name", function () use ($reads) {
        expect($reads('Int'))->toBe('Int');
    });

    it("reads one type argument", function () use ($reads) {
        expect($reads('ImmList<Int>'))->toBe('ImmList<Int>');
    });

    it("reads several", function () use ($reads) {
        expect($reads('ImmMap<String, Int>'))->toBe('ImmMap<String, Int>');
    });

    // The lexer runs `>>` together into one shift token, so a nested group
    // closes on something that is not two brackets until it is split.
    it("reads a nested argument closing on a shift", function () use ($reads) {
        expect($reads('ImmList<Option<Int>>'))->toBe('ImmList<Option<Int>>');
    });

    it("reads three deep, closing on a longer shift", function () use ($reads) {
        expect($reads('ImmList<Option<ImmList<Int>>>'))->toBe('ImmList<Option<ImmList<Int>>>');
    });

    it("reads an array by what it holds", function () use ($reads) {
        expect($reads('array<User>'))->toBe('array<User>');
        expect($reads('array<string, string>'))->toBe('array<string, string>');
    });

    it("reads a function's shape", function () use ($reads) {
        expect($reads('(string) => bool'))->toBe('(string) => bool');
        expect($reads('(string, int) => resource'))->toBe('(string, int) => resource');
    });

    it("reads a function that takes nothing", function () use ($reads) {
        expect($reads('() => string'))->toBe('() => string');
    });

    // Without the parentheses on the left there is nothing to say whether the
    // first arrow belongs to the parameter or to the whole thing, and the two
    // readings are different types.
    it("reads a function that takes and answers with functions", function () use ($reads) {
        expect($reads('((int) => int) => (int) => int'))->toBe('((int) => int) => (int) => int');
    });

    it("reads a hole", function () use ($reads) {
        expect($reads('F<_>'))->toBe('F<_>');
    });

    it("reads a callable inside a type argument", function () use ($reads) {
        expect($reads('ImmList<(Int) => String>'))->toBe('ImmList<(Int) => String>');
    });

    it("refuses two arguments with nothing between them", function () {
        expect(fn () => (new TypeParser())->parse('array<int User>'))->toThrow(TypeSyntaxError::class);
    });

    it("refuses a group that never closes", function () {
        expect(fn () => (new TypeParser())->parse('ImmList<Int'))->toThrow(TypeSyntaxError::class);
    });

    it("refuses a group with nothing in it", function () {
        expect(fn () => (new TypeParser())->parse('ImmList<>'))->toThrow(TypeSyntaxError::class);
    });

    it("refuses a function that answers with nothing", function () {
        expect(fn () => (new TypeParser())->parse('(string) =>'))->toThrow(TypeSyntaxError::class);
    });

    // A region is where a type was written, not where the cursor stopped
    // reading it. Reading a return type skips whatever follows it before
    // asking for arguments, so a function's shape claimed the space in front
    // of the variable it declared, and underlining it pointed one past itself.
    it("ends a function's shape where the shape ends", function () {
        $type = (new TypeParser())->type(new Cursor('(int) => string $f'));

        expect($type->region()->to)->toBe(strlen('(int) => string'));
    });

    it("says where it gave up", function () {
        try {
            (new TypeParser())->parse('array<int User>');
        } catch (TypeSyntaxError $error) {
            expect($error->offset)->toBe(10);

            return;
        }

        throw new RuntimeException('The parser accepted notation it should have refused.');
    });
});
