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
    public const CATEGORY = 'SYNTAX ERROR';

    /**
     * @param Parser     $parser     Parser deciding which PHP is accepted
     * @param OpeningTag $openingTag Opens a source that did not open itself
     */
    public function __construct(
        private readonly Parser $parser,
        private readonly OpeningTag $openingTag,
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

        try {
            $this->parser->parse($opened->text());
        } catch (Error $error) {
            return [$this->diagnose($error, $source, $code, $opened)];
        }

        return [];
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
