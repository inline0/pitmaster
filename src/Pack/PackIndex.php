<?php

declare(strict_types=1);

namespace Pitmaster\Pack;

use Pitmaster\Encoding\BinaryReader;
use Pitmaster\Exceptions\PackParseException;
use RuntimeException;

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
        try {
            $magic = $reader->read(4);

            if ($magic !== self::MAGIC) {
                $reader->seek(0);
                $v1 = PackIndexV1::parse($reader);
                $index = new self();
                $index->objectCount = $v1->objectCount();
                $index->names = $v1->allHashes();
                $index->fanout = array_fill(0, 256, 0);

                foreach ($index->names as $hash) {
                    $fb = (int) hexdec(substr($hash, 0, 2));

                    for ($j = $fb; $j < 256; $j++) {
                        $index->fanout[$j]++;
                    }
                }

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

            for ($i = 0; $i < 256; $i++) {
                $index->fanout[$i] = $reader->readUint32();
            }

            $index->objectCount = $index->fanout[255];
            $n = $index->objectCount;
            $sha1Size = 8 + 1024 + ($n * 20) + ($n * 4) + ($n * 4) + 20 + 20;
            $sha256Size = 8 + 1024 + ($n * 32) + ($n * 4) + ($n * 4) + 32 + 32;

            if ($reader->length() === $sha256Size && $reader->length() !== $sha1Size) {
                $index->hashBytes = 32;
            }

            for ($i = 0; $i < $index->objectCount; $i++) {
                if ($index->hashBytes === 32) {
                    $index->names[$i] = $reader->readHash32();
                } else {
                    $index->names[$i] = $reader->readHash20();
                }
            }

            $reader->skip($index->objectCount * 4);

            $largeOffsetIndices = [];

            for ($i = 0; $i < $index->objectCount; $i++) {
                $offset = $reader->readUint32();

                if ($offset & 0x80000000) {
                    $largeOffsetIndices[$i] = $offset & 0x7FFFFFFF;
                    $index->offsets[$i] = 0;
                } else {
                    $index->offsets[$i] = $offset;
                }
            }

            if ($largeOffsetIndices !== []) {
                $largeOffsets = [];
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
        } catch (PackParseException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            throw PackParseException::truncated($path ?: 'pack index', $e->getMessage());
        }
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
