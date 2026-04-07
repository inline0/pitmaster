<?php

declare(strict_types=1);

namespace Pitmaster\Index;

use Pitmaster\Encoding\BinaryReader;
use Pitmaster\Exceptions\IndexParseException;
use Pitmaster\Object\ObjectId;

/**
 * .git/index reader/writer.
 *
 * Index v2 format:
 *   Header: DIRC (4 bytes) + version (4 bytes) + entry count (4 bytes)
 *   Entries: sorted by path
 *   Extensions: optional
 *   Checksum: 20-byte SHA-1
 */
final class Index
{
    private const MAGIC = 'DIRC';

    /** @var array<string, IndexEntry> Keyed by path */
    private array $entries = [];

    private int $version = 2;

    public function __construct()
    {
    }

    /**
     * Parse an index file.
     */
    public static function open(string $path): self
    {
        if (!is_file($path)) {
            return new self();
        }

        $data = file_get_contents($path);

        if ($data === false) {
            return new self();
        }

        return self::parse($data, $path);
    }

    public static function parse(string $data, string $path = ''): self
    {
        $reader = new BinaryReader($data);

        $magic = $reader->read(4);

        if ($magic !== self::MAGIC) {
            throw IndexParseException::invalidMagic($path ?: 'index');
        }

        $version = $reader->readUint32();

        if ($version < 2 || $version > 4) {
            throw IndexParseException::unsupportedVersion($version, $path ?: 'index');
        }

        $entryCount = $reader->readUint32();

        $index = new self();
        $index->version = $version;

        for ($i = 0; $i < $entryCount; $i++) {
            $entryStart = $reader->position();

            $ctimeSec = $reader->readUint32();
            $ctimeNsec = $reader->readUint32();
            $mtimeSec = $reader->readUint32();
            $mtimeNsec = $reader->readUint32();
            $dev = $reader->readUint32();
            $ino = $reader->readUint32();
            $mode = $reader->readUint32();
            $uid = $reader->readUint32();
            $gid = $reader->readUint32();
            $fileSize = $reader->readUint32();
            $hash = ObjectId::fromBinary($reader->read(20));
            $flags = $reader->readUint16();

            $pathLen = $flags & 0x0FFF;

            // v3 extended flags (bit 14 set means 2 more flag bytes)
            $extendedFlags = 0;

            if ($version >= 3 && ($flags & 0x4000)) {
                $extendedFlags = $reader->readUint16();
            }

            if ($version >= 4) {
                // v4: path prefix compression
                // Strip N bytes from previous path, then append NUL-terminated suffix
                $stripLen = self::readVarint($reader);
                $pathSuffix = $reader->readNullTerminated();

                if ($i > 0) {
                    $prevPath = (string) array_key_last($index->entries);
                    $path = substr($prevPath, 0, max(0, strlen($prevPath) - $stripLen)) . $pathSuffix;
                } else {
                    $path = $pathSuffix;
                }
            } else {
                $path = $reader->readNullTerminated();
                // Entries are padded to 8-byte alignment from the start of the entry
                $entryLen = $reader->position() - $entryStart;
                $padLen = (8 - ($entryLen % 8)) % 8;

                if ($padLen > 0) {
                    $reader->skip($padLen);
                }
            }

            $entry = new IndexEntry(
                ctimeSec: $ctimeSec,
                ctimeNsec: $ctimeNsec,
                mtimeSec: $mtimeSec,
                mtimeNsec: $mtimeNsec,
                dev: $dev,
                ino: $ino,
                mode: $mode,
                uid: $uid,
                gid: $gid,
                fileSize: $fileSize,
                hash: $hash,
                flags: $flags,
                path: $path,
            );

            $index->entries[$path] = $entry;
        }

        // Parse extensions (if remaining data before the 20-byte checksum)
        while ($reader->remaining() > 20) {
            $sig = $reader->read(4);
            $extSize = $reader->readUint32();

            if ($sig === 'TREE') {
                // Cache tree extension: skip for now (read but don't use)
                $reader->skip($extSize);
            } elseif ($sig === 'REUC') {
                // Resolve undo extension: skip
                $reader->skip($extSize);
            } elseif ($sig === 'link') {
                // Split index extension: skip
                $reader->skip($extSize);
            } elseif ($sig === 'UNTR') {
                // Untracked cache: skip
                $reader->skip($extSize);
            } elseif ($sig === 'FSMN') {
                // FS monitor: skip
                $reader->skip($extSize);
            } elseif ($sig === 'EOIE') {
                // End of index entry: skip
                $reader->skip($extSize);
            } elseif ($sig === 'IEOT') {
                // Index entry offset table: skip
                $reader->skip($extSize);
            } else {
                // Unknown extension: skip if first byte is uppercase (required), else optional
                $reader->skip($extSize);
            }
        }

        return $index;
    }

    /**
     * Read a varint for v4 prefix compression.
     */
    private static function readVarint(BinaryReader $reader): int
    {
        $value = 0;
        $shift = 0;

        do {
            $byte = $reader->readByte();
            $value |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while ($byte & 0x80);

        return $value;
    }

    /**
     * @return array<string, IndexEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function entry(string $path): ?IndexEntry
    {
        return $this->entries[$path] ?? null;
    }

    public function has(string $path): bool
    {
        return isset($this->entries[$path]);
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * Add or update an entry.
     */
    public function addEntry(IndexEntry $entry): void
    {
        $this->entries[$entry->path] = $entry;
        ksort($this->entries);
    }

    /**
     * Remove an entry.
     */
    public function removeEntry(string $path): void
    {
        unset($this->entries[$path]);
    }

    /**
     * Remove all conflict stages for a path and keep only stage 0.
     */
    public function resolveConflict(string $path, IndexEntry $resolved): void
    {
        // Remove any staged entries for this path
        foreach ($this->entries as $key => $entry) {
            if ($entry->path === $path && $entry->stage() !== 0) {
                unset($this->entries[$key]);
            }
        }

        $this->entries[$path] = $resolved;
        ksort($this->entries);
    }
}
