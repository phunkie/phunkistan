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

use Phunkie\Stan\Source\Region;

/**
 * A class the compiler has to write, because the reader only declared it.
 *
 * `final class Some<T>(T $value) extends Option<T>;` is all notation: PHP has
 * no primary constructors and no class that ends in a semicolon. The grammar
 * reads it, and this carries what the compiler needs to write the class the
 * header meant: the head as written, the parent it extends, and the
 * parameters that become public readonly properties.
 *
 * In the stand-in the declaration is spaces ending at its own semicolon,
 * which is an empty statement, so offsets survive and nothing downstream
 * meets a class PHP could not parse.
 */
final class ClassSynthesis
{
    /**
     * @param string                    $head       Modifiers, keyword and name, as written
     * @param string|null               $parent     Parent the class extends, erased of arguments
     * @param list<SynthesisParameter>  $parameters What the primary constructor takes
     * @param Region                    $region     What the compiler rewrites: the whole
     *                                              declaration when there is no body, only
     *                                              the constructor clause when there is one
     * @param int|null                  $bodyOpen   Where the body's brace is, when braces
     *                                              came back: the generated members go just
     *                                              inside it. Null for the bodyless form.
     * @param list<string>              $derivings  Powers the deriving clause granted
     * @param Region|null               $derivingRegion Where the clause stood, for the
     *                                              braces-back form the compiler rewrites
     *                                              in place; inside the region for bodyless
     */
    public function __construct(
        public readonly string $head,
        public readonly ?string $parent,
        public readonly array $parameters,
        public readonly Region $region,
        public readonly ?int $bodyOpen = null,
        public readonly array $derivings = [],
        public readonly ?Region $derivingRegion = null,
    ) {
    }
}
