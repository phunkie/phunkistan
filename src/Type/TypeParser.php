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

        if ($cursor->looksAt('(')) {
            return $this->callable($cursor);
        }

        if ($cursor->looksAtHole()) {
            $cursor->take(1);

            return new Hole();
        }

        $name = $cursor->takeName();

        if ($name === null) {
            throw new TypeSyntaxError(
                sprintf('Expected a type, found %s.', $cursor->describeHere()),
                $cursor->offset()
            );
        }

        return new TypeName($name, $this->arguments($cursor));
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
    private function callable(Cursor $cursor): CallableType
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

        return new CallableType($parameters, $this->type($cursor));
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
