<?php

declare(strict_types=1);

namespace Pitmaster\Merge;

use Pitmaster\Object\Commit;
use Pitmaster\Object\ObjectId;

/**
 * Recursive merge strategy.
 *
 * When there are multiple merge bases (criss-cross merge), recursively
 * merge the bases first to create a virtual base, then three-way merge.
 */
final class RecursiveMerge
{
    public function __construct()
    {
    }

    /**
     * Merge two commits using recursive strategy.
     *
     * @return array{content: string, clean: bool, conflicts: int}
     */
    public function mergeContent(
        string $base,
        string $ours,
        string $theirs,
        string $oursLabel = 'HEAD',
        string $theirsLabel = 'incoming',
    ): array {
        // Recursive strategy delegates to three-way merge.
        // The "recursive" part is handling multiple merge bases at the tree level,
        // which is coordinated by Repository::merge().
        return ThreeWayMerge::merge($base, $ours, $theirs, $oursLabel, $theirsLabel);
    }
}
