<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Object;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\Blob;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;

final class GitObjectTest extends TestCase
{
    #[Test]
    public function testSizeReturnsContentLength(): void
    {
        $blob = Blob::fromContent('hello world');

        $this->assertSame(11, $blob->size());
    }

    #[Test]
    public function testSizeForEmptyContent(): void
    {
        $blob = Blob::fromContent('');

        $this->assertSame(0, $blob->size());
    }

    #[Test]
    public function testTypeIsBlobForBlob(): void
    {
        $blob = Blob::fromContent('test content');

        $this->assertSame(ObjectType::Blob, $blob->type);
    }

    #[Test]
    public function testIdIsComputedCorrectly(): void
    {
        $content = 'hello world';
        $blob = Blob::fromContent($content);

        $expectedHash = sha1("blob 11\0hello world");
        $this->assertSame($expectedHash, $blob->id->hex);
    }

    #[Test]
    public function testContentIsStored(): void
    {
        $content = "line1\nline2\nline3";
        $blob = Blob::fromContent($content);

        $this->assertSame($content, $blob->content);
    }

    #[Test]
    public function testBlobFromContentWithAlgo(): void
    {
        $content = 'test';
        $blob = Blob::fromContent($content, 'sha256');

        $expectedHash = hash('sha256', "blob 4\0test");
        $this->assertSame($expectedHash, $blob->id->hex);
        $this->assertTrue($blob->id->isSha256());
    }

    #[Test]
    public function testBlobConstructorDirectly(): void
    {
        $id = ObjectId::fromHex(str_repeat('a', 40));
        $blob = new Blob('raw content', $id);

        $this->assertSame('raw content', $blob->content);
        $this->assertSame($id, $blob->id);
        $this->assertSame(ObjectType::Blob, $blob->type);
    }
}
