<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;

final class RestoreParityTest extends TestCase
{
    /** @var list<string> */
    private array $tmpDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }

    #[Test]
    public function restoreWorktreeMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createRepoPair(function (string $dir): void {
            $this->writeFile($dir, 'a.txt', "original\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m initial');
        });

        $this->writeFile($gitDir, 'a.txt', "modified\n");
        $this->git($gitDir, 'restore a.txt');

        $this->writeFile($pitDir, 'a.txt', "modified\n");
        $repo = Pitmaster::open($pitDir);
        $repo->restore('a.txt');

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame(file_get_contents($gitDir . '/a.txt'), file_get_contents($pitDir . '/a.txt'));
        $this->assertSame($this->git($gitDir, 'ls-files --stage'), $this->git($pitDir, 'ls-files --stage'));
    }

    #[Test]
    public function restoreStagedMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createRepoPair(function (string $dir): void {
            $this->writeFile($dir, 'a.txt', "original\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m initial');
        });

        $this->writeFile($gitDir, 'a.txt', "staged\n");
        $this->git($gitDir, 'add a.txt');
        $this->git($gitDir, 'restore --staged a.txt');

        $this->writeFile($pitDir, 'a.txt', "staged\n");
        $repo = Pitmaster::open($pitDir);
        $repo->add('a.txt');
        $repo->restore('a.txt', staged: true);

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame(file_get_contents($gitDir . '/a.txt'), file_get_contents($pitDir . '/a.txt'));
        $this->assertSame($this->git($gitDir, 'ls-files --stage'), $this->git($pitDir, 'ls-files --stage'));
    }

    #[Test]
    public function restoreSourceToIndexAndWorktreeMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createRepoPair(function (string $dir): void {
            $this->writeFile($dir, 'a.txt', "v1\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m first');

            $this->writeFile($dir, 'a.txt', "v2\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m second');
        });

        $this->writeFile($gitDir, 'a.txt', "worktree\n");
        $this->git($gitDir, 'restore --source=HEAD~1 --staged --worktree a.txt');

        $this->writeFile($pitDir, 'a.txt', "worktree\n");
        $repo = Pitmaster::open($pitDir);
        $repo->restore('a.txt', 'HEAD~1', staged: true, worktree: true);

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame(file_get_contents($gitDir . '/a.txt'), file_get_contents($pitDir . '/a.txt'));
        $this->assertSame($this->git($gitDir, 'ls-files --stage'), $this->git($pitDir, 'ls-files --stage'));
        $this->assertSame(trim($this->git($gitDir, 'show -s --format=%T HEAD~1')), trim($this->git($pitDir, 'show -s --format=%T HEAD~1')));
    }

    /**
     * @param callable(string): void $setup
     * @return array{git: string, pit: string}
     */
    private function createRepoPair(callable $setup): array
    {
        $baseDir = $this->createTempDir('pitmaster-restore-base-');
        $gitDir = $this->createTempDir('pitmaster-restore-git-');
        $pitDir = $this->createTempDir('pitmaster-restore-pit-');
        $this->initRepo($baseDir);
        $setup($baseDir);
        $this->copyDir($baseDir, $gitDir);
        $this->copyDir($baseDir, $pitDir);

        return ['git' => $gitDir, 'pit' => $pitDir];
    }

    private function initRepo(string $dir): void
    {
        $this->runShell(sprintf('git init -b main %s >/dev/null', escapeshellarg($dir)));
        $this->git($dir, 'config user.email test@pitmaster.dev');
        $this->git($dir, 'config user.name "Test User"');
    }

    private function copyDir(string $source, string $target): void
    {
        $this->runShell(sprintf(
            'cp -R %s %s',
            escapeshellarg($source . '/.'),
            escapeshellarg($target),
        ));
    }

    private function createTempDir(string $prefix): string
    {
        $dir = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(4));
        mkdir($dir, 0777, true);
        $this->tmpDirs[] = $dir;

        return $dir;
    }

    private function git(string $dir, string $cmd): string
    {
        return shell_exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $cmd)) ?? '';
    }

    private function writeFile(string $dir, string $path, string $content): void
    {
        $fullPath = $dir . '/' . $path;
        $parent = dirname($fullPath);

        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }

        file_put_contents($fullPath, $content);
    }

    private function runShell(string $command): void
    {
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            self::fail(implode("\n", $output));
        }
    }
}
