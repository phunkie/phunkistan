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
use PhpParser\ParserFactory;
use Phunkie\Stan\Diagnostic\Diagnostic;
use Phunkie\Stan\Diagnostic\Span;
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

    private readonly Parser $parser;

    private readonly OpeningTag $openingTag;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->openingTag = new OpeningTag();
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
        $tagged = $this->openingTag->ensure($code);

        try {
            $this->parser->parse($tagged);
        } catch (Error $error) {
            return [$this->diagnose($error, $source, $code, $tagged, $this->openingTag->columnOffsetIn($code))];
        }

        return [];
    }

    /**
     * Turns a parser's complaint into a position the reader recognises.
     *
     * Opening the tag pushed the first line sideways and no line down, so that
     * is the whole of the correction: a column on line one, and nothing else.
     */
    private function diagnose(Error $error, Source $source, string $code, string $tagged, int $columnOffset): Diagnostic
    {
        $line = $error->getStartLine();
        $column = $error->hasColumnInfo() ? $error->getStartColumn($tagged) : 1;

        return new Diagnostic(
            self::CODE,
            self::CATEGORY,
            $error->getRawMessage(),
            new Span($source->relativePath, $line, max(1, $column - ($line === 1 ? $columnOffset : 0))),
            $code
        );
    }
}
