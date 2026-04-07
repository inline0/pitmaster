<?php

declare(strict_types=1);

namespace Pitmaster\Object;

/**
 * Single entry in a tree object.
 *
 * Mode values: 100644 (file), 100755 (executable), 040000 (directory),
 * 120000 (symlink), 160000 (gitlink/submodule).
 */
final readonly class TreeEntry
{
    public function __construct(
        public string $mode,
        public string $name,
        public ObjectId $hash,
    ) {
    }

    public function isBlob(): bool
    {
        return str_starts_with($this->mode, '10');
    }

    public function isTree(): bool
    {
        return $this->mode === '40000' || $this->mode === '040000';
    }

    public function isExecutable(): bool
    {
        return $this->mode === '100755';
    }

    public function isSymlink(): bool
    {
        return $this->mode === '120000';
    }

    public function isGitlink(): bool
    {
        return $this->mode === '160000';
    }
}
