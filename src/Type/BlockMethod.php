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
 * A method written as a value: a block hanging on a class as a property.
 *
 * `public Block $get = { arms };` is all notation. The object the block is
 * called through is never a parameter: the arms match $this, and any
 * parameters the block declares, `{ $default => arms }`, are the call's own
 * arguments. Omitting them is the common case.
 *
 * The declaration stands in as spaces, and the compiler writes the method it
 * meant from what is carried here: the name the property had, the parameters
 * as written, and the arms exactly as the reader wrote them, still in
 * phunkie, because the match is somebody else's job.
 */
final class BlockMethod
{
    /**
     * @param string       $name       Method name, the property's without its dollar
     * @param list<string> $parameters The call's own parameters, dollars stripped
     * @param string       $arms       The body, verbatim: arms when the kind is
     *                                 match, otherwise the expression or statements
     * @param Region       $region     The whole declaration, semicolon included
     * @param string       $kind       What the body is: "match" arms, one
     *                                 "expression" to answer, or "statements" to run
     */
    public function __construct(
        public readonly string $name,
        public readonly array $parameters,
        public readonly string $arms,
        public readonly Region $region,
        public readonly string $kind = 'match',
    ) {
    }
}
