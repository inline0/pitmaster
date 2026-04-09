<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;

final class RebaseParityTest extends TestCase
{
    /** @var array<int, string> */
    private array $tmpDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }

    #[Test]
    public function cleanRebaseMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createCleanRebasePair();

        $repo = Pitmaster::open($pitDir);
        $result = $this->withDeterministicCommitDates('2024-01-12T00:00:10+0000', function () use ($gitDir, $repo): array {
            $this->gitWithExit($gitDir, 'rebase main');

            return $repo->rebase('main');
        });

        $this->assertTrue($result['success']);
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertSame($this->git($gitDir, 'log --format=%s --reverse main..HEAD'), $this->git($pitDir, 'log --format=%s --reverse main..HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'HEAD'), $this->readGitFile($pitDir, 'HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'ORIG_HEAD'), $this->readGitFile($pitDir, 'ORIG_HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'REBASE_HEAD'), $this->readGitFile($pitDir, 'REBASE_HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'MERGE_MSG'), $this->readGitFile($pitDir, 'MERGE_MSG'));
        $this->assertFalse(is_dir($pitDir . '/.git/rebase-merge'));
        $this->assertSame($this->readGitFile($gitDir, 'logs/HEAD'), $this->readGitFile($pitDir, 'logs/HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'logs/refs/heads/feature'), $this->readGitFile($pitDir, 'logs/refs/heads/feature'));
    }

    #[Test]
    public function rebaseConflictStateMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createConflictRebasePair();

        $this->gitWithExit($gitDir, 'rebase main');
        $repo = Pitmaster::open($pitDir);
        $result = $repo->rebase('main');

        $this->assertFalse($result['success']);
        $this->assertSame($this->git($gitDir, 'ls-files --stage'), $this->git($pitDir, 'ls-files --stage'));
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->readGitFile($gitDir, 'REBASE_HEAD'), $this->readGitFile($pitDir, 'REBASE_HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'MERGE_MSG'), $this->readGitFile($pitDir, 'MERGE_MSG'));
        $this->assertSame($this->readGitFile($gitDir, 'ORIG_HEAD'), $this->readGitFile($pitDir, 'ORIG_HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'rebase-merge/head-name'), $this->readGitFile($pitDir, 'rebase-merge/head-name'));
        $this->assertSame($this->readGitFile($gitDir, 'rebase-merge/onto'), $this->readGitFile($pitDir, 'rebase-merge/onto'));
        $this->assertSame($this->readGitFile($gitDir, 'rebase-merge/orig-head'), $this->readGitFile($pitDir, 'rebase-merge/orig-head'));
        $this->assertSame($this->readGitFile($gitDir, 'rebase-merge/stopped-sha'), $this->readGitFile($pitDir, 'rebase-merge/stopped-sha'));
        $this->assertSame($this->readGitFile($gitDir, 'rebase-merge/message'), $this->readGitFile($pitDir, 'rebase-merge/message'));
        $this->assertSame(file_get_contents($gitDir . '/a.txt'), file_get_contents($pitDir . '/a.txt'));
    }

    #[Test]
    public function rebaseContinueMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createConflictRebasePair();

        $repo = Pitmaster::open($pitDir);
        $this->withDeterministicCommitDates('2024-01-14T00:00:10+0000', function () use ($gitDir, $repo): void {
            $this->gitWithExit($gitDir, 'rebase main');
            $repo->rebase('main');
        });

        $resolved = "line 1\nresolved\nline 3\n";
        $this->writeFile($gitDir, 'a.txt', $resolved);
        $this->git($gitDir, 'add a.txt');

        $this->writeFile($pitDir, 'a.txt', $resolved);
        $repo->add('a.txt');
        $this->withDeterministicCommitDates('2024-01-14T00:00:11+0000', function () use ($gitDir, $repo): void {
            $this->gitWithExit($gitDir, '-c core.editor=true rebase --continue');
            $repo->rebaseContinue();
        });

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertSame($this->git($gitDir, "show -s --format='%an <%ae>' HEAD"), $this->git($pitDir, "show -s --format='%an <%ae>' HEAD"));
        $this->assertSame($this->git($gitDir, 'log --format=%s --reverse main..HEAD'), $this->git($pitDir, 'log --format=%s --reverse main..HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'HEAD'), $this->readGitFile($pitDir, 'HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'ORIG_HEAD'), $this->readGitFile($pitDir, 'ORIG_HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'REBASE_HEAD'), $this->readGitFile($pitDir, 'REBASE_HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'MERGE_MSG'), $this->readGitFile($pitDir, 'MERGE_MSG'));
        $this->assertFalse(is_dir($pitDir . '/.git/rebase-merge'));
        $this->assertSame($this->readGitFile($gitDir, 'logs/HEAD'), $this->readGitFile($pitDir, 'logs/HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'logs/refs/heads/feature'), $this->readGitFile($pitDir, 'logs/refs/heads/feature'));
    }

    #[Test]
    public function rebaseAbortMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createConflictRebasePair();

        $repo = Pitmaster::open($pitDir);
        $this->withDeterministicCommitDates('2024-01-15T00:00:10+0000', function () use ($gitDir, $repo): void {
            $this->gitWithExit($gitDir, 'rebase main');
            $repo->rebase('main');
            $this->gitWithExit($gitDir, 'rebase --abort');
            $repo->rebaseAbort();
        });

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%P HEAD'), $this->git($pitDir, 'show -s --format=%P HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'HEAD'), $this->readGitFile($pitDir, 'HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'ORIG_HEAD'), $this->readGitFile($pitDir, 'ORIG_HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'REBASE_HEAD'), $this->readGitFile($pitDir, 'REBASE_HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'MERGE_MSG'), $this->readGitFile($pitDir, 'MERGE_MSG'));
        $this->assertFalse(is_dir($pitDir . '/.git/rebase-merge'));
        $this->assertSame($this->readGitFile($gitDir, 'logs/HEAD'), $this->readGitFile($pitDir, 'logs/HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'logs/refs/heads/feature'), $this->readGitFile($pitDir, 'logs/refs/heads/feature'));
    }

    #[Test]
    public function rebaseSkipMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createConflictRebasePair();

        $repo = Pitmaster::open($pitDir);
        $this->withDeterministicCommitDates('2024-01-16T00:00:10+0000', function () use ($gitDir, $repo): void {
            $this->gitWithExit($gitDir, 'rebase main');
            $repo->rebase('main');
            $this->gitWithExit($gitDir, 'rebase --skip');
            $repo->rebaseSkip();
        });

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%P HEAD'), $this->git($pitDir, 'show -s --format=%P HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'HEAD'), $this->readGitFile($pitDir, 'HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'ORIG_HEAD'), $this->readGitFile($pitDir, 'ORIG_HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'REBASE_HEAD'), $this->readGitFile($pitDir, 'REBASE_HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'MERGE_MSG'), $this->readGitFile($pitDir, 'MERGE_MSG'));
        $this->assertFalse(is_dir($pitDir . '/.git/rebase-merge'));
        $this->assertSame($this->readGitFile($gitDir, 'logs/HEAD'), $this->readGitFile($pitDir, 'logs/HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'logs/refs/heads/feature'), $this->readGitFile($pitDir, 'logs/refs/heads/feature'));
    }

    /**
     * @return array{git: string, pit: string}
     */
    private function createCleanRebasePair(): array
    {
        return $this->createRepoPair(function (string $dir): void {
            $this->writeFile($dir, 'a.txt', "base\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m base');
            $this->git($dir, 'checkout -b feature');
            $this->writeFile($dir, 'b.txt', "feature one\n");
            $this->git($dir, 'add b.txt');
            $this->git($dir, 'commit -m feature-one');
            $this->writeFile($dir, 'c.txt', "feature two\n");
            $this->git($dir, 'add c.txt');
            $this->git($dir, 'commit -m feature-two');
            $this->git($dir, 'checkout main');
            $this->writeFile($dir, 'a.txt', "main\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m main');
            $this->git($dir, 'checkout feature');
        });
    }

    /**
     * @return array{git: string, pit: string}
     */
    private function createConflictRebasePair(): array
    {
        return $this->createRepoPair(function (string $dir): void {
            $this->writeFile($dir, 'a.txt', "line 1\nline 2\nline 3\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m base');
            $this->git($dir, 'checkout -b feature');
            $this->writeFile($dir, 'a.txt', "line 1\nfeature change\nline 3\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m feature');
            $this->git($dir, 'checkout main');
            $this->writeFile($dir, 'a.txt', "line 1\nmain change\nline 3\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m main');
            $this->git($dir, 'checkout feature');
        });
    }

    /**
     * @param callable(string): array<string, string>|void $setup
     * @return array<string, string>
     */
    private function createRepoPair(callable $setup): array
    {
        $baseDir = $this->createTempDir('pitmaster-rebase-base-');
        $gitDir = $this->createTempDir('pitmaster-rebase-git-');
        $pitDir = $this->createTempDir('pitmaster-rebase-pit-');
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
        $this->git($dir, 'config core.logAllRefUpdates true');
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

    private function readGitFile(string $dir, string $name): ?string
    {
        $path = $dir . '/.git/' . $name;

        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content !== false ? $content : null;
    }

    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withDeterministicCommitDates(string $date, callable $callback): mixed
    {
        $previousAuthor = getenv('GIT_AUTHOR_DATE');
        $previousCommitter = getenv('GIT_COMMITTER_DATE');

        putenv("GIT_AUTHOR_DATE={$date}");
        putenv("GIT_COMMITTER_DATE={$date}");

        try {
            return $callback();
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
