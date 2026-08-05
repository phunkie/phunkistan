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

namespace Phunkie\Stan\Check;

use PhpParser\Error;
use PhpParser\Parser;
use Phunkie\Stan\Diagnostic\Diagnostic;
use Phunkie\Stan\Diagnostic\Span;
use Phunkie\Stan\Source\OpenedSource;
use Phunkie\Stan\Source\OpeningTag;
use Phunkie\Stan\Source\Source;
use Phunkie\Stan\Type\Notation;
use Phunkie\Stan\Type\TypeSyntaxError;

/**
 * Reads a source and reports where it stopped making sense.
 *
 * This is the first thing that runs and, until the phunkie grammar lands, the
 * only thing: it can say a source is not PHP, but not yet that it is not
 * phunkie. A file carrying type arguments will fail here for now, which is
 * honest rather than useful, and is exactly the gap the grammar closes.
 */
final class SyntaxCheck
{
    public const CODE = 'E0001';
    public const NOTATION_CODE = 'E0003';
    public const CATEGORY = 'SYNTAX ERROR';

    /**
     * @param Parser     $parser     Parser deciding which PHP is accepted
     * @param OpeningTag $openingTag Opens a source that did not open itself
     * @param Notation   $notation   Reads phunkie's own notation out of the source
     */
    public function __construct(
        private readonly Parser $parser,
        private readonly OpeningTag $openingTag,
        private readonly Notation $notation = new Notation(),
    ) {
    }

    /**
     * Checks one source.
     *
     * @param Source $source Source to read and parse
     *
     * @return list<Diagnostic> One diagnostic where it failed, empty where it did not
     */
    public function on(Source $source): array
    {
        $code = $source->read();
        $opened = $this->openingTag->open($code);
        $read = $this->notation->readFrom($opened->text());

        // Notation that could not be read stops here. Handing the rest to PHP
        // as well would report the same mistake twice, once in phunkie's terms
        // and once in PHP's, and the second is the one nobody can act on.
        if ($read->hasErrors()) {
            return array_map(
                fn (TypeSyntaxError $error): Diagnostic => $this->notationDiagnostic($error, $source, $code, $opened),
                $read->errors
            );
        }

        try {
            $this->parser->parse($read->php);
        } catch (Error $error) {
            return [$this->diagnose($error, $source, $code, $opened)];
        }

        return [];
    }

    /**
     * A type expression the grammar could not read.
     */
    private function notationDiagnostic(TypeSyntaxError $error, Source $source, string $code, OpenedSource $opened): Diagnostic
    {
        $position = $opened->positionOf($error->offset);

        return new Diagnostic(
            self::NOTATION_CODE,
            self::CATEGORY,
            $error->getMessage(),
            new Span($source->relativePath, $position->line, $position->column),
            $code
        );
    }

    /**
     * Turns a parser's complaint into a position the reader recognises.
     *
     * The offset is asked for rather than the line and column, because it is
     * the one number both are derived from and the only one that survives a
     * tag being inserted without needing a rule of its own.
     */
    private function diagnose(Error $error, Source $source, string $code, OpenedSource $opened): Diagnostic
    {
        return new Diagnostic(
            self::CODE,
            self::CATEGORY,
            $error->getRawMessage(),
            new Span($source->relativePath, ...$this->placeOf($error, $opened)),
            $code
        );
    }

    /**
     * Where the parser says it stopped.
     *
     * An error that kept no offset still knows its line, and a whole line is a
     * better answer than a confident wrong column.
     *
     * @return array{int, int}
     */
    private function placeOf(Error $error, OpenedSource $opened): array
    {
        $attributes = $error->getAttributes();
        $offset = $attributes['startFilePos'] ?? -1;

        if (!is_int($offset) || $offset < 0) {
            return [max(1, $error->getStartLine()), 1];
        }

        $position = $opened->positionOf($offset);

        return [$position->line, $position->column];
    }
}
