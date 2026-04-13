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

    #[Test]
    public function parseAdvertisementSkipsServiceBannerAndPreservesCapabilities(): void
    {
        $head = str_repeat('ab', 20);
        $feature = str_repeat('cd', 20);
        $advertisement = ''
            . "001e# service=git-upload-pack\n"
            . "0000"
            . sprintf('%04x', strlen("{$head} HEAD\0symref=HEAD:refs/heads/main multi_ack\n") + 4)
            . "{$head} HEAD\0symref=HEAD:refs/heads/main multi_ack\n"
            . sprintf('%04x', strlen("{$feature} refs/heads/feature\n") + 4)
            . "{$feature} refs/heads/feature\n"
            . "0000";

        $discovery = RefDiscovery::parseAdvertisement($advertisement);

        self::assertSame('refs/heads/main', $discovery->headSymref());
        self::assertSame($head, $discovery->ref('HEAD')?->hex);
        self::assertSame($feature, $discovery->ref('refs/heads/feature')?->hex);
        self::assertTrue($discovery->capabilities()?->has('multi_ack') ?? false);
    }

    #[Test]
    public function parseAdvertisementSupportsSha256Hashes(): void
    {
        $hash = str_repeat('ab', 32);
        $advertisement = sprintf('%04x', strlen("{$hash} refs/heads/main\n") + 4)
            . "{$hash} refs/heads/main\n"
            . "0000";

        $discovery = RefDiscovery::parseAdvertisement($advertisement);

        self::assertSame($hash, $discovery->ref('refs/heads/main')?->hex);
    }
}
