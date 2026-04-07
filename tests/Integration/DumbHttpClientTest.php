<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Protocol\DumbHttpClient;

final class DumbHttpClientTest extends TestCase
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
    public function canBeConstructedWithDefaultTimeout(): void
    {
        $client = new DumbHttpClient();

        $this->assertInstanceOf(DumbHttpClient::class, $client);
    }

    #[Test]
    public function canBeConstructedWithCustomTimeout(): void
    {
        $client = new DumbHttpClient(60);

        $this->assertInstanceOf(DumbHttpClient::class, $client);
    }
}
