<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Exceptions;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Exceptions\CorruptObjectException;
use Pitmaster\Exceptions\IndexParseException;
use Pitmaster\Exceptions\MergeConflictException;
use Pitmaster\Exceptions\ObjectNotFoundException;
use Pitmaster\Exceptions\PackParseException;
use Pitmaster\Exceptions\ProtocolException;

final class ExceptionsTest extends TestCase
{
    #[Test]
    public function testObjectNotFoundForHash(): void
    {
        $hash = str_repeat('a', 40);
        $e = ObjectNotFoundException::forHash($hash);

        $this->assertInstanceOf(ObjectNotFoundException::class, $e);
        $this->assertStringContainsString($hash, $e->getMessage());
    }

    #[Test]
    public function testCorruptObjectInvalidHeader(): void
    {
        $hash = str_repeat('b', 40);
        $e = CorruptObjectException::invalidHeader($hash);

        $this->assertInstanceOf(CorruptObjectException::class, $e);
        $this->assertStringContainsString($hash, $e->getMessage());
        $this->assertStringContainsString('header', $e->getMessage());
    }

    #[Test]
    public function testCorruptObjectInvalidHeaderWithDetail(): void
    {
        $hash = str_repeat('c', 40);
        $e = CorruptObjectException::invalidHeader($hash, 'missing null byte');

        $this->assertStringContainsString('missing null byte', $e->getMessage());
    }

    #[Test]
    public function testCorruptObjectHashMismatch(): void
    {
        $expected = str_repeat('a', 40);
        $actual = str_repeat('b', 40);
        $e = CorruptObjectException::hashMismatch($expected, $actual);

        $this->assertInstanceOf(CorruptObjectException::class, $e);
        $this->assertStringContainsString($expected, $e->getMessage());
        $this->assertStringContainsString($actual, $e->getMessage());
    }

    #[Test]
    public function testCorruptObjectInvalidContent(): void
    {
        $hash = str_repeat('d', 40);
        $e = CorruptObjectException::invalidContent($hash, 'unexpected EOF');

        $this->assertInstanceOf(CorruptObjectException::class, $e);
        $this->assertStringContainsString($hash, $e->getMessage());
        $this->assertStringContainsString('unexpected EOF', $e->getMessage());
    }

    #[Test]
    public function testPackParseExceptionInvalidMagic(): void
    {
        $e = PackParseException::invalidMagic('/path/to/pack');

        $this->assertInstanceOf(PackParseException::class, $e);
        $this->assertStringContainsString('/path/to/pack', $e->getMessage());
        $this->assertStringContainsString('magic', $e->getMessage());
    }

    #[Test]
    public function testPackParseExceptionDeltaChainTooDeep(): void
    {
        $e = PackParseException::deltaChainTooDeep(60, 50);

        $this->assertInstanceOf(PackParseException::class, $e);
        $this->assertStringContainsString('60', $e->getMessage());
        $this->assertStringContainsString('50', $e->getMessage());
    }

    #[Test]
    public function testPackParseExceptionTruncated(): void
    {
        $e = PackParseException::truncated('/path/to/pack', 'unexpected end of data');

        $this->assertInstanceOf(PackParseException::class, $e);
        $this->assertStringContainsString('/path/to/pack', $e->getMessage());
        $this->assertStringContainsString('unexpected end of data', $e->getMessage());
    }

    #[Test]
    public function testIndexParseExceptionInvalidMagic(): void
    {
        $e = IndexParseException::invalidMagic('/path/to/index');

        $this->assertInstanceOf(IndexParseException::class, $e);
        $this->assertStringContainsString('/path/to/index', $e->getMessage());
    }

    #[Test]
    public function testIndexParseExceptionChecksumMismatch(): void
    {
        $e = IndexParseException::checksumMismatch('/path/to/index');

        $this->assertInstanceOf(IndexParseException::class, $e);
        $this->assertStringContainsString('checksum', $e->getMessage());
        $this->assertStringContainsString('/path/to/index', $e->getMessage());
    }

    #[Test]
    public function testMergeConflictExceptionWithPaths(): void
    {
        $paths = ['file1.txt', 'file2.txt'];
        $e = new MergeConflictException($paths);

        $this->assertInstanceOf(MergeConflictException::class, $e);
        $this->assertSame($paths, $e->conflictPaths);
        $this->assertStringContainsString('file1.txt', $e->getMessage());
        $this->assertStringContainsString('file2.txt', $e->getMessage());
    }

    #[Test]
    public function testMergeConflictExceptionWithCustomMessage(): void
    {
        $e = new MergeConflictException(['a.txt'], 'Custom conflict message');

        $this->assertSame('Custom conflict message', $e->getMessage());
        $this->assertSame(['a.txt'], $e->conflictPaths);
    }

    #[Test]
    public function testProtocolExceptionWithMessage(): void
    {
        $e = new ProtocolException('Server returned error 403');

        $this->assertInstanceOf(ProtocolException::class, $e);
        $this->assertSame('Server returned error 403', $e->getMessage());
    }
}
