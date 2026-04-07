<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\Blob;
use Pitmaster\Storage\ObjectSerializer;

final class ObjectSerializerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        exec(sprintf('cd %s && git init && git config user.email t@t.com && git config user.name T 2>&1', escapeshellarg($this->tmpDir)));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function encodeRawProducesCorrectFormat(): void
    {
        $blob = Blob::fromContent("test content");
        $raw = ObjectSerializer::encodeRaw($blob);

        $this->assertSame("blob 12\0test content", $raw);
    }

    #[Test]
    public function encodeRawEmptyBlob(): void
    {
        $blob = Blob::fromContent('');
        $raw = ObjectSerializer::encodeRaw($blob);

        $this->assertSame("blob 0\0", $raw);
    }

    #[Test]
    public function decodeRawRoundTrip(): void
    {
        $blob = Blob::fromContent("round trip test\n");
        $raw = ObjectSerializer::encodeRaw($blob);
        $decoded = ObjectSerializer::decodeRaw($raw);

        $this->assertInstanceOf(Blob::class, $decoded);
        $this->assertSame("round trip test\n", $decoded->content);
        $this->assertSame($blob->id->hex, $decoded->id->hex);
    }

    #[Test]
    public function encodeDecodeWithZlibRoundTrip(): void
    {
        $blob = Blob::fromContent("compressed round trip\n");
        $compressed = ObjectSerializer::encode($blob);

        // Compressed data should differ from raw
        $raw = ObjectSerializer::encodeRaw($blob);
        $this->assertNotSame($raw, $compressed);

        // Decode should recover original
        $decoded = ObjectSerializer::decode($compressed);
        $this->assertInstanceOf(Blob::class, $decoded);
        $this->assertSame("compressed round trip\n", $decoded->content);
        $this->assertSame($blob->id->hex, $decoded->id->hex);
    }

    #[Test]
    public function decodeWithExpectedHashValidation(): void
    {
        $blob = Blob::fromContent("hash check\n");
        $compressed = ObjectSerializer::encode($blob);

        // Correct hash should succeed
        $decoded = ObjectSerializer::decode($compressed, $blob->id->hex);
        $this->assertSame($blob->id->hex, $decoded->id->hex);
    }

    #[Test]
    public function decodeWithWrongHashThrows(): void
    {
        $blob = Blob::fromContent("hash check\n");
        $compressed = ObjectSerializer::encode($blob);

        $this->expectException(\Pitmaster\Exceptions\CorruptObjectException::class);
        ObjectSerializer::decode($compressed, str_repeat('00', 20));
    }

    #[Test]
    public function binaryContentRoundTrip(): void
    {
        $binary = "\x00\x01\x02\xFF\xFE\xFD";
        $blob = Blob::fromContent($binary);
        $compressed = ObjectSerializer::encode($blob);
        $decoded = ObjectSerializer::decode($compressed);

        $this->assertSame($binary, $decoded->content);
    }
}
