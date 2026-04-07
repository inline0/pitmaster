<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Object;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;

final class ObjectIdTest extends TestCase
{
    private const SHA1_HEX = 'da39a3ee5e6b4b0d3255bfef95601890afd80709';
    private const SHA256_HEX = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

    #[Test]
    public function testFromHex(): void
    {
        $id = ObjectId::fromHex(self::SHA1_HEX);
        $this->assertSame(self::SHA1_HEX, $id->hex);
        $this->assertSame(20, strlen($id->binary));
        $this->assertSame('sha1', $id->algo);
    }

    #[Test]
    public function testFromHexUppercase(): void
    {
        $id = ObjectId::fromHex(strtoupper(self::SHA1_HEX));
        $this->assertSame(self::SHA1_HEX, $id->hex);
    }

    #[Test]
    public function testFromBinary(): void
    {
        $binary = hex2bin(self::SHA1_HEX);
        $id = ObjectId::fromBinary($binary);
        $this->assertSame(self::SHA1_HEX, $id->hex);
        $this->assertSame($binary, $id->binary);
        $this->assertSame('sha1', $id->algo);
    }

    #[Test]
    public function testComputeSha1(): void
    {
        $content = 'hello world';
        $id = ObjectId::compute(ObjectType::Blob, $content, 'sha1');

        // Manually compute expected hash: sha1("blob 11\0hello world")
        $expected = sha1("blob 11\0hello world");
        $this->assertSame($expected, $id->hex);
        $this->assertSame('sha1', $id->algo);
    }

    #[Test]
    public function testComputeSha256(): void
    {
        $content = 'hello world';
        $id = ObjectId::compute(ObjectType::Blob, $content, 'sha256');

        $expected = hash('sha256', "blob 11\0hello world");
        $this->assertSame($expected, $id->hex);
        $this->assertTrue($id->isSha256());
    }

    #[Test]
    public function testPrefix(): void
    {
        $id = ObjectId::fromHex(self::SHA1_HEX);
        $this->assertSame('da', $id->prefix());
    }

    #[Test]
    public function testSuffix(): void
    {
        $id = ObjectId::fromHex(self::SHA1_HEX);
        $this->assertSame('39a3ee5e6b4b0d3255bfef95601890afd80709', $id->suffix());
    }

    #[Test]
    public function testEquals(): void
    {
        $a = ObjectId::fromHex(self::SHA1_HEX);
        $b = ObjectId::fromHex(self::SHA1_HEX);
        $c = ObjectId::fromHex(str_repeat('0', 40));

        $this->assertTrue($a->equals($b));
        $this->assertFalse($a->equals($c));
    }

    #[Test]
    public function testZero(): void
    {
        $zero = ObjectId::zero();
        $this->assertSame(str_repeat('0', 40), $zero->hex);
        $this->assertTrue($zero->isZero());
    }

    #[Test]
    public function testZeroSha256(): void
    {
        $zero = ObjectId::zero('sha256');
        $this->assertSame(str_repeat('0', 64), $zero->hex);
        $this->assertTrue($zero->isZero());
        $this->assertTrue($zero->isSha256());
    }

    #[Test]
    public function testIsZeroReturnsFalseForNonZero(): void
    {
        $id = ObjectId::fromHex(self::SHA1_HEX);
        $this->assertFalse($id->isZero());
    }

    #[Test]
    public function testIsSha256(): void
    {
        $sha1 = ObjectId::fromHex(self::SHA1_HEX);
        $sha256 = ObjectId::fromHex(self::SHA256_HEX);

        $this->assertFalse($sha1->isSha256());
        $this->assertTrue($sha256->isSha256());
    }

    #[Test]
    public function testToString(): void
    {
        $id = ObjectId::fromHex(self::SHA1_HEX);
        $this->assertSame(self::SHA1_HEX, (string) $id);
    }

    #[Test]
    public function testFromBinarySha256(): void
    {
        $binary = hex2bin(self::SHA256_HEX);
        $id = ObjectId::fromBinary($binary);
        $this->assertSame(self::SHA256_HEX, $id->hex);
        $this->assertSame('sha256', $id->algo);
        $this->assertSame(32, $id->hashLength());
    }

    #[Test]
    public function testHashLength(): void
    {
        $sha1 = ObjectId::fromHex(self::SHA1_HEX);
        $this->assertSame(20, $sha1->hashLength());

        $sha256 = ObjectId::fromHex(self::SHA256_HEX);
        $this->assertSame(32, $sha256->hashLength());
    }
}
