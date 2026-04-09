<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;

final class MergeStrategyParityTest extends TestCase
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
    public function oursStrategyMergeMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createOursMergePair();
        $repo = Pitmaster::open($pitDir);

        $this->withDeterministicCommitDates(function () use ($gitDir, $repo): void {
            $this->git($gitDir, 'merge -s ours feature -m "Merge branch \'feature\'"');
            $repo->merge('feature', 'ours');
        });

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%P HEAD'), $this->git($pitDir, 'show -s --format=%P HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'ORIG_HEAD'), $this->readGitFile($pitDir, 'ORIG_HEAD'));
    }

    #[Test]
    public function cleanOctopusMergeMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createOctopusMergePair();
        $repo = Pitmaster::open($pitDir);

        $this->withDeterministicCommitDates(function () use ($gitDir, $repo): void {
            $this->git($gitDir, 'merge feature1 feature2 -m "Merge branches \'feature1\', \'feature2\'"');
            $repo->mergeOctopus(['feature1', 'feature2']);
        });

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%P HEAD'), $this->git($pitDir, 'show -s --format=%P HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertSame($this->git($gitDir, 'ls-tree -r --name-only HEAD'), $this->git($pitDir, 'ls-tree -r --name-only HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'ORIG_HEAD'), $this->readGitFile($pitDir, 'ORIG_HEAD'));
    }

    #[Test]
    public function ortStrategyCrissCrossMergeMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createOrtCrissCrossPair();
        $repo = Pitmaster::open($pitDir);

        $this->withDeterministicCommitDates(function () use ($gitDir, $repo): void {
            $this->git($gitDir, 'checkout left');
            $this->git($gitDir, 'merge -s ort right');
            $repo->checkout('left');
            $repo->merge('right', 'ort');
        });

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%P HEAD'), $this->git($pitDir, 'show -s --format=%P HEAD'));
        $this->assertSame(file_get_contents($gitDir . '/f.txt'), file_get_contents($pitDir . '/f.txt'));
    }

    /**
     * @return array{git: string, pit: string}
     */
    private function createOursMergePair(): array
    {
        $baseDir = sys_get_temp_dir() . '/pitmaster-merge-ours-' . bin2hex(random_bytes(4));
        $gitDir = $baseDir . '-git';
        $pitDir = $baseDir . '-pit';
        $this->tmpDirs[] = $gitDir;
        $this->tmpDirs[] = $pitDir;
        mkdir($gitDir, 0777, true);
        $this->git($gitDir, 'init --initial-branch=main');
        $this->git($gitDir, 'config user.email test@pitmaster.dev');
        $this->git($gitDir, 'config user.name "Test User"');

        file_put_contents($gitDir . '/shared.txt', "base\n");
        $this->git($gitDir, 'add shared.txt');
        $this->git($gitDir, 'commit -m base');
        $this->git($gitDir, 'checkout -b feature');
        file_put_contents($gitDir . '/feature.txt', "feature\n");
        $this->git($gitDir, 'add feature.txt');
        $this->git($gitDir, 'commit -m feature');
        $this->git($gitDir, 'checkout main');
        file_put_contents($gitDir . '/main.txt', "main\n");
        $this->git($gitDir, 'add main.txt');
        $this->git($gitDir, 'commit -m main');

        exec(sprintf('cp -R %s %s', escapeshellarg($gitDir), escapeshellarg($pitDir)), $output, $exitCode);

        if ($exitCode !== 0) {
            self::fail('Failed to create ours parity copy');
        }

        return ['git' => $gitDir, 'pit' => $pitDir];
    }

    /**
     * @return array{git: string, pit: string}
     */
    private function createOctopusMergePair(): array
    {
        $baseDir = sys_get_temp_dir() . '/pitmaster-merge-octopus-' . bin2hex(random_bytes(4));
        $gitDir = $baseDir . '-git';
        $pitDir = $baseDir . '-pit';
        $this->tmpDirs[] = $gitDir;
        $this->tmpDirs[] = $pitDir;
        mkdir($gitDir, 0777, true);
        $this->git($gitDir, 'init --initial-branch=main');
        $this->git($gitDir, 'config user.email test@pitmaster.dev');
        $this->git($gitDir, 'config user.name "Test User"');

        file_put_contents($gitDir . '/base.txt', "base\n");
        $this->git($gitDir, 'add base.txt');
        $this->git($gitDir, 'commit -m base');
        $base = trim($this->git($gitDir, 'rev-parse HEAD'));
        $this->git($gitDir, 'checkout -b feature1');
        file_put_contents($gitDir . '/a.txt', "a\n");
        $this->git($gitDir, 'add a.txt');
        $this->git($gitDir, 'commit -m feature1');
        $this->git($gitDir, 'checkout main');
        file_put_contents($gitDir . '/main.txt', "main\n");
        $this->git($gitDir, 'add main.txt');
        $this->git($gitDir, 'commit -m main');
        $this->git($gitDir, 'checkout -b feature2 ' . $base);
        file_put_contents($gitDir . '/b.txt', "b\n");
        $this->git($gitDir, 'add b.txt');
        $this->git($gitDir, 'commit -m feature2');
        $this->git($gitDir, 'checkout main');

        exec(sprintf('cp -R %s %s', escapeshellarg($gitDir), escapeshellarg($pitDir)), $output, $exitCode);

        if ($exitCode !== 0) {
            self::fail('Failed to create octopus parity copy');
        }

        return ['git' => $gitDir, 'pit' => $pitDir];
    }

    /**
     * @return array{git: string, pit: string}
     */
    private function createOrtCrissCrossPair(): array
    {
        $baseDir = sys_get_temp_dir() . '/pitmaster-merge-ort-' . bin2hex(random_bytes(4));
        $gitDir = $baseDir . '-git';
        $pitDir = $baseDir . '-pit';
        $this->tmpDirs[] = $gitDir;
        $this->tmpDirs[] = $pitDir;
        mkdir($gitDir, 0777, true);
        $this->git($gitDir, 'init --initial-branch=main');
        $this->git($gitDir, 'config user.email test@pitmaster.dev');
        $this->git($gitDir, 'config user.name "Test User"');

        file_put_contents($gitDir . '/f.txt', "one\ntwo\nthree\nfour\n");
        $this->git($gitDir, 'add f.txt');
        $this->git($gitDir, 'commit -m A');
        $this->git($gitDir, 'checkout -b left');
        file_put_contents($gitDir . '/f.txt', "B1\ntwo\nthree\nfour\n");
        $this->git($gitDir, 'add f.txt');
        $this->git($gitDir, 'commit -m B');
        $this->git($gitDir, 'branch left-base');
        $this->git($gitDir, 'checkout main');
        $this->git($gitDir, 'checkout -b right');
        file_put_contents($gitDir . '/f.txt', "one\ntwo\nthree\nC2\n");
        $this->git($gitDir, 'add f.txt');
        $this->git($gitDir, 'commit -m C');
        $this->git($gitDir, 'branch right-base');
        $this->git($gitDir, 'checkout left');
        $this->git($gitDir, 'merge right-base --no-edit');
        file_put_contents($gitDir . '/f.txt', "B1\ntwo\nthree\nD2\n");
        $this->git($gitDir, 'add f.txt');
        $this->git($gitDir, 'commit -m D');
        $this->git($gitDir, 'checkout right');
        $this->git($gitDir, 'merge left-base --no-edit');
        file_put_contents($gitDir . '/f.txt', "E1\ntwo\nthree\nC2\n");
        $this->git($gitDir, 'add f.txt');
        $this->git($gitDir, 'commit -m E');

        exec(sprintf('cp -R %s %s', escapeshellarg($gitDir), escapeshellarg($pitDir)), $output, $exitCode);

        if ($exitCode !== 0) {
            self::fail('Failed to create ort parity copy');
        }

        return ['git' => $gitDir, 'pit' => $pitDir];
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
            putenv($previousAuthor === false ? 'GIT_AUTHOR_DATE' : 'GIT_AUTHOR_DATE=' . $previousAuthor);
            putenv($previousCommitter === false ? 'GIT_COMMITTER_DATE' : 'GIT_COMMITTER_DATE=' . $previousCommitter);
        }
    }

    private function readGitFile(string $repoDir, string $path): ?string
    {
        $fullPath = $repoDir . '/.git/' . $path;

        if (!is_file($fullPath)) {
            return null;
        }

        return file_get_contents($fullPath);
    }

    private function git(string $repoDir, string $command): string
    {
        exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($repoDir), $command), $output, $exitCode);
        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            self::fail("git {$command} failed:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }
}
