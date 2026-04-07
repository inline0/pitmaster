<?php

declare(strict_types=1);

namespace Pitmaster\Pack;

use Pitmaster\Encoding\BinaryReader;

/**
 * Multi-pack-index (MIDX) reader.
 *
 * Provides a single index across multiple pack files for faster object lookup.
 *
 * Format:
 *   Header: MIDX signature + version + hash version + chunk count + pack count
 *   Chunk lookup table
 *   Chunks: pack names, OID fanout, OID lookup, object offsets
 */
final class MultiPackIndex
{
    private const MAGIC = "MIDX";

    /** @var array<int, string> Pack file names */
    private array $packNames = [];

    /** @var array<int, int> Fanout table */
    private array $fanout = [];

    /** @var array<int, string> Object hashes (sorted) */
    private array $oids = [];

    /** @var array<int, array{pack: int, offset: int}> */
    private array $offsets = [];

    private int $objectCount = 0;

    public static function open(string $path): ?self
    {
        if (!is_file($path)) {
            return null;
        }

        $reader = BinaryReader::fromFile($path);

        return self::parse($reader);
    }

    public static function parse(BinaryReader $reader): self
    {
        $midx = new self();

        $magic = $reader->read(4);

        if ($magic !== self::MAGIC) {
            return $midx;
        }

        $version = $reader->readByte();
        $hashVersion = $reader->readByte(); // 1 = SHA-1, 2 = SHA-256
        $chunkCount = $reader->readByte();
        $_ = $reader->readByte(); // base MIDX count
        $packCount = $reader->readUint32();

        // Read chunk lookup table
        $chunks = [];

        for ($i = 0; $i < $chunkCount; $i++) {
            $chunkId = $reader->readUint32();
            $chunkOffset = $reader->readUint32();
            // Upper 4 bytes of offset (for large files)
            $chunks[] = ['id' => $chunkId, 'offset' => $chunkOffset];
        }

        // Trailing entry for end-of-chunks
        $reader->readUint32();
        $reader->readUint32();

        // Parse each chunk based on ID
        foreach ($chunks as $chunk) {
            $reader->seek($chunk['offset']);

            switch ($chunk['id']) {
                case 0x504E414D: // PNAM - Pack names
                    $midx->packNames = self::readPackNames($reader, $packCount);
                    break;

                case 0x4F494446: // OIDF - OID fanout
                    for ($i = 0; $i < 256; $i++) {
                        $midx->fanout[$i] = $reader->readUint32();
                    }
                    $midx->objectCount = $midx->fanout[255];
                    break;

                case 0x4F49444C: // OIDL - OID lookup
                    for ($i = 0; $i < $midx->objectCount; $i++) {
                        $midx->oids[$i] = $reader->readHash20();
                    }
                    break;

                case 0x4F4F4646: // OOFF - Object offsets
                    for ($i = 0; $i < $midx->objectCount; $i++) {
                        $packIdx = $reader->readUint32();
                        $offset = $reader->readUint32();
                        $midx->offsets[$i] = ['pack' => $packIdx, 'offset' => $offset];
                    }
                    break;
            }
        }

        return $midx;
    }

    public function objectCount(): int
    {
        return $this->objectCount;
    }

    /**
     * @return array<int, string>
     */
    public function packNames(): array
    {
        return $this->packNames;
    }

    /**
     * Find which pack file contains an object and at what offset.
     *
     * @return array{pack: int, offset: int}|null
     */
    public function findObject(string $hex): ?array
    {
        $firstByte = (int) hexdec(substr($hex, 0, 2));
        $lo = $firstByte > 0 ? ($this->fanout[$firstByte - 1] ?? 0) : 0;
        $hi = ($this->fanout[$firstByte] ?? 0) - 1;

        while ($lo <= $hi) {
            $mid = (int) (($lo + $hi) / 2);
            $cmp = strcmp($hex, $this->oids[$mid] ?? '');

            if ($cmp === 0) {
                return $this->offsets[$mid] ?? null;
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
    private static function readPackNames(BinaryReader $reader, int $count): array
    {
        $names = [];

        for ($i = 0; $i < $count; $i++) {
            $names[] = $reader->readNullTerminated();
        }

        return $names;
    }
}
