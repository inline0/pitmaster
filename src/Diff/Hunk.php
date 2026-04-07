<?php

declare(strict_types=1);

namespace Pitmaster\Diff;

/**
 * A single diff hunk: a contiguous region of changes with context.
 */
final readonly class Hunk
{
    /**
     * @param int $oldStart Starting line in the old file (1-based)
     * @param int $oldCount Number of lines from old file
     * @param int $newStart Starting line in the new file (1-based)
     * @param int $newCount Number of lines from new file
     * @param array<int, string> $lines Lines with +/- / prefix
     */
    public function __construct(
        public int $oldStart,
        public int $oldCount,
        public int $newStart,
        public int $newCount,
        public array $lines,
    ) {
    }

    /**
     * Format as unified diff hunk header.
     */
    /**
     * Format as unified diff hunk header.
     * Git omits the count when it equals 1.
     */
    public function header(): string
    {
        $old = $this->oldCount === 1 ? "-{$this->oldStart}" : "-{$this->oldStart},{$this->oldCount}";
        $new = $this->newCount === 1 ? "+{$this->newStart}" : "+{$this->newStart},{$this->newCount}";

        return "@@ {$old} {$new} @@";
    }
}
