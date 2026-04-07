<?php

declare(strict_types=1);

namespace Pitmaster\Worktree;

use Pitmaster\Object\ObjectId;

/**
 * Represents a single worktree (main or linked).
 */
final readonly class Worktree
{
    public function __construct(
        public string $path,
        public string $gitDir,
        public ?string $branch,
        public ?ObjectId $head,
        public bool $isMain,
        public bool $isDetached,
        public bool $isLocked,
        public ?string $lockReason,
    ) {
    }
}
