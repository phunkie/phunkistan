<?php

/*
 * This file is part of Phunkistan, a type checker for phunkie.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Phunkie\Stan\Type;

use Phunkie\Stan\Source\Region;

/**
 * Reads phunkie's type notation.
 *
 * This is the grammar the language did not have. Without it the compiler's
 * answer to notation it does not recognise is to pass it through, so a gap in
 * its coverage and ordinary PHP going by look exactly alike, and what comes out
 * is PHP that will not parse.
 *
 * The grammar it accepts, in full:
 *
 *     type      := callable | name typeArgs? | hole
 *     callable  := '(' (type (',' type)*)? ')' '=>' type
 *     typeArgs  := '<' type (',' type)* '>'
 *     hole      := '_'
 *     name      := an identifier, or one of PHP's own type keywords
 *
 * Angle brackets are safe to read this way whatever else is in the file,
 * because PHP 8 made comparison non-associative: `$a < $b > $c` is a parse
 * error rather than an expression, so `Name<Arg>` can never be a comparison
 * somebody meant.
 */
final class TypeParser
{
    /**
     * Characters that end a type when read at the top level, so that a type
     * followed by a variable or a body stops where it should.
     */
    private const STOPS = ['$', '{', ';', ')', ',', '>', '='];

    /**
     * Reads a whole notation, which must be a type and nothing else.
     *
     * @param string $notation Type notation, exactly as it was written
     *
     * @throws TypeSyntaxError If the notation is not a type the grammar knows
     *
     * @return Type What it says
     */
    public function parse(string $notation): Type
    {
        $cursor = new Cursor($notation);
        $type = $this->type($cursor);

        $cursor->skipSpace();

        if (!$cursor->atEnd()) {
            throw new TypeSyntaxError(
                sprintf('Expected the type to end, found "%s".', $cursor->rest()),
                $cursor->offset()
            );
        }

        return $type;
    }

    /**
     * Reads a type starting where the cursor stands, and leaves the cursor
     * immediately after it.
     *
     * @throws TypeSyntaxError If there is no type here
     */
    public function type(Cursor $cursor): Type
    {
        $cursor->skipSpace();
        $from = $cursor->offset();

        if ($cursor->looksAt('(')) {
            return $this->callable($cursor, $from);
        }

        if ($cursor->looksAtHole()) {
            $cursor->take(1);

            return new Hole(new Region($from, $cursor->offset()));
        }

        $name = $cursor->takeName();

        if ($name === null) {
            throw new TypeSyntaxError(
                sprintf('Expected a type, found %s.', $cursor->describeHere()),
                $cursor->offset()
            );
        }

        // The name is its own node with its own region, because that is what a
        // diagnostic about an unknown type underlines. Underlining the whole
        // application would point at the arguments too, which are not what is
        // wrong with `ImmList<Itn>`.
        $constructor = new TypeNameUse($name, new Region($from, $cursor->offset()));
        $arguments = $this->arguments($cursor);

        if ($arguments === []) {
            return $constructor;
        }

        return new TypeApplication($constructor, $arguments, new Region($from, $cursor->offset()));
    }

    /**
     * Reads a declaration's type parameter clause.
     *
     * These are binders, not uses. `class Stack<Itn>` introduces a parameter
     * unfortunately spelled `Itn` and is entirely legal, so nothing here is
     * looked up and nothing here may be reported as unknown.
     *
     * A binder's own brackets state its arity: `F<_>` says F takes one argument
     * of its own, which is what lets `Functor<F<_>>` mean something for every F
     * that has a map.
     *
     * @param Cursor $cursor Standing on the opening bracket
     *
     * @throws TypeSyntaxError If the clause is not a list of names
     *
     * @return list<TypeParameterDeclaration> The parameters it introduces
     */
    public function parameters(Cursor $cursor): array
    {
        $cursor->skipSpace();

        if (!$cursor->looksAt('<')) {
            return [];
        }

        $cursor->take(1);
        $parameters = [];

        while (true) {
            $cursor->skipSpace();
            $from = $cursor->offset();
            $name = $cursor->takeName();

            if ($name === null) {
                throw new TypeSyntaxError(
                    sprintf('Expected the name of a type parameter, found %s.', $cursor->describeHere()),
                    $cursor->offset()
                );
            }

            $parameters[] = new TypeParameterDeclaration($name, $this->arity($cursor), new Region($from, $cursor->offset()));
            $cursor->skipSpace();

            if ($cursor->closeGroup()) {
                return $parameters;
            }

            if (!$cursor->looksAt(',')) {
                throw new TypeSyntaxError(
                    sprintf('Expected "," or ">" between type parameters, found %s.', $cursor->describeHere()),
                    $cursor->offset()
                );
            }

            $cursor->take(1);
        }
    }

