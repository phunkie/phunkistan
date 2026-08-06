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
final class SyntaxCheck implements Check
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

        // One parse decides everything. PHP is asked about the source with the
        // notation it could read taken out, so where it gives up is directly
        // comparable with where the grammar gave up.
        try {
            $this->parser->parse($read->php);
        } catch (Error $error) {
            return [$this->blame($error, $read->errors, $source, $code, $opened)];
        }

        // PHP is content, so nothing that failed to read as a type ever was
        // one: `MAX < 3` is a comparison. Asking per suspect rather than per
        // file is what stops a mistake on one line being blamed on a
        // comparison on another.
        return [];
    }

    /**
     * Decides whose complaint to report, phunkie's or PHP's.
     *
     * @param list<TypeSyntaxError> $suspects
     */
    private function blame(Error $error, array $suspects, Source $source, string $code, OpenedSource $opened): Diagnostic
    {
        $offset = $this->offsetOf($error);

        foreach ($suspects as $suspect) {
            if ($offset === null || $suspect->covers($offset)) {
                return $this->notationDiagnostic($suspect, $source, $code, $opened);
            }
        }

        return $this->diagnose($error, $source, $code, $opened);
    }

    private function offsetOf(Error $error): ?int
    {
        $offset = $error->getAttributes()['startFilePos'] ?? -1;

        return is_int($offset) && $offset >= 0 ? $offset : null;
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
        $offset = $this->offsetOf($error);

        if ($offset === null) {
            return [max(1, $error->getStartLine()), 1];
        }

        $position = $opened->positionOf($offset);

        return [$position->line, $position->column];
    }
}
