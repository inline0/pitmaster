<?php

declare(strict_types=1);

namespace Pitmaster\Pack;

use Pitmaster\Object\ObjectType;

/**
 * A parsed pack entry header.
 *
 * For base objects (commit, tree, blob, tag): type and uncompressed size.
 * For delta objects: type, size, and base reference (offset or hash).
 */
final readonly class PackEntry
{
    public const TYPE_OFS_DELTA = 6;
    public const TYPE_REF_DELTA = 7;

    public function __construct(
        public int $packType,
        public int $uncompressedSize,
        public int $dataOffset,
        public int $entryOffset,
        public ?int $baseOffset = null,
        public ?string $baseHash = null,
    ) {
    }

    public function isDelta(): bool
    {
        return $this->packType === self::TYPE_OFS_DELTA || $this->packType === self::TYPE_REF_DELTA;
    }

    public function isOfsDelta(): bool
    {
        return $this->packType === self::TYPE_OFS_DELTA;
    }

    public function isRefDelta(): bool
    {
        return $this->packType === self::TYPE_REF_DELTA;
    }

    public function objectType(): ?ObjectType
    {
        if ($this->isDelta()) {
            return null;
        }

        return ObjectType::fromPackType($this->packType);
    }
}
