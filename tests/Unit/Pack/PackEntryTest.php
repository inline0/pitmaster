<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Pack;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\ObjectType;
use Pitmaster\Pack\PackEntry;

final class PackEntryTest extends TestCase
{
    #[Test]
    public function testIsDeltaForOfsDelta(): void
    {
        $entry = new PackEntry(
            packType: PackEntry::TYPE_OFS_DELTA,
            uncompressedSize: 100,
            dataOffset: 12,
            entryOffset: 0,
            baseOffset: 50,
        );

        $this->assertTrue($entry->isDelta());
        $this->assertTrue($entry->isOfsDelta());
        $this->assertFalse($entry->isRefDelta());
    }

    #[Test]
    public function testIsDeltaForRefDelta(): void
    {
        $entry = new PackEntry(
            packType: PackEntry::TYPE_REF_DELTA,
            uncompressedSize: 200,
            dataOffset: 32,
            entryOffset: 10,
            baseHash: str_repeat('a', 40),
        );

        $this->assertTrue($entry->isDelta());
        $this->assertFalse($entry->isOfsDelta());
        $this->assertTrue($entry->isRefDelta());
    }

    #[Test]
    public function testIsDeltaFalseForBaseType(): void
    {
        // packType 3 = Blob
        $entry = new PackEntry(
            packType: 3,
            uncompressedSize: 50,
            dataOffset: 12,
            entryOffset: 0,
        );

        $this->assertFalse($entry->isDelta());
        $this->assertFalse($entry->isOfsDelta());
        $this->assertFalse($entry->isRefDelta());
    }

    #[Test]
    public function testObjectTypeForCommit(): void
    {
        $entry = new PackEntry(packType: 1, uncompressedSize: 100, dataOffset: 12, entryOffset: 0);

        $this->assertSame(ObjectType::Commit, $entry->objectType());
    }

    #[Test]
    public function testObjectTypeForTree(): void
    {
        $entry = new PackEntry(packType: 2, uncompressedSize: 100, dataOffset: 12, entryOffset: 0);

        $this->assertSame(ObjectType::Tree, $entry->objectType());
    }

    #[Test]
    public function testObjectTypeForBlob(): void
    {
        $entry = new PackEntry(packType: 3, uncompressedSize: 100, dataOffset: 12, entryOffset: 0);

        $this->assertSame(ObjectType::Blob, $entry->objectType());
    }

    #[Test]
    public function testObjectTypeForTag(): void
    {
        $entry = new PackEntry(packType: 4, uncompressedSize: 100, dataOffset: 12, entryOffset: 0);

        $this->assertSame(ObjectType::Tag, $entry->objectType());
    }

    #[Test]
    public function testObjectTypeNullForOfsDelta(): void
    {
        $entry = new PackEntry(
            packType: PackEntry::TYPE_OFS_DELTA,
            uncompressedSize: 100,
            dataOffset: 12,
            entryOffset: 0,
            baseOffset: 50,
        );

        $this->assertNull($entry->objectType());
    }

    #[Test]
    public function testObjectTypeNullForRefDelta(): void
    {
        $entry = new PackEntry(
            packType: PackEntry::TYPE_REF_DELTA,
            uncompressedSize: 100,
            dataOffset: 12,
            entryOffset: 0,
            baseHash: str_repeat('b', 40),
        );

        $this->assertNull($entry->objectType());
    }
}
