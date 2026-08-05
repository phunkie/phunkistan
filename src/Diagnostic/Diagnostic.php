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

/**
 * One thing that is wrong, and everything needed to say so.
 *
 * The shape is deliberately close to miette's `Diagnostic`, because the same
 * object has to serve a terminal, an editor over LSP, and a CI baseline. A
 * renderer decides how much of it to show; none of them decide what it means.
 *
 * The headline states the rule that was broken rather than the state the
 * checker was in when it noticed. "Every step of a for-comprehension binds in
 * the same monad" teaches; "unexpected type in flatMap" does not.
 */
final class Diagnostic
{
    /**
     * @param string $code     Stable identifier, `E0001`, never reused once published
     * @param string $category One of a closed vocabulary, shown in the banner
     * @param string $headline One sentence, present tense, stating the rule
     * @param Span   $span     Smallest position that triggered it
     * @param string $source   The source the span points into, verbatim
     */
    public function __construct(
        public readonly string $code,
        public readonly string $category,
        public readonly string $headline,
        public readonly Span $span,
        public readonly string $source = '',
    ) {
    }
}
