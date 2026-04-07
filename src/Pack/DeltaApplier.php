<?php

declare(strict_types=1);

namespace Pitmaster\Pack;

use Pitmaster\Exceptions\PackParseException;
use Pitmaster\Encoding\BinaryReader;

/**
 * Applies delta instructions (copy/insert) against a base object.
 *
 * Delta instruction format:
 * - Copy:   1XXXXXXX [offset bytes 0-4] [size bytes 0-3]
 *           Copies from base at offset for size bytes.
 *           Offset/size bytes present based on which X bits are set.
 *           Zero-size copy means 0x10000 bytes.
 *
 * - Insert: 0LLLLLLL <L bytes of literal data>
 *           L=1-127. L=0 is reserved/invalid.
 */
final class DeltaApplier
{
    /**
     * Apply delta instructions to produce the target content.
     *
     * @param string $base The base object content
     * @param string $delta The raw delta instruction stream
     * @return string The resulting content
     */
    public static function apply(string $base, string $delta): string
    {
        $reader = new BinaryReader($delta);

        // Delta header: source size and target size (both as size-encoding varints).
        $sourceSize = self::readDeltaSize($reader);
        $targetSize = self::readDeltaSize($reader);

        if ($sourceSize !== strlen($base)) {
            throw PackParseException::invalidDeltaBase(
                "source size mismatch: delta says {$sourceSize}, base is " . strlen($base)
            );
        }

        $result = '';

        while (!$reader->isEof()) {
            $opcode = $reader->readByte();

            if ($opcode & 0x80) {
                // Copy instruction
                $offset = 0;
                $size = 0;

                if ($opcode & 0x01) {
                    $offset = $reader->readByte();
                }
                if ($opcode & 0x02) {
                    $offset |= $reader->readByte() << 8;
                }
                if ($opcode & 0x04) {
                    $offset |= $reader->readByte() << 16;
                }
                if ($opcode & 0x08) {
                    $offset |= $reader->readByte() << 24;
                }

                if ($opcode & 0x10) {
                    $size = $reader->readByte();
                }
                if ($opcode & 0x20) {
                    $size |= $reader->readByte() << 8;
                }
                if ($opcode & 0x40) {
                    $size |= $reader->readByte() << 16;
                }

                if ($size === 0) {
                    $size = 0x10000;
                }

                $result .= substr($base, $offset, $size);
            } elseif ($opcode > 0) {
                // Insert instruction: opcode is the byte count
                $result .= $reader->read($opcode);
            } else {
                // opcode 0 is reserved
                throw PackParseException::invalidDeltaBase('zero-length insert opcode');
            }
        }

        if (strlen($result) !== $targetSize) {
            throw PackParseException::invalidDeltaBase(
                "target size mismatch: expected {$targetSize}, got " . strlen($result)
            );
        }

        return $result;
    }

    /**
     * Read a delta header size (variable-length, 7 bits per byte, MSB continue).
     */
    private static function readDeltaSize(BinaryReader $reader): int
    {
        $size = 0;
        $shift = 0;

        do {
            $byte = $reader->readByte();
            $size |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while ($byte & 0x80);

        return $size;
    }
}
