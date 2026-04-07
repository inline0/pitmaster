<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Config\ConfigEntry;

final class ConfigEntryTest extends TestCase
{
    #[Test]
    public function testConstructorSetsProperties(): void
    {
        $entry = new ConfigEntry('core', null, 'bare', 'false');

        $this->assertSame('core', $entry->section);
        $this->assertNull($entry->subsection);
        $this->assertSame('bare', $entry->key);
        $this->assertSame('false', $entry->value);
    }

    #[Test]
    public function testConstructorWithSubsection(): void
    {
        $entry = new ConfigEntry('remote', 'origin', 'url', 'https://example.com');

        $this->assertSame('remote', $entry->section);
        $this->assertSame('origin', $entry->subsection);
        $this->assertSame('url', $entry->key);
        $this->assertSame('https://example.com', $entry->value);
    }

    #[Test]
    public function testQualifiedKeyWithoutSubsection(): void
    {
        $entry = new ConfigEntry('core', null, 'bare', 'false');

        $this->assertSame('core.bare', $entry->qualifiedKey());
    }

    #[Test]
    public function testQualifiedKeyWithSubsection(): void
    {
        $entry = new ConfigEntry('remote', 'origin', 'url', 'https://example.com');

        $this->assertSame('remote.origin.url', $entry->qualifiedKey());
    }

    #[Test]
    public function testQualifiedKeyWithBranchSubsection(): void
    {
        $entry = new ConfigEntry('branch', 'main', 'merge', 'refs/heads/main');

        $this->assertSame('branch.main.merge', $entry->qualifiedKey());
    }
}
