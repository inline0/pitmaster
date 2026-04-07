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
}
