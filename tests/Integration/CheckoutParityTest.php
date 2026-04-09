<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Repository;

final class CheckoutParityTest extends TestCase
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
    public function checkoutRejectsTrackedOverwriteLikeGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createRepoPair(function (string $dir): void {
            $this->writeFile($dir, 'a.txt', "base\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m base');
            $this->git($dir, 'checkout -b feature');
            $this->writeFile($dir, 'a.txt', "feature\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m feature');
            $this->git($dir, 'checkout main');
            $this->writeFile($dir, 'a.txt', "local change\n");
        });

        $gitResult = $this->gitWithExit($gitDir, 'checkout feature');
        $repo = Pitmaster::open($pitDir);

        try {
            $repo->checkout('feature');
            self::fail('Expected checkout to reject tracked overwrite');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('would be overwritten by checkout', $e->getMessage());
        }

        $this->assertNotSame(0, $gitResult['exitCode']);
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'branch --show-current'), $this->git($pitDir, 'branch --show-current'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%H HEAD'), $this->git($pitDir, 'show -s --format=%H HEAD'));
        $this->assertSame(file_get_contents($gitDir . '/a.txt'), file_get_contents($pitDir . '/a.txt'));
    }

    #[Test]
    public function checkoutRejectsUntrackedOverwriteLikeGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createRepoPair(function (string $dir): void {
            $this->writeFile($dir, 'a.txt', "base\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m base');
            $this->git($dir, 'checkout -b feature');
            $this->writeFile($dir, 'new.txt', "feature file\n");
            $this->git($dir, 'add new.txt');
            $this->git($dir, 'commit -m feature');
            $this->git($dir, 'checkout main');
            $this->writeFile($dir, 'new.txt', "untracked\n");
        });

        $gitResult = $this->gitWithExit($gitDir, 'checkout feature');
        $repo = Pitmaster::open($pitDir);

        try {
            $repo->checkout('feature');
            self::fail('Expected checkout to reject untracked overwrite');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('untracked working tree files would be overwritten', $e->getMessage());
        }

        $this->assertNotSame(0, $gitResult['exitCode']);
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'branch --show-current'), $this->git($pitDir, 'branch --show-current'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%H HEAD'), $this->git($pitDir, 'show -s --format=%H HEAD'));
        $this->assertSame(file_get_contents($gitDir . '/new.txt'), file_get_contents($pitDir . '/new.txt'));
    }

    #[Test]
    public function detachedHeadCommitMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir, 'detach' => $detachId] = $this->createRepoPair(function (string $dir): array {
            $this->git($dir, 'config core.logAllRefUpdates true');
            $this->writeFile($dir, 'a.txt', "one\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m first');
            $detachId = trim($this->git($dir, 'rev-parse HEAD'));
            $this->writeFile($dir, 'a.txt', "two\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m second');

            return ['detach' => $detachId];
        });

        $this->git($gitDir, 'checkout ' . escapeshellarg($detachId));
        $repo = Pitmaster::open($pitDir);
        $repo->checkout($detachId);

        $this->withDeterministicCommitDates(function () use ($gitDir, $pitDir, $repo): void {
            $this->writeFile($gitDir, 'detached.txt', "detached\n");
            $this->git($gitDir, 'add detached.txt');
            $this->git($gitDir, 'commit -m detached');

            $this->writeFile($pitDir, 'detached.txt', "detached\n");
            $repo->add('detached.txt');
            $repo->commit("detached\n");
        });

        $this->assertNull($repo->branch());
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'branch --show-current'), $this->git($pitDir, 'branch --show-current'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%H HEAD'), $this->git($pitDir, 'show -s --format=%H HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%P HEAD'), $this->git($pitDir, 'show -s --format=%P HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertSame($this->git($gitDir, 'reflog --format=%gs -n 2'), $this->git($pitDir, 'reflog --format=%gs -n 2'));
        $this->assertSame(
            $this->git($gitDir, 'reflog show refs/heads/main --format=%gs -n 1'),
            $this->git($pitDir, 'reflog show refs/heads/main --format=%gs -n 1'),
        );
    }

    /**
     * @param callable(string): array<string, string>|void $setup
     * @return array<string, string>
     */
    private function createRepoPair(callable $setup): array
    {
        $baseDir = $this->createTempDir('pitmaster-checkout-base-');
        $gitDir = $this->createTempDir('pitmaster-checkout-git-');
        $pitDir = $this->createTempDir('pitmaster-checkout-pit-');
        $this->initRepo($baseDir);
        $meta = $setup($baseDir) ?? [];
        $this->copyDir($baseDir, $gitDir);
        $this->copyDir($baseDir, $pitDir);

        return array_merge(['git' => $gitDir, 'pit' => $pitDir], $meta);
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

    /**
     * @return array{output: string, exitCode: int}
     */
    private function gitWithExit(string $dir, string $cmd): array
    {
        exec(
            sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $cmd),
            $output,
            $exitCode,
        );

        return ['output' => implode("\n", $output), 'exitCode' => $exitCode];
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

    private function withDeterministicCommitDates(callable $callback): void
    {
        $previousAuthor = getenv('GIT_AUTHOR_DATE');
        $previousCommitter = getenv('GIT_COMMITTER_DATE');
        putenv('GIT_AUTHOR_DATE=2024-01-15T00:00:10+0000');
        putenv('GIT_COMMITTER_DATE=2024-01-15T00:00:10+0000');

        try {
            $callback();
        } finally {
            putenv($previousAuthor === false ? 'GIT_AUTHOR_DATE' : "GIT_AUTHOR_DATE={$previousAuthor}");
            putenv($previousCommitter === false ? 'GIT_COMMITTER_DATE' : "GIT_COMMITTER_DATE={$previousCommitter}");
        }
    }

    private function runShell(string $command): void
    {
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            self::fail(implode("\n", $output));
        }
    }
}
