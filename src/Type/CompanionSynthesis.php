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
 * A constructor function the compiler has to write, because the class asked
 * with `#[Companion]`.
 *
 * The attribute is legal PHP and generation is the compiler's, so the reader
 * only reports what was declared: the class, the primary constructor's
 * parameters for the mirror, and the flags. On a sealed head the attribute
 * carries a recipe by name (`variadic: [NonEmptyList, Nil]`,
 * `nullable: [Some, None]`), because per-file reading cannot see the cases'
 * own constructors; a recipe body only ever calls the cases' companions, so
 * names are all it needs.
 */
final class CompanionSynthesis
{
    /**
     * @param string                    $class         Class the companion constructs, as written
     * @param list<SynthesisParameter>  $parameters    What the mirror function takes
     * @param bool                      $singleton     One shared instance answers every call
     * @param bool                      $withArguments Whether the function takes the constructor's
     *                                                 arguments; false on a singleton also makes
     *                                                 the bare name the value
     * @param list<string>|null         $variadic      Cons case and empty case, for a head whose
     *                                                 companion folds its arguments into a chain
     * @param list<string>|null         $nullable      Wrapping case and empty case, for a head
     *                                                 whose companion sends null to the empty case
     */
    public function __construct(
        public readonly string $class,
        public readonly array $parameters = [],
        public readonly bool $singleton = false,
        public readonly bool $withArguments = true,
        public readonly ?array $variadic = null,
        public readonly ?array $nullable = null,
    ) {
    }
}
