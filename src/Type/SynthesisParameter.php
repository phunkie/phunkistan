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
 * One parameter of a primary constructor, on its way to becoming a property.
 *
 * The type as written is a Type node like any other; what travels here is the
 * part the compiler writes into PHP: the name the property takes, and the
 * type PHP can still enforce, null where the written type was a variable or a
 * shape PHP has no word for.
 */
final class SynthesisParameter
{
    /**
     * @param string      $name    Property and parameter name, without its dollar
     * @param string|null $phpType Type PHP can enforce, null where it cannot
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $phpType,
    ) {
    }
}
