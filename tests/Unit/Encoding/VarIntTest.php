<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Encoding;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Encoding\BinaryReader;
use Pitmaster\Encoding\VarInt;

final class VarIntTest extends TestCase
{
    #[Test]
    public function testEncodeDecodeRoundtripSmallSize(): void
    {
        // Type 3 (blob), size 10 (fits in 4 bits)
        $encoded = VarInt::encodePackSize(3, 10);
        $reader = new BinaryReader($encoded);

        $firstByte = $reader->readByte();
        $type = ($firstByte >> 4) & 0x07;
        $initialBits = $firstByte & 0x0F;

        $this->assertSame(3, $type);

        if ($firstByte & 0x80) {
            $size = VarInt::decodePackSize($reader, $initialBits, 4);
        } else {
            $size = $initialBits;
        }

        $this->assertSame(10, $size);
    }

    #[Test]
    public function testEncodeDecodeRoundtripLargeSize(): void
    {
        // Type 1 (commit), size 300 (needs multiple bytes)
        $encoded = VarInt::encodePackSize(1, 300);
        $reader = new BinaryReader($encoded);

        $firstByte = $reader->readByte();
        $type = ($firstByte >> 4) & 0x07;
        $initialBits = $firstByte & 0x0F;

        $this->assertSame(1, $type);

        if ($firstByte & 0x80) {
            $size = VarInt::decodePackSize($reader, $initialBits, 4);
        } else {
            $size = $initialBits;
        }

        $this->assertSame(300, $size);
    }

    #[Test]
    public function testEncodeDecodeRoundtripVeryLargeSize(): void
    {
        $encoded = VarInt::encodePackSize(2, 100000);
        $reader = new BinaryReader($encoded);

        $firstByte = $reader->readByte();
        $type = ($firstByte >> 4) & 0x07;
        $initialBits = $firstByte & 0x0F;

        $this->assertSame(2, $type);

        if ($firstByte & 0x80) {
            $size = VarInt::decodePackSize($reader, $initialBits, 4);
        } else {
            $size = $initialBits;
        }

        $this->assertSame(100000, $size);
    }

    #[Test]
    public function testDecodeOfsOffset(): void
    {
        // Single byte offset: value < 128
        $reader = new BinaryReader(chr(42));
        $this->assertSame(42, VarInt::decodeOfsOffset($reader));
    }

    #[Test]
    public function testDecodeOfsOffsetMultipleBytes(): void
    {
        // Two byte offset: first byte has MSB set, second does not
        // byte1 = 0x81 (MSB=1, value=1), byte2 = 0x00 (MSB=0, value=0)
        // offset = ((1 + 1) << 7) | 0 = 256
        $reader = new BinaryReader("\x81\x00");
        $this->assertSame(256, VarInt::decodeOfsOffset($reader));
    }

    #[Test]
    public function testEncodePackSizeZeroSize(): void
    {
        // Type 3 (blob), size 0
        $encoded = VarInt::encodePackSize(3, 0);
        $reader = new BinaryReader($encoded);

        $firstByte = $reader->readByte();
        $type = ($firstByte >> 4) & 0x07;
        $initialBits = $firstByte & 0x0F;

        $this->assertSame(3, $type);
        $this->assertSame(0, $initialBits);
        $this->assertTrue($reader->isEof());
    }
}
