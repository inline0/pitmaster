<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;

final class MoveAndRemoveParityTest extends TestCase
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
    public function removeMatchesGitRmCached(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createRepoPair(function (string $dir): void {
            $this->writeFile($dir, 'tracked.txt', "tracked\n");
            $this->git($dir, 'add tracked.txt');
            $this->git($dir, 'commit -m initial');
        });

        $this->git($gitDir, 'rm --cached tracked.txt');
        $repo = Pitmaster::open($pitDir);
        $repo->remove('--cached', 'tracked.txt');

        $this->assertFileExists($gitDir . '/tracked.txt');
        $this->assertFileExists($pitDir . '/tracked.txt');
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'ls-files --stage'), $this->git($pitDir, 'ls-files --stage'));
    }

    #[Test]
    public function removeTrackedFileMatchesGitRm(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createRepoPair(function (string $dir): void {
            $this->writeFile($dir, 'tracked.txt', "tracked\n");
            $this->git($dir, 'add tracked.txt');
            $this->git($dir, 'commit -m initial');
        });

        $this->git($gitDir, 'rm tracked.txt');
        $repo = Pitmaster::open($pitDir);
        $repo->remove('tracked.txt');

        $this->assertFileDoesNotExist($gitDir . '/tracked.txt');
        $this->assertFileDoesNotExist($pitDir . '/tracked.txt');
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'ls-files --stage'), $this->git($pitDir, 'ls-files --stage'));
    }

    #[Test]
    public function removeTrackedDirectoryRecursivelyMatchesGitRm(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createRepoPair(function (string $dir): void {
            $this->writeFile($dir, 'src/Service.php', "<?php\nreturn 'service';\n");
            $this->writeFile($dir, 'src/Nested/Helper.php', "<?php\nreturn 'helper';\n");
            $this->git($dir, 'add src');
            $this->git($dir, 'commit -m initial');
        });

        $this->git($gitDir, 'rm -r src');
        $repo = Pitmaster::open($pitDir);
        $repo->remove('-r', 'src');

        $this->assertDirectoryDoesNotExist($gitDir . '/src');
        $this->assertDirectoryDoesNotExist($pitDir . '/src');
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'ls-files --stage'), $this->git($pitDir, 'ls-files --stage'));
    }

    #[Test]
    public function moveDirectoryMatchesGitAndCommitsSameTree(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createRepoPair(function (string $dir): void {
            $this->writeFile($dir, 'src/Service.php', "<?php\nreturn 'service';\n");
            $this->writeFile($dir, 'src/Nested/Helper.php', "<?php\nreturn 'helper';\n");
            $this->git($dir, 'add src');
            $this->git($dir, 'commit -m initial');
        });

        $this->git($gitDir, 'mv src app');
        $repo = Pitmaster::open($pitDir);
        $repo->mv('src', 'app');

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'ls-files --stage'), $this->git($pitDir, 'ls-files --stage'));

        $this->withDeterministicCommitDates(function () use ($gitDir, $pitDir, $repo): void {
            $this->git($gitDir, 'commit -m "Rename tree"');
            $repo->commit("Rename tree\n");
        });

        $this->assertFileDoesNotExist($pitDir . '/src/Service.php');
        $this->assertFileExists($pitDir . '/app/Service.php');
        $this->assertFileExists($pitDir . '/app/Nested/Helper.php');
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%P HEAD'), $this->git($pitDir, 'show -s --format=%P HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertSame($this->git($gitDir, 'ls-tree -r --name-only HEAD'), $this->git($pitDir, 'ls-tree -r --name-only HEAD'));
    }

    #[Test]
    public function moveFileIntoExistingDirectoryMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createRepoPair(function (string $dir): void {
            $this->writeFile($dir, 'old.txt', "old\n");
            $this->writeFile($dir, 'dest/keep.txt', "keep\n");
            $this->git($dir, 'add old.txt dest/keep.txt');
            $this->git($dir, 'commit -m initial');
        });

        $this->git($gitDir, 'mv old.txt dest');
        $repo = Pitmaster::open($pitDir);
        $repo->mv('old.txt', 'dest');

        $this->assertFileDoesNotExist($pitDir . '/old.txt');
        $this->assertFileExists($pitDir . '/dest/old.txt');
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'ls-files --stage'), $this->git($pitDir, 'ls-files --stage'));
    }

    /**
     * @param callable(string): void $setup
     * @return array{git: string, pit: string}
     */
    private function createRepoPair(callable $setup): array
    {
        $baseDir = $this->createTempDir('pitmaster-move-remove-base-');
        $gitDir = $this->createTempDir('pitmaster-move-remove-git-');
        $pitDir = $this->createTempDir('pitmaster-move-remove-pit-');
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
