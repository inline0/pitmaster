<?php

declare(strict_types=1);

namespace Pitmaster\Diff;

/**
 * Result of a diff operation. Contains hunks with context lines.
 */
final readonly class DiffResult
{
    /**
     * @param string $oldPath Path in the old tree (a/)
     * @param string $newPath Path in the new tree (b/)
     * @param array<int, Hunk> $hunks
     * @param bool $binary Whether this is a binary file diff
     */
    public function __construct(
        public string $oldPath,
        public string $newPath,
        public array $hunks,
        public bool $binary = false,
        public ?string $oldHash = null,
        public ?string $newHash = null,
        public bool $oldNoNewline = false,
        public bool $newNoNewline = false,
        public ?string $oldMode = '100644',
        public ?string $newMode = '100644',
        public ?int $similarity = null,
    ) {
    }

    /**
     * Format as unified diff output (matching git diff).
     */
    public function format(): string
    {
        if (!$this->hasChanges()) {
            return '';
        }

        $lines = [];
        $lines[] = "diff --git a/{$this->oldPath} b/{$this->newPath}";
        $added = $this->oldHash === null && $this->newHash !== null;
        $deleted = $this->oldHash !== null && $this->newHash === null;

        if ($added) {
            $lines[] = 'new file mode ' . $this->newMode;
        } elseif ($deleted) {
            $lines[] = 'deleted file mode ' . $this->oldMode;
        } elseif ($this->oldMode !== $this->newMode) {
            $lines[] = 'old mode ' . $this->oldMode;
            $lines[] = 'new mode ' . $this->newMode;
        }

        if ($this->similarity !== null) {
            $lines[] = 'similarity index ' . $this->similarity . '%';
            $lines[] = 'rename from ' . $this->oldPath;
            $lines[] = 'rename to ' . $this->newPath;
        }

        if (($this->oldHash !== null || $this->newHash !== null) && $this->oldHash !== $this->newHash) {
            $oldHash = $this->oldHash === null ? '0000000' : substr($this->oldHash, 0, 7);
            $newHash = $this->newHash === null ? '0000000' : substr($this->newHash, 0, 7);
            $mode = !$added && !$deleted && $this->oldMode === $this->newMode ? ' ' . $this->newMode : '';
            $lines[] = "index {$oldHash}..{$newHash}{$mode}";
        }

        $oldPath = $added ? '/dev/null' : 'a/' . $this->oldPath;
        $newPath = $deleted ? '/dev/null' : 'b/' . $this->newPath;

        if ($this->binary) {
            $lines[] = "Binary files {$oldPath} and {$newPath} differ";
            return implode("\n", $lines) . "\n";
        }

        if ($this->hunks === []) {
            return implode("\n", $lines) . "\n";
        }

        $lines[] = '--- ' . $oldPath;
        $lines[] = '+++ ' . $newPath;

        $lastHunkIndex = count($this->hunks) - 1;

        foreach ($this->hunks as $hunkIndex => $hunk) {
            $lines[] = $hunk->header();
            $lastDeleted = null;
            $lastInserted = null;

            foreach ($hunk->lines as $lineIndex => $line) {
                if ($line !== '' && $line[0] === '-') {
                    $lastDeleted = $lineIndex;
                } elseif ($line !== '' && $line[0] === '+') {
                    $lastInserted = $lineIndex;
                }
            }

            foreach ($hunk->lines as $lineIndex => $line) {
                $lines[] = $line;

                if ($hunkIndex !== $lastHunkIndex) {
                    continue;
                }

                if ($this->oldNoNewline && $lastDeleted !== null && $lineIndex === $lastDeleted) {
                    $lines[] = '\ No newline at end of file';
                }

                if ($this->newNoNewline && $lastInserted !== null && $lineIndex === $lastInserted) {
                    $lines[] = '\ No newline at end of file';
                }
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * Check if the diff has any changes.
     */
    public function hasChanges(): bool
    {
        return $this->hunks !== [] || $this->binary || $this->oldMode !== $this->newMode
            || $this->oldHash !== $this->newHash || $this->similarity !== null;
    }
}
