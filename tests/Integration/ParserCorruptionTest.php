<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Exceptions\CorruptObjectException;
use Pitmaster\Exceptions\PackParseException;
use Pitmaster\Object\ObjectId;
use Pitmaster\Pack\PackIndex;
use Pitmaster\Pack\PackIndexer;
use Pitmaster\Storage\LooseObjectStore;

final class ParserCorruptionTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-parser-corruption-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function looseObjectStoreRejectsInvalidCompressedData(): void
    {
        $store = new LooseObjectStore($this->tmpDir);
        $id = ObjectId::fromHex(str_repeat('1', 40));
        $path = $this->tmpDir . '/' . $id->prefix() . '/' . $id->suffix();
        mkdir(dirname($path), 0777, true);
        file_put_contents($path, 'not-a-zlib-stream');

        $this->expectException(CorruptObjectException::class);
        $this->expectExceptionMessage('zlib decompression failed');

        $store->read($id);
    }

    #[Test]
    public function looseObjectStoreRejectsHeaderSizeMismatch(): void
    {
        $store = new LooseObjectStore($this->tmpDir);
        $id = ObjectId::fromHex(str_repeat('2', 40));
        $path = $this->tmpDir . '/' . $id->prefix() . '/' . $id->suffix();
        mkdir(dirname($path), 0777, true);
        file_put_contents($path, zlib_encode("blob 99\0hello", ZLIB_ENCODING_DEFLATE));

        $this->expectException(CorruptObjectException::class);
        $this->expectExceptionMessage('size mismatch');

        $store->read($id);
    }

    #[Test]
    public function packIndexerRejectsInvalidMagic(): void
    {
        $packPath = $this->tmpDir . '/invalid.pack';
        file_put_contents($packPath, 'NOPE');

        $this->expectException(PackParseException::class);
        $this->expectExceptionMessage('Invalid pack file magic');

        PackIndexer::writeIndex($packPath);
    }

    #[Test]
    public function packIndexerRejectsTruncatedPackEntries(): void
    {
        $packPath = $this->tmpDir . '/truncated.pack';
        file_put_contents($packPath, 'PACK' . pack('N', 2) . pack('N', 1));

        $this->expectException(PackParseException::class);
        $this->expectExceptionMessage('Truncated pack file');

        PackIndexer::writeIndex($packPath);
    }

    #[Test]
    public function packIndexRejectsUnsupportedVersion(): void
    {
        $indexPath = $this->tmpDir . '/unsupported.idx';
        file_put_contents($indexPath, "\xFF\x74\x4F\x63" . pack('N', 3) . str_repeat("\0", 1024));

        $this->expectException(PackParseException::class);
        $this->expectExceptionMessage('Unsupported pack version 3');

        PackIndex::open($indexPath);
    }

    #[Test]
    public function packIndexRejectsTruncatedFile(): void
    {
        $indexPath = $this->tmpDir . '/truncated.idx';
        file_put_contents($indexPath, "\xFF\x74\x4F\x63" . pack('N', 2) . str_repeat("\0", 16));

        $this->expectException(PackParseException::class);
        $this->expectExceptionMessage('Truncated pack file');

        PackIndex::open($indexPath);
    }
}
