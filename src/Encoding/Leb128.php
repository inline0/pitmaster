<?php

declare(strict_types=1);

namespace Pitmaster\Encoding;

/**
 * LEB128 variable-length integer codec.
 *
 * Used in delta instructions for sizes and offsets. Standard unsigned LEB128:
 * 7 bits per byte, MSB indicates continuation.
 */
final class Leb128
{
    /**
     * Decode an unsigned LEB128 integer from the reader.
     */
    public static function decodeUnsigned(BinaryReader $reader): int
    {
        $result = 0;
        $shift = 0;

        do {
            $byte = $reader->readByte();
            $result |= ($byte & 0x7F) << $shift;
            $shift += 7;
        } while ($byte & 0x80);

        return $result;
    }

    /**
     * Encode an unsigned integer as LEB128 bytes.
     */
    public static function encodeUnsigned(int $value): string
    {
        $result = '';

        do {
            $byte = $value & 0x7F;
            $value >>= 7;

            if ($value > 0) {
                $byte |= 0x80;
            }

            $result .= chr($byte);
        } while ($value > 0);

        return $result;
    }
}
