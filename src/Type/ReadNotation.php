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
 * What reading a source's notation found.
 */
final class ReadNotation
{
    /**
     * @param list<Type>            $types   Every type expression that was understood
     * @param list<TypeSyntaxError> $errors  Notation that could not be read, with offsets
     * @param string                $php     The source with its notation blanked out
     */
    public function __construct(
        public readonly array $types,
        public readonly array $errors,
        public readonly string $php,
    ) {
    }

    /**
     * @return bool Whether anything could not be read
     */
    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
