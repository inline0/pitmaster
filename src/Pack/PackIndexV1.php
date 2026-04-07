<?php

declare(strict_types=1);

namespace Pitmaster\Pack;

use Pitmaster\Encoding\BinaryReader;

/**
 * Pack index v1 reader.
 *
 * V1 format (no magic header):
 *   Fanout: 256 x 4-byte big-endian cumulative counts
 *   Entries: N x (4-byte offset + 20-byte SHA-1)
 *   Pack checksum: 20 bytes
 *   Index checksum: 20 bytes
 *
 * Distinguished from v2 by absence of the 0xFF744F63 magic bytes.
 */
final class PackIndexV1
{
    /** @var array<int, int> */
    private array $fanout = [];

    /** @var array<int, string> */
    private array $names = [];

    /** @var array<int, int> */
    private array $offsets = [];

    private int $objectCount;

    public static function parse(BinaryReader $reader): self
    {
        $index = new self();

        // Fanout table
        for ($i = 0; $i < 256; $i++) {
            $index->fanout[$i] = $reader->readUint32();
        }

        $index->objectCount = $index->fanout[255];

        // Entries: offset (4 bytes) + hash (20 bytes) per object
        for ($i = 0; $i < $index->objectCount; $i++) {
            $index->offsets[$i] = $reader->readUint32();
            $index->names[$i] = $reader->readHash20();
        }

        return $index;
    }

    public function objectCount(): int
    {
        return $this->objectCount;
    }

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
     * @return array<int, string>
     */
    public function allHashes(): array
    {
        return $this->names;
    }
}
