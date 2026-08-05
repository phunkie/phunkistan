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

namespace Phunkie\Stan\Diagnostic;

use JsonException;

/**
 * Writes diagnostics for an editor.
 *
 * The shape is the Language Server Protocol's `Diagnostic`, so an extension can
 * hand it straight to a client without translating. That protocol counts lines
 * and characters from zero where a reader counts from one, and the conversion
 * happens here rather than anywhere earlier: a `Span` means what a person
 * means by it, everywhere else in this codebase.
 */
final class JsonRenderer
{
    private const SOURCE = 'phunkistan';

    private const SEVERITY_ERROR = 1;

    /**
     * Renders every diagnostic as one LSP `Diagnostic`.
     *
     * @param list<Diagnostic> $diagnostics Diagnostics to write
     *
     * @throws JsonException If a diagnostic holds text that is not valid UTF-8
     *
     * @return string A JSON array, `[]` when there is nothing to say
     */
    public function render(array $diagnostics): string
    {
        return json_encode(
            array_map($this->one(...), $diagnostics),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function one(Diagnostic $diagnostic): array
    {
        $line = $diagnostic->span->line - 1;
        $character = $diagnostic->span->column - 1;

        return [
            'uri' => $diagnostic->span->file,
            'range' => [
                'start' => ['line' => $line, 'character' => $character],
                'end' => ['line' => $line, 'character' => $character],
            ],
            'severity' => self::SEVERITY_ERROR,
            'code' => $diagnostic->code,
            'source' => self::SOURCE,
            'message' => $diagnostic->headline,
        ];
    }
}
