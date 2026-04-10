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
 *   Checksum: repository hash length (20-byte SHA-1 or 32-byte SHA-256)
 */
final class Index
{
    private const MAGIC = 'DIRC';

    /** @var list<IndexEntry> */
    private array $entries = [];

    /** @var list<array{signature: string, data: string}> */
    private array $extensions = [];

    private int $version = 2;
    private int $hashBytes;

    public function __construct(int $hashBytes = 20)
    {
        $this->hashBytes = $hashBytes;
    }

    /**
     * Parse an index file.
     */
    public static function open(string $path, int $hashBytes = 20): self
    {
        if (!is_file($path)) {
            return new self($hashBytes);
        }

        $data = file_get_contents($path);

        if ($data === false) {
            return new self($hashBytes);
        }

        return self::parse($data, $path, $hashBytes);
    }

    public static function parse(string $data, string $path = '', int $hashBytes = 20): self
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

        $index = new self($hashBytes);
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
            $hash = ObjectId::fromBinary($reader->read($hashBytes));
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
                    $prevPath = $index->entries[$i - 1]->path;
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
                extendedFlags: $extendedFlags,
            );

            $index->entries[] = $entry;
        }

        // Parse extensions (if remaining data before the trailing index checksum)
        while ($reader->remaining() > $hashBytes) {
            $sig = $reader->read(4);
            $extSize = $reader->readUint32();
            $index->extensions[] = [
                'signature' => $sig,
                'data' => $reader->read($extSize),
            ];
        }

        $index->sortEntries();

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
        $entries = [];

        foreach ($this->entries as $entry) {
            if ($entry->stage() === 0) {
                $entries[$entry->path] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @return list<IndexEntry>
     */
    public function allEntries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<array{signature: string, data: string}>
     */
    public function extensions(): array
    {
        return $this->extensions;
    }

    public function entry(string $path, int $stage = 0): ?IndexEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->path === $path && $entry->stage() === $stage) {
                return $entry;
            }
        }

        return null;
    }

    public function has(string $path, int $stage = 0): bool
    {
        return $this->entry($path, $stage) !== null;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function hashBytes(): int
    {
        return $this->hashBytes;
    }

    public function hashAlgo(): string
    {
        return $this->hashBytes === 32 ? 'sha256' : 'sha1';
    }

    /**
     * Add or update an entry.
     */
    public function addEntry(IndexEntry $entry): void
    {
        $this->addEntries([$entry]);
    }

    /**
     * Add or update multiple entries in one pass.
     *
     * @param list<IndexEntry> $entries
     */
    public function addEntries(array $entries): void
    {
        if ($entries === []) {
            return;
        }

        $this->dropExtensions();

        foreach ($entries as $entry) {
            if ($entry->extendedFlags !== 0 && $this->version < 3) {
                $this->version = 3;
            }
        }

        $pendingByPath = [];

        foreach ($entries as $entry) {
            $pathEntries = $pendingByPath[$entry->path] ?? [];
            $stage = $entry->stage();

            $pathEntries = array_values(array_filter(
                $pathEntries,
                static function (IndexEntry $existing) use ($stage): bool {
                    if ($stage === 0 || $existing->stage() === 0) {
                        return false;
                    }

                    return $existing->stage() !== $stage;
                },
            ));
            $pathEntries[] = $entry;
            $pendingByPath[$entry->path] = $pathEntries;
        }

        $pathsWithStageZero = [];
        $stagesByPath = [];
        $pendingEntries = [];

        foreach ($pendingByPath as $path => $pathEntries) {
            foreach ($pathEntries as $entry) {
                $stage = $entry->stage();

                if ($stage === 0) {
                    $pathsWithStageZero[$path] = true;
                } else {
                    $stagesByPath[$path][$stage] = true;
                }

                $pendingEntries[] = $entry;
            }
        }

        $this->entries = array_values(array_filter(
            $this->entries,
            static function (IndexEntry $existing) use ($pendingByPath, $pathsWithStageZero, $stagesByPath): bool {
                if (!isset($pendingByPath[$existing->path])) {
                    return true;
                }

                if (isset($pathsWithStageZero[$existing->path])) {
                    return false;
                }

                if ($existing->stage() === 0) {
                    return false;
                }

                return !isset($stagesByPath[$existing->path][$existing->stage()]);
            },
        ));

        array_push($this->entries, ...$pendingEntries);
        $this->sortEntries();
    }

    /**
     * Remove an entry.
     */
    public function removeEntry(string $path, ?int $stage = null): void
    {
        $this->removeEntries([$path], $stage);
    }

    /**
     * Remove multiple entries in one pass.
     *
     * @param list<string> $paths
     */
    public function removeEntries(array $paths, ?int $stage = null): void
    {
        if ($paths === []) {
            return;
        }

        $this->dropExtensions();
        $pathSet = array_fill_keys($paths, true);

        $this->entries = array_values(array_filter(
            $this->entries,
            static fn (IndexEntry $entry): bool => !isset($pathSet[$entry->path])
                || ($stage !== null && $entry->stage() !== $stage),
        ));
    }

    /**
     * Remove all conflict stages for a path and keep only stage 0.
     */
    public function resolveConflict(string $path, IndexEntry $resolved): void
    {
        $this->removeEntry($path);
        $this->addEntry($resolved);
    }

    /**
     * @return array<int, IndexEntry>
     */
    public function stageEntries(string $path): array
    {
        $entries = [];

        foreach ($this->entries as $entry) {
            if ($entry->path === $path) {
                $entries[$entry->stage()] = $entry;
            }
        }

        ksort($entries);

        return $entries;
    }

    /**
     * @return array<int, string>
     */
    public function paths(): array
    {
        $paths = [];

        foreach ($this->entries as $entry) {
            $paths[$entry->path] = true;
        }

        return array_keys($paths);
    }

    /**
     * @return array<int, string>
     */
    public function unmergedPaths(): array
    {
        $paths = [];

        foreach ($this->entries as $entry) {
            if ($entry->stage() !== 0) {
                $paths[$entry->path] = true;
            }
        }

        return array_keys($paths);
    }

    public function hasUnmerged(): bool
    {
        return $this->unmergedPaths() !== [];
    }

    private function sortEntries(): void
    {
        usort($this->entries, static function (IndexEntry $a, IndexEntry $b): int {
            $pathCompare = strcmp($a->path, $b->path);

            if ($pathCompare !== 0) {
                return $pathCompare;
            }

            return $a->stage() <=> $b->stage();
        });
    }

    private function dropExtensions(): void
    {
        $this->extensions = [];
    }
}
