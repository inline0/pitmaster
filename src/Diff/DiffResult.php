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
    ) {
    }

    /**
     * Format as unified diff output (matching git diff).
     */
    public function format(): string
    {
        if ($this->binary) {
            return "diff --git a/{$this->oldPath} b/{$this->newPath}\n"
                . "Binary files differ\n";
        }

        if ($this->hunks === []) {
            return '';
        }

        $lines = [];
        $lines[] = "diff --git a/{$this->oldPath} b/{$this->newPath}";

        if ($this->oldHash !== null && $this->newHash !== null) {
            $lines[] = "index " . substr($this->oldHash, 0, 7) . ".." . substr($this->newHash, 0, 7) . " 100644";
        }

        $lines[] = "--- a/{$this->oldPath}";
        $lines[] = "+++ b/{$this->newPath}";

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
        return $this->hunks !== [] || $this->binary;
    }
}
