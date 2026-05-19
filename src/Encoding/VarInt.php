<?php

declare(strict_types=1);

namespace Pitmaster\Encoding;

/**
 * Git-style variable-length integer codec.
 *
 * Used in pack file type/size headers. MSB-continue encoding:
 * the most significant bit of each byte indicates whether more bytes follow.
 *
 * Pack entry header: first byte encodes type (bits 6-4) and first 4 size bits (bits 3-0).
 * Subsequent bytes contribute 7 bits each to the size.
 *
 * OFS_DELTA offset: different encoding where each continuation byte adds 1 to the value
 * before shifting, producing a non-redundant encoding for positive offsets.
 */
final class VarInt
{
    /**
     * Decode a pack entry size from the reader.
     *
     * The first byte's lower 4 bits are the initial size bits (already extracted by caller).
     * Subsequent bytes contribute 7 bits each, shifted left by increasing amounts.
     *
     * @return int The decoded size
     */
    public static function decodePackSize(BinaryReader $reader, int $initialBits, int $shift = 4): int
    {
        $size = $initialBits;
        $byte = 0x80; // Force entry into loop if caller sets MSB

        while ($byte & 0x80) {
            $byte = $reader->readByte();
            $size |= ($byte & 0x7F) << $shift;
            $shift += 7;
        }

        return $size;
    }

    /**
     * Decode an OFS_DELTA negative offset.
     *
     * Each byte contributes 7 bits. If MSB is set, more bytes follow.
     * Each continuation adds 1 before shifting to ensure unique encoding.
     *
     * @return int The positive offset value (caller negates from current position)
     */
    public static function decodeOfsOffset(BinaryReader $reader): int
    {
        $byte = $reader->readByte();
        $offset = $byte & 0x7F;

        while ($byte & 0x80) {
            $byte = $reader->readByte();
            $offset = (($offset + 1) << 7) | ($byte & 0x7F);
        }

        return $offset;
    }

    /**
     * Encode a size as Git pack varint bytes.
     *
     * @return string The encoded bytes
     */
    public static function encodePackSize(int $type, int $size): string
    {
        $byte = ($type << 4) | ($size & 0x0F);
        $size >>= 4;
        $result = '';

        while ($size > 0) {
            $result .= chr(($byte | 0x80) & 0xFF);
            $byte = $size & 0x7F;
            $size >>= 7;
        }

        $result .= chr($byte & 0xFF);

        return $result;
    }
}
