<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Encoding\BinaryReader;
use Pitmaster\Pack\PackIndexV1;

final class PackIndexV1Test extends TestCase
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

    /**
     * Build a minimal v1 index with one object.
     * V1 format: 256 x 4-byte fanout + N x (4-byte offset + 20-byte hash) + checksums
     */
    private function buildV1Index(string $hex, int $offset): string
    {
        $binary = hex2bin($hex);
        $firstByte = ord($binary[0]);

        // Build fanout: all entries are 0 until the first-byte bucket, then 1
        $fanout = '';
        for ($i = 0; $i < 256; $i++) {
            $count = ($i >= $firstByte) ? 1 : 0;
            $fanout .= pack('N', $count);
        }

        // One entry: offset + hash
        $entry = pack('N', $offset) . $binary;

        // Pack and index checksums (20 bytes each, just zeros for our test)
        $checksums = str_repeat("\x00", 40);

        return $fanout . $entry . $checksums;
    }

    #[Test]
    public function parseAndObjectCount(): void
    {
        $hex = 'aabbccddee' . str_repeat('ff', 15);
        $data = $this->buildV1Index($hex, 12);
        $reader = new BinaryReader($data);

        $index = PackIndexV1::parse($reader);

        $this->assertSame(1, $index->objectCount());
    }

    #[Test]
    public function findOffsetReturnsCorrectOffset(): void
    {
        $hex = 'aabbccddee' . str_repeat('ff', 15);
        $data = $this->buildV1Index($hex, 42);
        $reader = new BinaryReader($data);

        $index = PackIndexV1::parse($reader);

        $this->assertSame(42, $index->findOffset($hex));
    }

    #[Test]
    public function findOffsetReturnsNullForMissing(): void
    {
        $hex = 'aabbccddee' . str_repeat('ff', 15);
        $data = $this->buildV1Index($hex, 42);
        $reader = new BinaryReader($data);

        $index = PackIndexV1::parse($reader);

        $this->assertNull($index->findOffset(str_repeat('00', 20)));
    }

    #[Test]
    public function allHashesReturnsKnownHash(): void
    {
        $hex = 'aabbccddee' . str_repeat('ff', 15);
        $data = $this->buildV1Index($hex, 100);
        $reader = new BinaryReader($data);

        $index = PackIndexV1::parse($reader);
        $hashes = $index->allHashes();

        $this->assertCount(1, $hashes);
        $this->assertSame($hex, $hashes[0]);
    }
}
