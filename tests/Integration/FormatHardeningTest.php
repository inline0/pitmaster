<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Encoding\BinaryReader;
use Pitmaster\Exceptions\CorruptObjectException;
use Pitmaster\Exceptions\PackParseException;
use Pitmaster\Index\Index;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;
use Pitmaster\Pack\CommitGraph;
use Pitmaster\Pack\MultiPackIndex;
use Pitmaster\Pack\PackIndex;
use Pitmaster\Pack\PackIndexer;
use Pitmaster\Ref\Reftable;
use Pitmaster\Storage\ObjectSerializer;
use Pitmaster\Tests\Support\Workspace;

final class FormatHardeningTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            Workspace::remove($path);
        }
    }

    #[Test]
    public function packIndexerRejectsInvalidObjectTypesAndMissingRefDeltaBases(): void
    {
        $invalidTypePack = $this->createFile('format-pack-', '.pack');
        file_put_contents(
            $invalidTypePack,
            $this->packFile(chr(0x50), gzdeflate('')),
        );

        try {
            PackIndexer::writeIndex($invalidTypePack);
            self::fail('Expected invalid pack type to be rejected');
        } catch (\Throwable $e) {
            self::assertStringContainsString('Unknown pack object type', $e->getMessage());
        }

        $missingBasePack = $this->createFile('format-pack-', '.pack');
        file_put_contents(
            $missingBasePack,
            $this->packFile(
                chr(0x72) . str_repeat("\0", 20),
                gzdeflate("\x00\x00"),
            ),
        );

        $this->expectException(PackParseException::class);
        $this->expectExceptionMessage('ref-delta base');

        PackIndexer::writeIndex($missingBasePack);
    }

    #[Test]
    public function packIndexRejectsTruncatedLargeOffsetTable(): void
    {
        $path = $this->createFile('format-idx-', '.idx');
        $fanout = '';

        for ($i = 0; $i < 256; $i++) {
            $fanout .= pack('N', 1);
        }

        file_put_contents(
            $path,
            "\xFF\x74\x4F\x63"
            . pack('N', 2)
            . $fanout
            . str_repeat("\0", 20)
            . pack('N', 0)
            . pack('N', 0x80000000),
        );

        $this->expectException(PackParseException::class);
        $this->expectExceptionMessage('Truncated pack file');

        PackIndex::open($path);
    }

    #[Test]
    public function commitGraphRejectsChunkOffsetsPastEndOfFile(): void
    {
        $path = $this->createFile('format-graph-');
        file_put_contents(
            $path,
            "CGPH"
            . chr(1)
            . chr(1)
            . chr(1)
            . chr(0)
            . pack('N', 0x4F494446)
            . $this->packUint64(512)
            . pack('N', 0)
            . $this->packUint64(0),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Seek position 512 out of bounds');

        CommitGraph::open($path);
    }

    #[Test]
    public function multiPackIndexRejectsChunkOffsetsPastEndOfFile(): void
    {
        $path = $this->createFile('format-midx-');
        file_put_contents(
            $path,
            "MIDX"
            . chr(1)
            . chr(1)
            . chr(1)
            . chr(0)
            . pack('N', 1)
            . pack('N', 0x504E414D)
            . $this->packUint64(1024)
            . pack('N', 0)
            . $this->packUint64(0),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Seek position 1024 out of bounds');

        MultiPackIndex::open($path);
    }

    #[Test]
    public function reftableSafelyIgnoresTruncatedRefBlocks(): void
    {
        $path = $this->createFile('format-reftable-');
        file_put_contents(
            $path,
            "REFT"
            . chr(1)
            . "\x00\x00\x20"
            . str_repeat("\0", 16)
            . chr(ord('r'))
            . "\x00\x00\x20"
            . "abc",
        );

        $table = Reftable::open($path);

        self::assertNotNull($table);
        self::assertSame([], $table->refs());
        self::assertSame([], $table->symrefs());
    }

    #[Test]
    public function objectSerializerRejectsMalformedCommitTreeAndTagPayloads(): void
    {
        $this->expectException(CorruptObjectException::class);
        $this->expectExceptionMessage('missing null byte in header');

        ObjectSerializer::decodeRaw('blob 4hello');
    }

    #[Test]
    public function malformedTypedObjectsFailClosed(): void
    {
        try {
            $content = "author Test <t@e> 1 +0000\n\nmsg";
            ObjectSerializer::decodeRaw('commit ' . strlen($content) . "\0" . $content);
            self::fail('Expected commit without tree to fail');
        } catch (CorruptObjectException $e) {
            self::assertStringContainsString('commit missing tree', $e->getMessage());
        }

        $treeContent = '100644' . "\0" . str_repeat("\0", 20);

        try {
            ObjectSerializer::parseTyped(
                ObjectType::Tree,
                $treeContent,
                ObjectId::compute(ObjectType::Tree, $treeContent),
            );
            self::fail('Expected malformed tree entry to fail');
        } catch (CorruptObjectException $e) {
            self::assertStringContainsString('tree entry missing space', $e->getMessage());
        }

        try {
            $content = "type commit\ntag v1\n";
            ObjectSerializer::decodeRaw('tag ' . strlen($content) . "\0" . $content);
            self::fail('Expected malformed tag to fail');
        } catch (CorruptObjectException $e) {
            self::assertStringContainsString('tag missing object', $e->getMessage());
        }
    }

    #[Test]
    public function indexParserRejectsTruncatedEntriesAndMalformedExtensions(): void
    {
        $truncatedEntry = 'DIRC' . pack('N', 2) . pack('N', 1) . str_repeat("\0", 20);

        try {
            Index::parse($truncatedEntry, 'truncated-entry');
            self::fail('Expected truncated index entry to fail');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Cannot read', $e->getMessage());
        }

        $malformedExtension = 'DIRC'
            . pack('N', 2)
            . pack('N', 0)
            . 'TREE'
            . pack('N', 30)
            . 'abc'
            . str_repeat("\0", 20);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot read 30 bytes');

        Index::parse($malformedExtension, 'malformed-extension');
    }

    #[Test]
    public function binaryReaderRejectsOutOfBoundsReadsAndSeeks(): void
    {
        $reader = new BinaryReader('abc');

        try {
            $reader->seek(8);
            self::fail('Expected out-of-bounds seek to fail');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Seek position 8 out of bounds', $e->getMessage());
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot read 10 bytes');

        $reader->read(10);
    }

    private function packFile(string $entryHeader, string $payload): string
    {
        return 'PACK'
            . pack('N', 2)
            . pack('N', 1)
            . $entryHeader
            . $payload
            . str_repeat("\0", 20);
    }

    private function packUint64(int $value): string
    {
        return pack('N2', ($value >> 32) & 0xFFFFFFFF, $value & 0xFFFFFFFF);
    }

    private function createFile(string $prefix, string $suffix = ''): string
    {
        $path = Workspace::createFile($prefix, $suffix);
        $this->paths[] = $path;

        return $path;
    }
}
