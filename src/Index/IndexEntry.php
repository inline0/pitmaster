<?php

declare(strict_types=1);

namespace Pitmaster\Index;

use Pitmaster\Object\ObjectId;

/**
 * Single index entry (staging area record).
 *
 * Matches the on-disk format:
 *   [ctime 8B] [mtime 8B] [dev 4B] [ino 4B] [mode 4B] [uid 4B] [gid 4B]
 *   [size 4B] [SHA-1 20B] [flags 2B] [path NUL-terminated] [padding]
 *
 * Flags: bit 15 = assume-valid, bits 13-12 = stage (0-3), bits 11-0 = path length (capped at 0xFFF).
 */
final readonly class IndexEntry
{
    public function __construct(
        public int $ctimeSec,
        public int $ctimeNsec,
        public int $mtimeSec,
        public int $mtimeNsec,
        public int $dev,
        public int $ino,
        public int $mode,
        public int $uid,
        public int $gid,
        public int $fileSize,
        public ObjectId $hash,
        public int $flags,
        public string $path,
    ) {
    }

    public function stage(): int
    {
        return ($this->flags >> 12) & 0x03;
    }

    public function assumeValid(): bool
    {
        return ($this->flags & 0x8000) !== 0;
    }

    public function pathLength(): int
    {
        return $this->flags & 0x0FFF;
    }

    /**
     * Create an entry for staging a file.
     */
    public static function create(
        string $path,
        ObjectId $hash,
        int $mode = 0100644,
        int $fileSize = 0,
        int $stage = 0,
    ): self {
        $now = time();
        $flags = min(strlen($path), 0xFFF) | ($stage << 12);

        return new self(
            ctimeSec: $now,
            ctimeNsec: 0,
            mtimeSec: $now,
            mtimeNsec: 0,
            dev: 0,
            ino: 0,
            mode: $mode,
            uid: 0,
            gid: 0,
            fileSize: $fileSize,
            hash: $hash,
            flags: $flags,
            path: $path,
        );
    }

    /**
     * Create from stat info for a real file.
     */
    public static function fromStat(string $path, ObjectId $hash, string $fullPath): self
    {
        $stat = stat($fullPath);
        $mode = is_executable($fullPath) ? 0100755 : 0100644;
        $flags = min(strlen($path), 0xFFF);

        if ($stat === false) {
            return self::create($path, $hash, $mode);
        }

        return new self(
            ctimeSec: $stat['ctime'],
            ctimeNsec: 0,
            mtimeSec: $stat['mtime'],
            mtimeNsec: 0,
            dev: $stat['dev'],
            ino: $stat['ino'],
            mode: $mode,
            uid: $stat['uid'],
            gid: $stat['gid'],
            fileSize: $stat['size'],
            hash: $hash,
            flags: $flags,
            path: $path,
        );
    }
}
