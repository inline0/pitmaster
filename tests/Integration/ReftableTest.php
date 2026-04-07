<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Ref\Reftable;

final class ReftableTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        exec(sprintf('cd %s && git init && git config user.email t@t.com && git config user.name T 2>&1', escapeshellarg($this->tmpDir)));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function openOnNonexistentFileReturnsNull(): void
    {
        $result = Reftable::open($this->tmpDir . '/nonexistent-reftable');

        $this->assertNull($result);
    }

    #[Test]
    public function openOnTooSmallFileReturnsNull(): void
    {
        $path = $this->tmpDir . '/tiny-reftable';
        file_put_contents($path, 'REFT');

        $result = Reftable::open($path);

        $this->assertNull($result);
    }

    #[Test]
    public function openOnInvalidMagicReturnsEmptyRefs(): void
    {
        $path = $this->tmpDir . '/bad-reftable';
        file_put_contents($path, str_repeat("\x00", 100));

        $result = Reftable::open($path);

        // Should return a Reftable with empty refs (not null, because file exists and is big enough)
        $this->assertNotNull($result);
        $this->assertEmpty($result->refs());
    }
}
