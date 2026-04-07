<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Protocol;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Protocol\Capability;

final class CapabilityTest extends TestCase
{
    #[Test]
    public function parseCapabilityString(): void
    {
        $cap = Capability::parse('multi_ack side-band-64k ofs-delta');

        self::assertTrue($cap->has('multi_ack'));
        self::assertTrue($cap->has('side-band-64k'));
        self::assertTrue($cap->has('ofs-delta'));
    }

    #[Test]
    public function hasReturnsFalseForMissing(): void
    {
        $cap = Capability::parse('multi_ack');

        self::assertFalse($cap->has('thin-pack'));
    }

    #[Test]
    public function getReturnsValueForKeyValue(): void
    {
        $cap = Capability::parse('symref=HEAD:refs/heads/main agent=git/2.45.0');

        self::assertSame('HEAD:refs/heads/main', $cap->get('symref'));
        self::assertSame('git/2.45.0', $cap->get('agent'));
    }

    #[Test]
    public function formatProducesString(): void
    {
        $cap = Capability::parse('multi_ack symref=HEAD:refs/heads/main');

        $formatted = $cap->format();

        self::assertStringContainsString('multi_ack', $formatted);
        self::assertStringContainsString('symref=HEAD:refs/heads/main', $formatted);
    }
}
