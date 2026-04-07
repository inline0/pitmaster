<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Lfs;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Lfs\LfsPointer;

final class LfsPointerTest extends TestCase
{
    private const VALID_POINTER = "version https://git-lfs.github.com/spec/v1\n"
        . "oid sha256:4d7a214614ab2935c943f9e0ff69d22eadbb8f32b1258daaa5e2ca24d17e2393\n"
        . "size 12345\n";

    #[Test]
    public function parseValidPointerContent(): void
    {
        $pointer = LfsPointer::parse(self::VALID_POINTER);

        self::assertNotNull($pointer);
        self::assertSame('4d7a214614ab2935c943f9e0ff69d22eadbb8f32b1258daaa5e2ca24d17e2393', $pointer->oid);
        self::assertSame(12345, $pointer->size);
        self::assertSame('https://git-lfs.github.com/spec/v1', $pointer->version);
    }

    #[Test]
    public function parseReturnsNullForNonPointer(): void
    {
        self::assertNull(LfsPointer::parse('just some file content'));
    }

    #[Test]
    public function isPointerDetection(): void
    {
        self::assertTrue(LfsPointer::isPointer(self::VALID_POINTER));
        self::assertFalse(LfsPointer::isPointer('not a pointer'));
    }

    #[Test]
    public function serializeRoundtrip(): void
    {
        $pointer = LfsPointer::parse(self::VALID_POINTER);
        self::assertNotNull($pointer);

        $serialized = $pointer->serialize();
        $reparsed = LfsPointer::parse($serialized);

        self::assertNotNull($reparsed);
        self::assertSame($pointer->oid, $reparsed->oid);
        self::assertSame($pointer->size, $reparsed->size);
        self::assertSame($pointer->version, $reparsed->version);
    }

    #[Test]
    public function forContentHashesWithSha256(): void
    {
        $content = 'hello world';
        $pointer = LfsPointer::forContent($content);

        self::assertSame(hash('sha256', $content), $pointer->oid);
        self::assertSame(strlen($content), $pointer->size);
    }
}
