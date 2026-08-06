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

use PhpParser\Node;
use Phunkie\Stan\Source\Region;

/**
 * Puts a type that was read onto the PHP node it was written on.
 *
 * The notation is blanked to spaces of the same length before PHP sees the
 * source, so every offset survives and a type's region lands exactly on the
 * node whose declaration it belonged to. Nothing has to be matched by name or
 * by counting, which is what makes this a lookup rather than a heuristic.
 *
 * It attaches rather than replaces, deliberately, and the types know nothing
 * about nikic. A type that implemented `PhpParser\Node` would be a type that
 * could not outlive it, and replacing a node is also what makes a
 * format-preserving printer lay the whole declaration out afresh.
 */
final class Attaching
{
    public const ATTRIBUTE = 'phunkieType';

    /**
     * Attaches each type to the smallest node that contains it.
     *
     * The smallest, because a `Param` sits inside a `ClassMethod` which sits
     * inside a `Class_`, and all three contain the region. Only the innermost
     * one is what the type was written on.
     *
     * @param list<Node> $nodes A parsed statement tree
     * @param list<Type> $types Types read from the same source
     *
     * @return int How many were placed, which is fewer than were read when a
     *             type sits somewhere PHP kept no node for
     */
    public function attach(array $nodes, array $types): int
    {
        $placed = 0;

        foreach ($types as $type) {
            $owner = $this->smallestContaining($nodes, $type->region());

            if ($owner === null) {
                continue;
            }

            $owner->setAttribute(self::ATTRIBUTE, $type);
            $placed++;
        }

        return $placed;
    }

    /**
     * @param list<Node> $nodes
     */
    private function smallestContaining(array $nodes, Region $region): ?Node
    {
        $smallest = null;

        foreach ($nodes as $node) {
            if (!$this->contains($node, $region)) {
                continue;
            }

            $inside = $this->smallestContaining($this->childrenOf($node), $region);
            $found = $inside ?? $node;

            if ($smallest === null || $this->widthOf($found) < $this->widthOf($smallest)) {
                $smallest = $found;
            }
        }

        return $smallest;
    }

    private function contains(Node $node, Region $region): bool
    {
        $from = $node->getStartFilePos();
        $to = $node->getEndFilePos();

        return $from >= 0 && $to >= 0 && $from <= $region->from && $to >= $region->to - 1;
    }

    private function widthOf(Node $node): int
    {
        return $node->getEndFilePos() - $node->getStartFilePos();
    }

    /**
     * @return list<Node>
     */
    private function childrenOf(Node $node): array
    {
        $children = [];

        foreach ($node->getSubNodeNames() as $name) {
            $value = $node->{$name};

            foreach (is_array($value) ? $value : [$value] as $child) {
                if ($child instanceof Node) {
                    $children[] = $child;
                }
            }
        }

        return $children;
    }
}
