<?php

declare(strict_types=1);

namespace Pitmaster\Merge;

use Pitmaster\Object\ObjectId;

/**
 * Result of a merge operation.
 */
final readonly class MergeResult
{
    /**
     * @param bool $clean Whether the merge completed without conflicts
     * @param ?ObjectId $commitId The merge commit ID (null if conflicts)
     * @param array<int, string> $conflictPaths Files with conflicts
     * @param array<string, string> $mergedContents Path => merged content (with markers if conflicted)
     */
    public function __construct(
        public bool $clean,
        public ?ObjectId $commitId = null,
        public array $conflictPaths = [],
        public array $mergedContents = [],
    ) {
    }
}
