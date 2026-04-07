<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Protocol;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Exceptions\ProtocolException;
use Pitmaster\Protocol\PktLine;

final class PktLineTest extends TestCase
{
    #[Test]
    public function testEncodeDecodeRoundtrip(): void
    {
        $payload = 'hello world';
        $encoded = PktLine::encode($payload);

        // Length should be payload + 4 hex digits
        $expectedLen = sprintf('%04x', strlen($payload) + 4);
        $this->assertStringStartsWith($expectedLen, $encoded);

        $decoded = PktLine::decode($encoded);
        $this->assertCount(1, $decoded);
        $this->assertSame('hello world', $decoded[0]);
    }

    #[Test]
    public function testFlushPacket(): void
    {
        $this->assertSame('0000', PktLine::flush());
    }

    #[Test]
    public function testDelimiterPacket(): void
    {
        $this->assertSame('0001', PktLine::delimiter());
    }

    #[Test]
    public function testDecodeFlushPacket(): void
    {
        $decoded = PktLine::decode('0000');
        $this->assertCount(1, $decoded);
        $this->assertNull($decoded[0]);
    }

    #[Test]
    public function testDecodeDelimiterPacket(): void
    {
        $decoded = PktLine::decode('0001');
        $this->assertCount(1, $decoded);
        $this->assertFalse($decoded[0]);
    }

    #[Test]
    public function testDecodeMultipleLines(): void
    {
        $data = PktLine::encode("line one\n") . PktLine::encode("line two\n");
        $decoded = PktLine::decode($data);

        $this->assertCount(2, $decoded);
        $this->assertSame('line one', $decoded[0]);
        $this->assertSame('line two', $decoded[1]);
    }

    #[Test]
    public function testDecodeWithFlushInMiddle(): void
    {
        $data = PktLine::encode("before\n") . PktLine::flush() . PktLine::encode("after\n");
        $decoded = PktLine::decode($data);

        $this->assertCount(3, $decoded);
        $this->assertSame('before', $decoded[0]);
        $this->assertNull($decoded[1]);
        $this->assertSame('after', $decoded[2]);
    }

    #[Test]
    public function testMaxLength(): void
    {
        $this->assertSame(65516, PktLine::MAX_PAYLOAD);
    }

    #[Test]
    public function testEncodeTooLongThrows(): void
    {
        $this->expectException(ProtocolException::class);
        // Create a payload that exceeds max length
        PktLine::encode(str_repeat('x', 65520));
    }

    #[Test]
    public function testEncodeShortPayload(): void
    {
        $encoded = PktLine::encode('a');
        // Length = 1 + 4 = 5 = "0005"
        $this->assertSame('0005a', $encoded);
    }

    #[Test]
    public function testDecodeStripsTrailingNewline(): void
    {
        $encoded = PktLine::encode("some data\n");
        $decoded = PktLine::decode($encoded);

        $this->assertSame('some data', $decoded[0]);
    }
}
