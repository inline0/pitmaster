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
     * @param string|null $section Optional trailing section label for the hunk header
     */
    public function __construct(
        public int $oldStart,
        public int $oldCount,
        public int $newStart,
        public int $newCount,
        public array $lines,
        public ?string $section = null,
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
        $oldStart = $this->oldCount === 0 ? max(0, $this->oldStart - 1) : $this->oldStart;
        $newStart = $this->newCount === 0 ? max(0, $this->newStart - 1) : $this->newStart;
        $old = $this->oldCount === 1 ? "-{$oldStart}" : "-{$oldStart},{$this->oldCount}";
        $new = $this->newCount === 1 ? "+{$newStart}" : "+{$newStart},{$this->newCount}";
        $section = $this->section !== null && $this->section !== '' ? ' ' . $this->section : '';

        return "@@ {$old} {$new} @@{$section}";
    }
}
