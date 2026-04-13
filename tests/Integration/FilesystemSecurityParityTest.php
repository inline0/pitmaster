<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;

final class FilesystemSecurityParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-filesystem-security-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function symlinkStagingAndStatusMatchGit(): void
    {
        $gitDir = $this->tmpDir . '/git';
        $pitDir = $this->tmpDir . '/pit';
        mkdir($gitDir, 0777, true);
        mkdir($pitDir, 0777, true);
        $this->initRepo($gitDir);
        $this->initRepo($pitDir);

        file_put_contents($gitDir . '/target.txt', "target\n");
        file_put_contents($pitDir . '/target.txt', "target\n");
        symlink('target.txt', $gitDir . '/link.txt');
        symlink('target.txt', $pitDir . '/link.txt');

        $this->git($gitDir, 'add target.txt link.txt');
        Pitmaster::open($pitDir)->add('target.txt', 'link.txt');

        $this->assertSame(
            $this->git($gitDir, 'ls-files --stage'),
            $this->git($pitDir, 'ls-files --stage'),
        );
        $this->assertSame(
            $this->git($gitDir, 'status --porcelain=v2'),
            $this->git($pitDir, 'status --porcelain=v2'),
        );
    }

    #[Test]
    public function restoreRecreatesSymlinkLikeGit(): void
    {
        $gitDir = $this->tmpDir . '/git-restore';
        $pitDir = $this->tmpDir . '/pit-restore';
        mkdir($gitDir, 0777, true);
        mkdir($pitDir, 0777, true);
        $this->initRepo($gitDir);
        $this->initRepo($pitDir);

        foreach ([$gitDir, $pitDir] as $dir) {
            file_put_contents($dir . '/target.txt', "target\n");
            symlink('target.txt', $dir . '/link.txt');
        }

        $this->git($gitDir, 'add target.txt link.txt');
        $this->git($gitDir, 'commit -m base');
        $pitRepo = Pitmaster::open($pitDir);
        $pitRepo->add('target.txt', 'link.txt');
        $pitRepo->commit('base');

        unlink($gitDir . '/link.txt');
        unlink($pitDir . '/link.txt');

        $this->git($gitDir, 'restore link.txt');
        $pitRepo->restore('link.txt');

        $this->assertTrue(is_link($pitDir . '/link.txt'));
        $this->assertSame(readlink($gitDir . '/link.txt'), readlink($pitDir . '/link.txt'));
        $this->assertSame(
            $this->git($gitDir, 'status --porcelain=v2'),
            $this->git($pitDir, 'status --porcelain=v2'),
        );
    }

    #[Test]
    public function invalidGitFileIndirectionFailsClosed(): void
    {
        $dir = $this->tmpDir . '/invalid-gitdir';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/.git', "gitdir: missing/worktree\n");

        exec(
            sprintf('cd %s && git status --short 2>&1', escapeshellarg($dir)),
            $output,
            $gitExitCode,
        );

        $this->assertNotSame(0, $gitExitCode);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid gitdir');

        Pitmaster::open($dir);
    }

    private function initRepo(string $dir): void
    {
        $this->git($dir, 'init -b main');
        $this->git($dir, 'config user.email test@example.com');
        $this->git($dir, 'config user.name Test');
    }

    private function git(string $dir, string $command): string
    {
        exec(
            sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command),
            $output,
            $exitCode,
        );

        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            self::fail("git {$command} failed in {$dir}:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }
}
