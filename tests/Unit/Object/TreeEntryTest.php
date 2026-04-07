<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Object;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\TreeEntry;

final class TreeEntryTest extends TestCase
{
    private function entry(string $mode): TreeEntry
    {
        return new TreeEntry($mode, 'test', ObjectId::zero());
    }

    #[Test]
    public function isBlobForRegularFile(): void
    {
        self::assertTrue($this->entry('100644')->isBlob());
    }

    #[Test]
    public function isTreeForMode40000(): void
    {
        self::assertTrue($this->entry('40000')->isTree());
    }

    #[Test]
    public function isTreeForMode040000(): void
    {
        self::assertTrue($this->entry('040000')->isTree());
    }

    #[Test]
    public function isExecutableForMode100755(): void
    {
        self::assertTrue($this->entry('100755')->isExecutable());
    }

    #[Test]
    public function isSymlinkForMode120000(): void
    {
        self::assertTrue($this->entry('120000')->isSymlink());
    }

    #[Test]
    public function isGitlinkForMode160000(): void
    {
        self::assertTrue($this->entry('160000')->isGitlink());
    }
}