    /**
     * How many arguments a parameter takes itself, read from its own holes.
     *
     * @throws TypeSyntaxError
     */
    private function arity(Cursor $cursor): int
    {
        if (!$cursor->looksAt('<')) {
            return 0;
        }

        $cursor->take(1);
        $arity = 0;

        while (true) {
            $cursor->skipSpace();

            if (!$cursor->looksAtHole()) {
                throw new TypeSyntaxError(
                    sprintf('Expected "_" to say what shape this parameter takes, found %s.', $cursor->describeHere()),
                    $cursor->offset()
                );
            }

            $cursor->take(1);
            $arity++;
            $cursor->skipSpace();

            if ($cursor->closeGroup()) {
                return $arity;
            }

            if (!$cursor->looksAt(',')) {
                throw new TypeSyntaxError(
                    sprintf('Expected "," or ">" between holes, found %s.', $cursor->describeHere()),
                    $cursor->offset()
                );
            }

            $cursor->take(1);
        }
    }

    /**
     * Reads a `<...>` group, or nothing where there is none.
     *
     * @throws TypeSyntaxError
     *
     * @return list<Type>
     */
    private function arguments(Cursor $cursor): array
    {
        // The space is skipped here rather than by whatever found this, so one
        // object decides whether `ImmList <Int>` has arguments. Two answering
        // differently is how a type gets read as a bare name and its arguments
        // leak through to PHP.
        $cursor->skipSpace();

        if (!$cursor->looksAt('<')) {
            return [];
        }

        $opened = $cursor->offset();
        $cursor->take(1);
        $cursor->skipSpace();

        if ($cursor->looksAt('>')) {
            throw new TypeSyntaxError('Expected a type argument, found an empty group.', $cursor->offset());
        }

        $arguments = [$this->type($cursor)];

        while (true) {
            $cursor->skipSpace();

            if ($cursor->closeGroup()) {
                return $arguments;
            }

            if (!$cursor->looksAt(',')) {
                throw new TypeSyntaxError($this->unclosed($cursor, $opened), $cursor->offset());
            }

            $cursor->take(1);
            $arguments[] = $this->type($cursor);
        }
    }

    /**
     * Says the more useful of two things: that an argument list ran out before
     * it closed, or that two arguments were written with nothing between them.
     */
    private function unclosed(Cursor $cursor, int $opened): string
    {
        if ($cursor->atEnd()) {
            return sprintf('Expected "%s" to close, and the type ended first.', '<');
        }

        return sprintf('Expected "," or ">" between type arguments, found %s.', $cursor->describeHere());
    }

    /**
     * @throws TypeSyntaxError
     */
    private function callable(Cursor $cursor, int $from): CallableType
    {
        $cursor->take(1);
        $cursor->skipSpace();
        $parameters = [];

        if (!$cursor->looksAt(')')) {
            $parameters[] = $this->type($cursor);
            $cursor->skipSpace();

            while ($cursor->looksAt(',')) {
                $cursor->take(1);
                $parameters[] = $this->type($cursor);
                $cursor->skipSpace();
            }
        }

        if (!$cursor->looksAt(')')) {
            throw new TypeSyntaxError(
                sprintf('Expected ")" to close the parameters, found %s.', $cursor->describeHere()),
                $cursor->offset()
            );
        }

        $cursor->take(1);
        $cursor->skipSpace();

        if (!$cursor->looksAt('=>')) {
            throw new TypeSyntaxError(
                sprintf('Expected "=>" after the parameters, found %s.', $cursor->describeHere()),
                $cursor->offset()
            );
        }

        $cursor->take(2);
        $returns = $this->type($cursor);

        // The shape ends where its return type ends, not where the cursor
        // stopped. Reading a type skips the space that follows it before asking
        // whether arguments come next, so the cursor is already past the end of
        // what was written by the time this can ask.
        return new CallableType($parameters, $returns, new Region($from, $returns->region()->to));
    }

    /**
     * Whether a type read at the top level stops here.
     *
     * @return list<string>
     */
    public function stops(): array
    {
        return self::STOPS;
    }
}
