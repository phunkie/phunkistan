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

use RuntimeException;

/**
 * Notation the grammar could not read, and where it gave up.
 *
 * The offset is into the source the parser was given, so it can be turned into
 * a place the reader recognises without anything in between having to know how
 * the notation was found.
 */
final class TypeSyntaxError extends RuntimeException
{
    /**
     * @param string $message What was expected, in the reader's terms
     * @param int    $offset  Byte offset the parser stopped at
     */
    public function __construct(
        string $message,
        public readonly int $offset,
    ) {
        parent::__construct($message);
    }
}
