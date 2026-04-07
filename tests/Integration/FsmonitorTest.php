<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Status\Fsmonitor;

final class FsmonitorTest extends TestCase
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

    private function writeFile(string $p, string $c): void
    {
        $d = $this->tmpDir . '/' . $p;
        if (!is_dir(dirname($d))) {
            mkdir(dirname($d), 0777, true);
        }
        file_put_contents($d, $c);
    }

    #[Test]
    public function isEnabledReturnsFalseByDefault(): void
    {
        $fsmon = new Fsmonitor($this->tmpDir . '/.git', $this->tmpDir);

        $this->assertFalse($fsmon->isEnabled());
    }

    #[Test]
    public function queryReturnsChangedFiles(): void
    {
        $fsmon = new Fsmonitor($this->tmpDir . '/.git', $this->tmpDir);

        // Create files
        $this->writeFile('a.txt', "alpha\n");
        $this->writeFile('b.txt', "beta\n");

        $result = $fsmon->query('0');
        $this->assertArrayHasKey('files', $result);
        $this->assertArrayHasKey('token', $result);
        $this->assertContains('a.txt', $result['files']);
        $this->assertContains('b.txt', $result['files']);
    }

    #[Test]
    public function saveTokenAndLastTokenRoundTrip(): void
    {
        $fsmon = new Fsmonitor($this->tmpDir . '/.git', $this->tmpDir);

        // Initially no token
        $this->assertNull($fsmon->lastToken());

        // Save a token
        $token = (string) time();
        $fsmon->saveToken($token);

        $this->assertSame($token, $fsmon->lastToken());
    }

    #[Test]
    public function queryWithNullTokenReturnsAllFiles(): void
    {
        $this->writeFile('file.txt', "content\n");

        $fsmon = new Fsmonitor($this->tmpDir . '/.git', $this->tmpDir);
        $result = $fsmon->query(null);

        $this->assertContains('file.txt', $result['files']);
    }
}
