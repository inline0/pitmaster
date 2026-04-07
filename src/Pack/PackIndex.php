<?php

declare(strict_types=1);

namespace Pitmaster\Pack;

use Pitmaster\Encoding\BinaryReader;
use Pitmaster\Exceptions\PackParseException;

/**
 * Pack index (.idx) file reader. Supports v2 format.
 *
 * Index v2 format:
 *   Magic:   FF 74 4F 63
 *   Version: 00 00 00 02
 *   Fanout:  256 x 4-byte big-endian cumulative counts
 *   Names:   N x 20-byte SHA-1 hashes (sorted)
 *   CRC32s:  N x 4-byte CRC32
 *   Offsets: N x 4-byte offsets (MSB=1 means index into large offset table)
 *   Large:   M x 8-byte offsets (for packs > 2GB)
 *   Pack checksum: 20 bytes
 *   Index checksum: 20 bytes
 */
final class PackIndex
{
    private const MAGIC = "\xFF\x74\x4F\x63";

    /** @var array<int, int> Fanout table (256 entries) */
    private array $fanout = [];

    /** @var array<int, string> Sorted hex hashes */
    private array $names = [];

    /** @var array<int, int> Pack file offsets indexed same as names */
    private array $offsets = [];

    private int $objectCount;

    private int $hashBytes = 20;

    private function __construct()
    {
    }

    public static function open(string $path): self
    {
        $reader = BinaryReader::fromFile($path);

        return self::parse($reader, $path);
    }

    public static function parse(BinaryReader $reader, string $path = ''): self
    {
        $magic = $reader->read(4);

        if ($magic !== self::MAGIC) {
            // No magic = v1 index. Seek back and parse as v1.
            $reader->seek(0);
            $v1 = PackIndexV1::parse($reader);

            // Convert v1 to our format
            $index = new self();
            $index->objectCount = $v1->objectCount();
            $index->names = $v1->allHashes();

            // Build fanout from sorted names
            $index->fanout = array_fill(0, 256, 0);

            foreach ($index->names as $i => $hash) {
                $fb = (int) hexdec(substr($hash, 0, 2));

                for ($j = $fb; $j < 256; $j++) {
                    $index->fanout[$j]++;
                }
            }

            // Get offsets
            for ($i = 0; $i < $index->objectCount; $i++) {
                $index->offsets[$i] = $v1->findOffset($index->names[$i]) ?? 0;
            }

            return $index;
        }

        $version = $reader->readUint32();

        if ($version !== 2) {
            throw PackParseException::unsupportedVersion($version, $path ?: 'pack index');
        }

        $index = new self();

        // Fanout table: 256 cumulative counts
        for ($i = 0; $i < 256; $i++) {
            $index->fanout[$i] = $reader->readUint32();
        }

        $index->objectCount = $index->fanout[255];

        // Detect SHA-256 by checking file size.
        // v2 with SHA-1: 8 + 1024 + N*20 + N*4 + N*4 + 20 + 20
        // v2 with SHA-256: 8 + 1024 + N*32 + N*4 + N*4 + 32 + 32
        $n = $index->objectCount;
        $sha1Size = 8 + 1024 + ($n * 20) + ($n * 4) + ($n * 4) + 20 + 20;
        $sha256Size = 8 + 1024 + ($n * 32) + ($n * 4) + ($n * 4) + 32 + 32;

        if ($reader->length() === $sha256Size && $reader->length() !== $sha1Size) {
            $index->hashBytes = 32;
        }

        // Names: N x hash-sized entries
        for ($i = 0; $i < $index->objectCount; $i++) {
            if ($index->hashBytes === 32) {
                $index->names[$i] = $reader->readHash32();
            } else {
                $index->names[$i] = $reader->readHash20();
            }
        }

        // CRC32s: skip (N x 4 bytes)
        $reader->skip($index->objectCount * 4);

        // 4-byte offsets
        $largeOffsetIndices = [];

        for ($i = 0; $i < $index->objectCount; $i++) {
            $offset = $reader->readUint32();

            if ($offset & 0x80000000) {
                // MSB set: index into large offset table
                $largeOffsetIndices[$i] = $offset & 0x7FFFFFFF;
                $index->offsets[$i] = 0; // placeholder
            } else {
                $index->offsets[$i] = $offset;
            }
        }

        // Large offsets (8 bytes each) if any
        if ($largeOffsetIndices !== []) {
            $largeOffsets = [];
            // We need to figure out how many large offsets there are
            $maxLargeIndex = max($largeOffsetIndices);

            for ($i = 0; $i <= $maxLargeIndex; $i++) {
                $high = $reader->readUint32();
                $low = $reader->readUint32();
                $largeOffsets[$i] = ($high << 32) | $low;
            }

            foreach ($largeOffsetIndices as $nameIdx => $largeIdx) {
                $index->offsets[$nameIdx] = $largeOffsets[$largeIdx];
            }
        }

        return $index;
    }

    public function objectCount(): int
    {
        return $this->objectCount;
    }

    public function hashBytes(): int
    {
        return $this->hashBytes;
    }

    /**
     * Find the pack offset for a given object hash.
     *
     * Uses the fanout table for binary search range, then linear/binary search in names.
     *
     * @return int|null Pack file offset, or null if not found
     */
    public function findOffset(string $hex): ?int
    {
        $firstByte = (int) hexdec(substr($hex, 0, 2));

        $lo = $firstByte > 0 ? $this->fanout[$firstByte - 1] : 0;
        $hi = $this->fanout[$firstByte] - 1;

        while ($lo <= $hi) {
            $mid = (int) (($lo + $hi) / 2);
            $cmp = strcmp($hex, $this->names[$mid]);

            if ($cmp === 0) {
                return $this->offsets[$mid];
            }

            if ($cmp < 0) {
                $hi = $mid - 1;
            } else {
                $lo = $mid + 1;
            }
        }

        return null;
    }

    /**
     * Get all object hashes in this index.
     *
     * @return array<int, string> Hex hashes (sorted)
     */
    public function allHashes(): array
    {
        return $this->names;
    }

    /**
     * Get all entries as hash => offset pairs.
     *
     * @return array<string, int>
     */
    public function allEntries(): array
    {
        $entries = [];

        for ($i = 0; $i < $this->objectCount; $i++) {
            $entries[$this->names[$i]] = $this->offsets[$i];
        }

        return $entries;
    }
}
