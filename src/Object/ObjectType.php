<?php

declare(strict_types=1);

namespace Pitmaster\Object;

enum ObjectType: string
{
    case Blob = 'blob';
    case Tree = 'tree';
    case Commit = 'commit';
    case Tag = 'tag';

    /**
     * Pack file type integer to ObjectType.
     */
    public static function fromPackType(int $packType): self
    {
        return match ($packType) {
            1 => self::Commit,
            2 => self::Tree,
            3 => self::Blob,
            4 => self::Tag,
            default => throw new \InvalidArgumentException("Unknown pack object type: {$packType}"),
        };
    }

    /**
     * ObjectType to pack file type integer.
     */
    public function toPackType(): int
    {
        return match ($this) {
            self::Commit => 1,
            self::Tree => 2,
            self::Blob => 3,
            self::Tag => 4,
        };
    }
}
