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
 * A place in a piece of notation, and the small questions a parser asks of it.
 *
 * It reads characters rather than PHP tokens, which is not the obvious choice
 * and is the right one here. PHP's lexer runs `>>` together into a single shift
 * token, so a parser built on it has to unpick that before a nested argument
 * can close, and `=>` and `_` arrive with meanings borrowed from a language
 * this notation is not. Reading characters, the grammar says what it means.
 */
final class Cursor
{
    private int $at = 0;

    private readonly int $length;

    /**
     * @param string $notation Text being read
     * @param int    $origin   Offset the text started at in the file it came from
     */
    public function __construct(
        private readonly string $notation,
        private readonly int $origin = 0,
    ) {
        $this->length = strlen($notation);
    }

    /**
     * Where the cursor stands, in the coordinates of the file the text came
     * from rather than of the fragment being read.
     *
     * @return int Byte offset
     */
    public function offset(): int
    {
        return $this->origin + $this->at;
    }

    /**
     * @return bool Whether there is nothing left to read
     */
    public function atEnd(): bool
    {
        return $this->at >= $this->length;
    }

    /**
     * @param string $text Text to look for
     *
     * @return bool Whether the cursor stands on it
     */
    public function looksAt(string $text): bool
    {
        return str_starts_with(substr($this->notation, $this->at, strlen($text)), $text);
    }

    /**
     * A hole is an underscore standing on its own. An underscore that starts a
     * name is a name, so `_internal` is a type and `_` is a gap.
     *
     * @return bool Whether a hole begins here
     */
    public function looksAtHole(): bool
    {
        return preg_match('/^_(?![A-Za-z0-9_\\x80-\\xff\\\\])/', substr($this->notation, $this->at)) === 1;
    }

    /**
     * Moves the cursor along.
     *
     * @param int $characters How far
     */
    public function take(int $characters): void
    {
        $this->at = min($this->length, $this->at + $characters);
    }

    public function skipSpace(): void
    {
        while ($this->at < $this->length && ctype_space($this->notation[$this->at])) {
            $this->at++;
        }
    }

    /**
     * Reads a name, or answers null where there is none.
     *
     * A bare `_` is a hole rather than a name, and is left for the caller.
     *
     * @return string|null The name, if one begins here
     */
    public function takeName(): ?string
    {
        if (preg_match('/^[A-Za-z_\\x80-\\xff\\\\][A-Za-z0-9_\\x80-\\xff\\\\]*/', substr($this->notation, $this->at), $found) !== 1) {
            return null;
        }

        $this->take(strlen($found[0]));

        return $found[0];
    }

    /**
     * Closes one argument group, splitting a run of `>` if it has to.
     *
     * `ImmList<Option<Int>>` ends in two brackets written as one shift, and
     * `ImmList<Option<ImmList<Int>>>` in three. Reading them one at a time is
     * the whole of what the lexer could not do, and here it is a subtraction.
     *
     * @return bool Whether a group closed here
     */
    public function closeGroup(): bool
    {
        if (!$this->looksAt('>')) {
            return false;
        }

        $this->take(1);

        return true;
    }

    /**
     * What is at the cursor, said the way a diagnostic would say it.
     *
     * @return string A description of what is here, quoted where it is text
     */
    public function describeHere(): string
    {
        if ($this->atEnd()) {
            return 'the end of the type';
        }

        preg_match('/^\S+/', substr($this->notation, $this->at), $found);

        return sprintf('"%s"', $found[0] ?? $this->notation[$this->at]);
    }

    /**
     * @return string Everything still unread
     */
    public function rest(): string
    {
        return substr($this->notation, $this->at);
    }
}
