<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Protocol;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Protocol\RefDiscovery;

final class RefDiscoveryTest extends TestCase
{
    #[Test]
    public function parseArrayOfHashRefnameLines(): void
    {
        $hash = str_repeat('ab', 20);
        $lines = [
            "{$hash} refs/heads/main",
        ];

        $discovery = RefDiscovery::parse($lines);

        self::assertArrayHasKey('refs/heads/main', $discovery->refs());
        self::assertSame($hash, $discovery->refs()['refs/heads/main']->hex);
    }

    #[Test]
    public function firstLineWithNulAndCapabilities(): void
    {
        $hash = str_repeat('ab', 20);
        $lines = [
            "{$hash} HEAD\0multi_ack side-band-64k symref=HEAD:refs/heads/main",
        ];

        $discovery = RefDiscovery::parse($lines);

        self::assertNotNull($discovery->capabilities());
        self::assertTrue($discovery->capabilities()->has('multi_ack'));
        self::assertTrue($discovery->capabilities()->has('side-band-64k'));
    }

    #[Test]
    public function refsReturnsArray(): void
    {
        $hash1 = str_repeat('ab', 20);
        $hash2 = str_repeat('cd', 20);
        $lines = [
            "{$hash1} refs/heads/main",
            "{$hash2} refs/heads/feature",
        ];

        $discovery = RefDiscovery::parse($lines);

        self::assertCount(2, $discovery->refs());
    }

    #[Test]
    public function parseSupportsSha256Hashes(): void
    {
        $hash = str_repeat('ab', 32);
        $lines = [
            "{$hash} refs/heads/main",
        ];

        $discovery = RefDiscovery::parse($lines);

        self::assertArrayHasKey('refs/heads/main', $discovery->refs());
        self::assertSame($hash, $discovery->refs()['refs/heads/main']->hex);
    }

    #[Test]
    public function headSymrefExtractionFromCapability(): void
    {
        $hash = str_repeat('ab', 20);
        $lines = [
            "{$hash} HEAD\0symref=HEAD:refs/heads/main",
        ];

        $discovery = RefDiscovery::parse($lines);

        self::assertSame('refs/heads/main', $discovery->headSymref());
    }
}
