<?php

declare(strict_types=1);

namespace Pitmaster\Status;

/**
 * Single file status entry.
 *
 * index = status of HEAD vs index (staged changes)
 * worktree = status of index vs worktree (unstaged changes)
 */
final readonly class StatusEntry
{
    public function __construct(
        public string $path,
        public FileStatus $index,
        public FileStatus $worktree,
        public ?string $origPath = null,
        public ?int $renameScore = null,
    ) {
    }

    /**
     * Short format: "XY path"
     */
    public function shortFormat(): string
    {
        $x = $this->index->value;
        $y = $this->worktree->value;

        if ($this->index === FileStatus::Untracked) {
            return "?? {$this->path}";
        }

        if ($this->index === FileStatus::Ignored) {
            return "!! {$this->path}";
        }

        if ($this->index === FileStatus::Renamed && $this->origPath !== null) {
            return "{$x}{$y} {$this->origPath} -> {$this->path}";
        }

        return "{$x}{$y} {$this->path}";
    }
}
