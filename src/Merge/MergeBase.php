<?php

declare(strict_types=1);

namespace Pitmaster\Merge;

use Pitmaster\Object\Commit;
use Pitmaster\Object\ObjectId;
use Pitmaster\Storage\ObjectDatabase;

/**
 * Find the lowest common ancestor(s) of two commits in the commit graph.
 *
 * Uses a BFS-based algorithm: walk backwards from both commits simultaneously,
 * the first commit seen from both sides is the merge base.
 */
final class MergeBase
{
    public function __construct(private readonly ObjectDatabase $objects)
    {
    }

    /**
     * Find the merge base (LCA) of two commits.
     */
    public function find(ObjectId $a, ObjectId $b): ?ObjectId
    {
        if ($a->equals($b)) {
            return $a;
        }

        $ancestorsA = [];
        $ancestorsB = [];
        $queueA = [$a->hex];
        $queueB = [$b->hex];

        while ($queueA !== [] || $queueB !== []) {
            // Expand from A
            if ($queueA !== []) {
                $hex = array_shift($queueA);

                if (isset($ancestorsB[$hex])) {
                    return ObjectId::fromHex($hex);
                }

                $ancestorsA[$hex] = true;
                $commit = $this->objects->read(ObjectId::fromHex($hex));

                if ($commit instanceof Commit) {
                    foreach ($commit->parents as $parent) {
                        if (!isset($ancestorsA[$parent->hex])) {
                            $queueA[] = $parent->hex;
                        }
                    }
                }
            }

            // Expand from B
            if ($queueB !== []) {
                $hex = array_shift($queueB);

                if (isset($ancestorsA[$hex])) {
                    return ObjectId::fromHex($hex);
                }

                $ancestorsB[$hex] = true;
                $commit = $this->objects->read(ObjectId::fromHex($hex));

                if ($commit instanceof Commit) {
                    foreach ($commit->parents as $parent) {
                        if (!isset($ancestorsB[$parent->hex])) {
                            $queueB[] = $parent->hex;
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Find all lowest common ancestors for two commits.
     *
     * @return array<int, ObjectId>
     */
    public function findAll(ObjectId $a, ObjectId $b): array
    {
        if ($a->equals($b)) {
            return [$a];
        }

        $ancestorsA = $this->collectAncestors($a);
        $ancestorsB = $this->collectAncestors($b);
        $commonHexes = array_keys(array_intersect_key($ancestorsA, $ancestorsB));
        $lowest = [];

        foreach ($commonHexes as $hex) {
            $candidate = ObjectId::fromHex($hex);
            $isLowest = true;

            foreach ($commonHexes as $otherHex) {
                if ($hex === $otherHex) {
                    continue;
                }

                if ($this->isAncestor($candidate, ObjectId::fromHex($otherHex))) {
                    $isLowest = false;
                    break;
                }
            }

            if ($isLowest) {
                $lowest[] = $candidate;
            }
        }

        usort($lowest, static fn (ObjectId $left, ObjectId $right): int => strcmp($left->hex, $right->hex));

        return $lowest;
    }

    /**
     * Check if A is an ancestor of B (for fast-forward detection).
     */
    public function isAncestor(ObjectId $a, ObjectId $b): bool
    {
        if ($a->equals($b)) {
            return true;
        }

        $visited = [];
        $queue = [$b->hex];

        while ($queue !== []) {
            $hex = array_shift($queue);

            if ($hex === $a->hex) {
                return true;
            }

            if (isset($visited[$hex])) {
                continue;
            }

            $visited[$hex] = true;
            $commit = $this->objects->read(ObjectId::fromHex($hex));

            if ($commit instanceof Commit) {
                foreach ($commit->parents as $parent) {
                    if (!isset($visited[$parent->hex])) {
                        $queue[] = $parent->hex;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @return array<string, true>
     */
    private function collectAncestors(ObjectId $start): array
    {
        $ancestors = [];
        $queue = [$start->hex];

        while ($queue !== []) {
            $hex = array_shift($queue);

            if ($hex === null || isset($ancestors[$hex])) {
                continue;
            }

            $ancestors[$hex] = true;
            $commit = $this->objects->read(ObjectId::fromHex($hex));

            if (!$commit instanceof Commit) {
                continue;
            }

            foreach ($commit->parents as $parent) {
                if (!isset($ancestors[$parent->hex])) {
                    $queue[] = $parent->hex;
                }
            }
        }

        return $ancestors;
    }
}
