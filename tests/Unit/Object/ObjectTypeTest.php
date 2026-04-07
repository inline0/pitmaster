<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Object;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\ObjectType;

final class ObjectTypeTest extends TestCase
{
    #[Test]
    public function testFromStringValues(): void
    {
        $this->assertSame(ObjectType::Blob, ObjectType::from('blob'));
        $this->assertSame(ObjectType::Tree, ObjectType::from('tree'));
        $this->assertSame(ObjectType::Commit, ObjectType::from('commit'));
        $this->assertSame(ObjectType::Tag, ObjectType::from('tag'));
    }

    #[Test]
    public function testFromPackType(): void
    {
        $this->assertSame(ObjectType::Commit, ObjectType::fromPackType(1));
        $this->assertSame(ObjectType::Tree, ObjectType::fromPackType(2));
        $this->assertSame(ObjectType::Blob, ObjectType::fromPackType(3));
        $this->assertSame(ObjectType::Tag, ObjectType::fromPackType(4));
    }

    #[Test]
    public function testToPackType(): void
    {
        $this->assertSame(1, ObjectType::Commit->toPackType());
        $this->assertSame(2, ObjectType::Tree->toPackType());
        $this->assertSame(3, ObjectType::Blob->toPackType());
        $this->assertSame(4, ObjectType::Tag->toPackType());
    }

    #[Test]
    public function testFromPackTypeInvalidThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ObjectType::fromPackType(5);
    }

    #[Test]
    public function testFromPackTypeZeroThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ObjectType::fromPackType(0);
    }

    #[Test]
    public function testPackTypeRoundtrip(): void
    {
        foreach (ObjectType::cases() as $type) {
            $packType = $type->toPackType();
            $this->assertSame($type, ObjectType::fromPackType($packType));
        }
    }

    #[Test]
    public function testStringValues(): void
    {
        $this->assertSame('blob', ObjectType::Blob->value);
        $this->assertSame('tree', ObjectType::Tree->value);
        $this->assertSame('commit', ObjectType::Commit->value);
        $this->assertSame('tag', ObjectType::Tag->value);
    }
}
