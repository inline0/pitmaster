<?php

declare(strict_types=1);

namespace Pitmaster\Ref;

use Pitmaster\Encoding\BinaryReader;
use Pitmaster\Object\ObjectId;

/**
 * Reftable format reader.
 *
 * Reftable is a newer binary format for storing refs, replacing loose + packed refs.
 * Structure: header + blocks (restart-compressed ref entries) + footer.
 *
 * Header: "REFT" + version (1 byte) + block_size (3 bytes)
 * Each block: sorted ref entries with restart points for binary search.
 * Footer: stats + offsets + checksum.
 */
final class Reftable
{
    private const MAGIC = "REFT";

    /** @var array<string, ObjectId> */
    private array $refs = [];

    private int $blockSize = 4096;

    public static function open(string $path): ?self
    {
        if (!is_file($path)) {
            return null;
        }

        $data = file_get_contents($path);

        if ($data === false || strlen($data) < 28) {
            return null;
        }

        $reader = new BinaryReader($data);

        return self::parse($reader);
    }

    public static function parse(BinaryReader $reader): self
    {
        $reftable = new self();

        $magic = $reader->read(4);

        if ($magic !== self::MAGIC) {
            return $reftable;
        }

        $version = $reader->readByte();

        // Block size: 3 bytes, big-endian
        $b1 = $reader->readByte();
        $b2 = $reader->readByte();
        $b3 = $reader->readByte();
        $reftable->blockSize = ($b1 << 16) | ($b2 << 8) | $b3;

        if ($reftable->blockSize === 0) {
            $reftable->blockSize = 4096;
        }

        // Read ref blocks
        // Each block starts with a block type byte and entry count
        while ($reader->remaining() > 68) { // 68 = footer size
            $blockStart = $reader->position();
            $blockType = $reader->readByte();

            if ($blockType === ord('r')) {
                // Ref block
                $restartCount = $reader->readByte();
                // Simplified: read entries until block boundary
                $remaining = min($reftable->blockSize - 2, $reader->remaining() - 68);

                if ($remaining <= 0) {
                    break;
                }

                $blockData = $reader->read($remaining);

                // Parse entries from block data (simplified)
                $entryReader = new BinaryReader($blockData);

                while ($entryReader->remaining() > 21) {
                    try {
                        $nameLen = $entryReader->readByte();

                        if ($nameLen === 0) {
                            break;
                        }

                        $name = $entryReader->read($nameLen);
                        $valueType = $entryReader->readByte();

                        if ($valueType === 1) {
                            $hash = $entryReader->readHash20();
                            $reftable->refs[$name] = ObjectId::fromHex($hash);
                        } else {
                            break;
                        }
                    } catch (\Throwable) {
                        break;
                    }
                }
            } else {
                // Unknown or non-ref block, skip to next block
                break;
            }
        }

        return $reftable;
    }

    /**
     * @return array<string, ObjectId>
     */
    public function refs(): array
    {
        return $this->refs;
    }

    public function resolve(string $name): ?ObjectId
    {
        return $this->refs[$name] ?? null;
    }
}
