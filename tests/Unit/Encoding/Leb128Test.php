<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Encoding;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Encoding\BinaryReader;
use Pitmaster\Encoding\Leb128;

final class Leb128Test extends TestCase
{
    #[Test]
    public function testRoundtripZero(): void
    {
        $encoded = Leb128::encodeUnsigned(0);
        $reader = new BinaryReader($encoded);
        $this->assertSame(0, Leb128::decodeUnsigned($reader));
    }

    #[Test]
    public function testRoundtripOne(): void
    {
        $encoded = Leb128::encodeUnsigned(1);
        $reader = new BinaryReader($encoded);
        $this->assertSame(1, Leb128::decodeUnsigned($reader));
    }

    #[Test]
    public function testRoundtrip127(): void
    {
        // 127 fits in a single byte (7 bits)
        $encoded = Leb128::encodeUnsigned(127);
        $this->assertSame(1, strlen($encoded));
        $reader = new BinaryReader($encoded);
        $this->assertSame(127, Leb128::decodeUnsigned($reader));
    }

    #[Test]
    public function testRoundtrip128(): void
    {
        // 128 requires two bytes
        $encoded = Leb128::encodeUnsigned(128);
        $this->assertSame(2, strlen($encoded));
        $reader = new BinaryReader($encoded);
        $this->assertSame(128, Leb128::decodeUnsigned($reader));
    }

    #[Test]
    public function testRoundtrip16383(): void
    {
        // 16383 = 0x3FFF, max value in 2 bytes (14 bits)
        $encoded = Leb128::encodeUnsigned(16383);
        $this->assertSame(2, strlen($encoded));
        $reader = new BinaryReader($encoded);
        $this->assertSame(16383, Leb128::decodeUnsigned($reader));
    }

    #[Test]
    public function testRoundtrip16384(): void
    {
        // 16384 = 0x4000, requires 3 bytes
        $encoded = Leb128::encodeUnsigned(16384);
        $this->assertSame(3, strlen($encoded));
        $reader = new BinaryReader($encoded);
        $this->assertSame(16384, Leb128::decodeUnsigned($reader));
    }

    #[Test]
    public function testRoundtripLargeValue(): void
    {
        $value = 1_000_000;
        $encoded = Leb128::encodeUnsigned($value);
        $reader = new BinaryReader($encoded);
        $this->assertSame($value, Leb128::decodeUnsigned($reader));
    }

    #[Test]
    public function testRoundtripVeryLargeValue(): void
    {
        $value = 2_147_483_647; // INT32_MAX
        $encoded = Leb128::encodeUnsigned($value);
        $reader = new BinaryReader($encoded);
        $this->assertSame($value, Leb128::decodeUnsigned($reader));
    }

    #[Test]
    public function testEncodeUnsignedProducesCorrectBytes(): void
    {
        // 624485 encodes to [0xE5, 0x8E, 0x26] in LEB128
        $encoded = Leb128::encodeUnsigned(624485);
        $this->assertSame("\xE5\x8E\x26", $encoded);
    }

    #[Test]
    public function testDecodeMultipleValuesInSequence(): void
    {
        $encoded = Leb128::encodeUnsigned(100) . Leb128::encodeUnsigned(200) . Leb128::encodeUnsigned(300);
        $reader = new BinaryReader($encoded);

        $this->assertSame(100, Leb128::decodeUnsigned($reader));
        $this->assertSame(200, Leb128::decodeUnsigned($reader));
        $this->assertSame(300, Leb128::decodeUnsigned($reader));
        $this->assertTrue($reader->isEof());
    }
}
