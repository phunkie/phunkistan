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
 * A deriving clause on a head with no primary constructor.
 *
 * A synthesis carries its own clause; this record is for the class that is
 * otherwise plain PHP: whose clause it is, what it grants, whether an
 * implements is already on the head to join, and where the body opens so
 * the compiler knows where the powers' methods go. The region is the
 * clause itself, blanked in the stand-in and the compiler's to rewrite.
 */
final class DerivingSynthesis
{
    /**
     * @param string       $class           Class the clause is on, as written
     * @param list<string> $powers          What is derived, in written order
     * @param Region       $region          The clause, from keyword to last power
     * @param bool         $joinsImplements Whether the head already has an implements
     * @param int          $bodyOpen        Where the body's brace is
     */
    public function __construct(
        public readonly string $class,
        public readonly array $powers,
        public readonly Region $region,
        public readonly bool $joinsImplements,
        public readonly int $bodyOpen,
    ) {
    }
}
