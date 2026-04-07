<?php

declare(strict_types=1);

namespace Pitmaster\Object;

/**
 * Base git object: type + raw content + computed hash.
 *
 * Subclassed by Blob, Tree, Commit, Tag which add parsed fields.
 */
abstract readonly class GitObject
{
    public function __construct(
        public ObjectType $type,
        public string $content,
        public ObjectId $id,
    ) {
    }

    public function size(): int
    {
        return strlen($this->content);
    }
}
