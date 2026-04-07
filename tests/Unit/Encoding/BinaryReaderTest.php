<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Encoding;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Encoding\BinaryReader;
use RuntimeException;

final class BinaryReaderTest extends TestCase
{
    #[Test]
    public function testReadBytes(): void
    {
        $reader = new BinaryReader("Hello, World!");
        $this->assertSame('Hello', $reader->read(5));
        $this->assertSame(', ', $reader->read(2));
        $this->assertSame('World!', $reader->read(6));
    }

    #[Test]
    public function testReadUint32(): void
    {
        // Pack 0x01020304 as big-endian
        $data = pack('N', 0x01020304);
        $reader = new BinaryReader($data);
        $this->assertSame(0x01020304, $reader->readUint32());
    }

    #[Test]
    public function testReadUint16(): void
    {
        $data = pack('n', 0x0102);
        $reader = new BinaryReader($data);
        $this->assertSame(0x0102, $reader->readUint16());
    }

    #[Test]
    public function testReadNullTerminated(): void
    {
        $reader = new BinaryReader("blob 42\0content here");
        $this->assertSame('blob 42', $reader->readNullTerminated());
        $this->assertSame('content here', $reader->read(12));
    }

    #[Test]
    public function testReadNullTerminatedThrowsWhenNoNull(): void
    {
        $reader = new BinaryReader('no null byte here');
        $this->expectException(RuntimeException::class);
        $reader->readNullTerminated();
    }

    #[Test]
    public function testReadHash20(): void
    {
        $hex = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';
        $binary = hex2bin($hex);
        $reader = new BinaryReader($binary);
        $this->assertSame($hex, $reader->readHash20());
    }

    #[Test]
    public function testSeek(): void
    {
        $reader = new BinaryReader('ABCDEFGH');
        $reader->seek(4);
        $this->assertSame(4, $reader->position());
        $this->assertSame('E', $reader->read(1));
    }

    #[Test]
    public function testSeekOutOfBoundsThrows(): void
    {
        $reader = new BinaryReader('ABC');
        $this->expectException(RuntimeException::class);
        $reader->seek(10);
    }

    #[Test]
    public function testSkip(): void
    {
        $reader = new BinaryReader('ABCDEFGH');
        $reader->skip(3);
        $this->assertSame(3, $reader->position());
        $this->assertSame('D', $reader->read(1));
    }

    #[Test]
    public function testRemaining(): void
    {
        $reader = new BinaryReader('ABCDE');
        $this->assertSame(5, $reader->remaining());
        $reader->read(2);
        $this->assertSame(3, $reader->remaining());
    }

    #[Test]
    public function testIsEof(): void
    {
        $reader = new BinaryReader('AB');
        $this->assertFalse($reader->isEof());
        $reader->read(2);
        $this->assertTrue($reader->isEof());
    }

    #[Test]
    public function testPeek(): void
    {
        $reader = new BinaryReader('ABCDE');
        $this->assertSame('AB', $reader->peek(2));
        // Position should not have changed
        $this->assertSame(0, $reader->position());
        $this->assertSame('AB', $reader->read(2));
    }

    #[Test]
    public function testReadPastEndThrowsRuntimeException(): void
    {
        $reader = new BinaryReader('AB');
        $this->expectException(RuntimeException::class);
        $reader->read(5);
    }

    #[Test]
    public function testPeekPastEndThrowsRuntimeException(): void
    {
        $reader = new BinaryReader('AB');
        $this->expectException(RuntimeException::class);
        $reader->peek(5);
    }

    #[Test]
    public function testReadByte(): void
    {
        $reader = new BinaryReader("\x42\xFF");
        $this->assertSame(0x42, $reader->readByte());
        $this->assertSame(0xFF, $reader->readByte());
    }

    #[Test]
    public function testLength(): void
    {
        $reader = new BinaryReader('ABCDE');
        $this->assertSame(5, $reader->length());
    }

    #[Test]
    public function testEmptyReader(): void
    {
        $reader = new BinaryReader('');
        $this->assertTrue($reader->isEof());
        $this->assertSame(0, $reader->remaining());
        $this->assertSame(0, $reader->length());
    }

    #[Test]
    public function testRawData(): void
    {
        $data = "some binary\x00data";
        $reader = new BinaryReader($data);
        $reader->read(5);
        $this->assertSame($data, $reader->rawData());
    }

    #[Test]
    public function testRemainingData(): void
    {
        $reader = new BinaryReader('ABCDE');
        $reader->read(2);
        $this->assertSame('CDE', $reader->remainingData());
    }
}
