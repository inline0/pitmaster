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

    private function git(string $command): string
    {
        exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $command), $output, $exitCode);
        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            self::fail("git {$command} failed:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }

    private function installFsmonitorHook(string $script): void
    {
        $path = $this->tmpDir . '/.git/hooks/query-fsmonitor';
        file_put_contents($path, $script);
        chmod($path, 0755);
        $this->git('config core.fsmonitor .git/hooks/query-fsmonitor');
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

    #[Test]
    public function queryUsesConfiguredFsmonitorHookProtocol(): void
    {
        $this->installFsmonitorHook(<<<'SH'
#!/usr/bin/env bash
printf '%s|%s\n' "$1" "$2" >> .git/fsmonitor.log
printf 'hook-token\0tracked.txt\0nested/file.txt\0'
SH);

        $fsmon = new Fsmonitor($this->tmpDir . '/.git', $this->tmpDir);
        $result = $fsmon->query('seed-token');

        $this->assertSame('hook-token', $result['token']);
        $this->assertSame(['tracked.txt', 'nested/file.txt'], $result['files']);
        $this->assertSame("2|seed-token\n", file_get_contents($this->tmpDir . '/.git/fsmonitor.log'));
    }

    #[Test]
    public function fsmonitorHookProtocolMatchesGitInvocationShape(): void
    {
        $this->writeFile('tracked.txt', "tracked\n");
        $this->git('add tracked.txt');
        $this->git('commit -m initial');
        $this->installFsmonitorHook(<<<'SH'
#!/usr/bin/env bash
printf '%s|%s\n' "$1" "$2" >> .git/fsmonitor.log
printf 'git-token\0tracked.txt\0'
SH);

        $this->git('update-index --fsmonitor');
        $this->git('status --porcelain=v2');
        $this->git('status --porcelain=v2');

        $gitLog = array_values(array_filter(explode("\n", trim((string) file_get_contents($this->tmpDir . '/.git/fsmonitor.log')))));
        $this->assertNotEmpty($gitLog);
        $this->assertSame('2|git-token', end($gitLog));

        unlink($this->tmpDir . '/.git/fsmonitor.log');

        $fsmon = new Fsmonitor($this->tmpDir . '/.git', $this->tmpDir);
        $result = $fsmon->query('git-token');

        $this->assertSame('git-token', $result['token']);
        $this->assertSame(['tracked.txt'], $result['files']);
        $this->assertSame("2|git-token\n", file_get_contents($this->tmpDir . '/.git/fsmonitor.log'));
    }
}
