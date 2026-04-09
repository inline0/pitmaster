<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Exceptions\MergeConflictException;
use Pitmaster\Pitmaster;
use Pitmaster\Repository;

final class MergeFamilyParityTest extends TestCase
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
    public function mergeConflictStateMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createMergeConflictPair();

        $this->gitWithExit($gitDir, 'merge feature');
        $repo = Pitmaster::open($pitDir);
        $result = $repo->merge('feature');

        $this->assertFalse($result->clean);
        $this->assertSame($this->git($gitDir, 'ls-files --stage'), $this->git($pitDir, 'ls-files --stage'));
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->readGitFile($gitDir, 'MERGE_HEAD'), $this->readGitFile($pitDir, 'MERGE_HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'MERGE_MSG'), $this->readGitFile($pitDir, 'MERGE_MSG'));
        $this->assertSame($this->readGitFile($gitDir, 'ORIG_HEAD'), $this->readGitFile($pitDir, 'ORIG_HEAD'));
        $this->assertSame(file_get_contents($gitDir . '/a.txt'), file_get_contents($pitDir . '/a.txt'));
    }

    #[Test]
    public function cleanMergeCommitMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createCleanMergePair();

        $this->withDeterministicCommitDates(function () use ($gitDir, $pitDir): void {
            $this->gitWithExit($gitDir, 'merge feature');
            $repo = Pitmaster::open($pitDir);
            $repo->merge('feature');
        });

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%P HEAD'), $this->git($pitDir, 'show -s --format=%P HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertSame($this->git($gitDir, 'ls-tree -r --name-only HEAD'), $this->git($pitDir, 'ls-tree -r --name-only HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'ORIG_HEAD'), $this->readGitFile($pitDir, 'ORIG_HEAD'));
        $this->assertSame($this->reflogMessage($gitDir), $this->reflogMessage($pitDir));
        $this->assertSame($this->reflogMessage($gitDir, 'refs/heads/main'), $this->reflogMessage($pitDir, 'refs/heads/main'));
    }

    #[Test]
    public function mergeConflictContinuationMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createMergeConflictPair();

        $this->gitWithExit($gitDir, 'merge feature');
        $repo = Pitmaster::open($pitDir);
        $repo->merge('feature');

        $resolved = "line 1\nresolved\nline 3\n";
        $this->writeFile($gitDir, 'a.txt', $resolved);
        $this->git($gitDir, 'add a.txt');
        $this->withDeterministicCommitDates(function () use ($gitDir, $repo, $pitDir, $resolved): void {
            $this->git($gitDir, '-c core.editor=true merge --continue');
            $this->writeFile($pitDir, 'a.txt', $resolved);
            $repo->add('a.txt');
            $repo->mergeContinue();
        });

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%P HEAD'), $this->git($pitDir, 'show -s --format=%P HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertNull($this->readGitFile($pitDir, 'MERGE_HEAD'));
        $this->assertNull($this->readGitFile($pitDir, 'MERGE_MSG'));
        $this->assertSame($this->readGitFile($gitDir, 'ORIG_HEAD'), $this->readGitFile($pitDir, 'ORIG_HEAD'));
    }

    #[Test]
    public function mergeConflictAbortMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createMergeConflictPair();

        $this->gitWithExit($gitDir, 'merge feature');
        $repo = Pitmaster::open($pitDir);
        $repo->merge('feature');

        $this->gitWithExit($gitDir, 'merge --abort');
        $repo->mergeAbort();

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%P HEAD'), $this->git($pitDir, 'show -s --format=%P HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertSame(file_get_contents($gitDir . '/a.txt'), file_get_contents($pitDir . '/a.txt'));
        $this->assertNull($this->readGitFile($pitDir, 'MERGE_HEAD'));
        $this->assertNull($this->readGitFile($pitDir, 'MERGE_MSG'));
        $this->assertSame($this->readGitFile($gitDir, 'ORIG_HEAD'), $this->readGitFile($pitDir, 'ORIG_HEAD'));
    }

    #[Test]
    public function cherryPickConflictStateMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir, 'pick' => $pickId] = $this->createCherryPickConflictPair();

        $this->gitWithExit($gitDir, 'cherry-pick ' . escapeshellarg($pickId));
        $repo = Pitmaster::open($pitDir);

        try {
            $repo->cherryPick($pickId);
            self::fail('Expected cherry-pick conflict');
        } catch (MergeConflictException $e) {
            $this->assertStringContainsString('conflict', strtolower($e->getMessage()));
        }

        $this->assertSame($this->git($gitDir, 'ls-files --stage'), $this->git($pitDir, 'ls-files --stage'));
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->readGitFile($gitDir, 'CHERRY_PICK_HEAD'), $this->readGitFile($pitDir, 'CHERRY_PICK_HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'MERGE_MSG'), $this->readGitFile($pitDir, 'MERGE_MSG'));
        $this->assertSame(file_get_contents($gitDir . '/a.txt'), file_get_contents($pitDir . '/a.txt'));
        $this->assertNull($this->readGitFile($pitDir, 'ORIG_HEAD'));
    }

    #[Test]
    public function cherryPickConflictContinuationMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir, 'pick' => $pickId] = $this->createCherryPickConflictPair();

        $this->gitWithExit($gitDir, 'cherry-pick ' . escapeshellarg($pickId));
        $repo = Pitmaster::open($pitDir);

        try {
            $repo->cherryPick($pickId);
            self::fail('Expected cherry-pick conflict');
        } catch (MergeConflictException) {
        }

        $resolved = "line 1\nresolved\nline 3\n";
        $this->writeFile($gitDir, 'a.txt', $resolved);
        $this->git($gitDir, 'add a.txt');
        $this->withDeterministicCommitDates(function () use ($gitDir, $repo, $pitDir, $resolved): void {
            $this->git($gitDir, '-c core.editor=true cherry-pick --continue');
            $this->writeFile($pitDir, 'a.txt', $resolved);
            $repo->add('a.txt');
            $repo->cherryPickContinue();
        });

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%P HEAD'), $this->git($pitDir, 'show -s --format=%P HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertSame($this->git($gitDir, "show -s --format='%an <%ae>' HEAD"), $this->git($pitDir, "show -s --format='%an <%ae>' HEAD"));
        $this->assertNull($this->readGitFile($pitDir, 'CHERRY_PICK_HEAD'));
        $this->assertNull($this->readGitFile($pitDir, 'MERGE_MSG'));
        $this->assertSame($this->reflogMessage($gitDir), $this->reflogMessage($pitDir));
        $this->assertSame($this->reflogMessage($gitDir, 'refs/heads/main'), $this->reflogMessage($pitDir, 'refs/heads/main'));
    }

    #[Test]
    public function cherryPickConflictAbortMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir, 'pick' => $pickId] = $this->createCherryPickConflictPair();

        $this->gitWithExit($gitDir, 'cherry-pick ' . escapeshellarg($pickId));
        $repo = Pitmaster::open($pitDir);

        try {
            $repo->cherryPick($pickId);
            self::fail('Expected cherry-pick conflict');
        } catch (MergeConflictException) {
        }

        $this->gitWithExit($gitDir, 'cherry-pick --abort');
        $repo->cherryPickAbort();

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%P HEAD'), $this->git($pitDir, 'show -s --format=%P HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertSame(file_get_contents($gitDir . '/a.txt'), file_get_contents($pitDir . '/a.txt'));
        $this->assertNull($this->readGitFile($pitDir, 'CHERRY_PICK_HEAD'));
        $this->assertNull($this->readGitFile($pitDir, 'MERGE_MSG'));
        $this->assertSame($this->readGitFile($gitDir, 'ORIG_HEAD'), $this->readGitFile($pitDir, 'ORIG_HEAD'));
        $this->assertSame($this->reflogMessage($gitDir), $this->reflogMessage($pitDir));
        $this->assertSame($this->reflogMessage($gitDir, 'refs/heads/main'), $this->reflogMessage($pitDir, 'refs/heads/main'));
    }

    #[Test]
    public function revertConflictStateMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir, 'revert' => $revertId] = $this->createRevertConflictPair();

        $this->gitWithExit($gitDir, 'revert ' . escapeshellarg($revertId));
        $repo = Pitmaster::open($pitDir);

        try {
            $repo->revert($revertId);
            self::fail('Expected revert conflict');
        } catch (MergeConflictException $e) {
            $this->assertStringContainsString('conflict', strtolower($e->getMessage()));
        }

        $this->assertSame($this->git($gitDir, 'ls-files --stage'), $this->git($pitDir, 'ls-files --stage'));
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->readGitFile($gitDir, 'REVERT_HEAD'), $this->readGitFile($pitDir, 'REVERT_HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'MERGE_MSG'), $this->readGitFile($pitDir, 'MERGE_MSG'));
        $this->assertSame(file_get_contents($gitDir . '/a.txt'), file_get_contents($pitDir . '/a.txt'));
    }

    #[Test]
    public function revertConflictContinuationMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir, 'revert' => $revertId] = $this->createRevertConflictPair();

        $this->gitWithExit($gitDir, 'revert ' . escapeshellarg($revertId));
        $repo = Pitmaster::open($pitDir);

        try {
            $repo->revert($revertId);
            self::fail('Expected revert conflict');
        } catch (MergeConflictException) {
        }

        $resolved = "line 1\nresolved\nline 3\n";
        $this->writeFile($gitDir, 'a.txt', $resolved);
        $this->git($gitDir, 'add a.txt');
        $this->withDeterministicCommitDates(function () use ($gitDir, $repo, $pitDir, $resolved): void {
            $this->git($gitDir, '-c core.editor=true revert --continue');
            $this->writeFile($pitDir, 'a.txt', $resolved);
            $repo->add('a.txt');
            $repo->revertContinue();
        });

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%P HEAD'), $this->git($pitDir, 'show -s --format=%P HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertSame($this->git($gitDir, "show -s --format='%an <%ae>' HEAD"), $this->git($pitDir, "show -s --format='%an <%ae>' HEAD"));
        $this->assertNull($this->readGitFile($pitDir, 'REVERT_HEAD'));
        $this->assertNull($this->readGitFile($pitDir, 'MERGE_MSG'));
        $this->assertSame($this->reflogMessage($gitDir), $this->reflogMessage($pitDir));
        $this->assertSame($this->reflogMessage($gitDir, 'refs/heads/main'), $this->reflogMessage($pitDir, 'refs/heads/main'));
    }

    #[Test]
    public function revertConflictAbortMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir, 'revert' => $revertId] = $this->createRevertConflictPair();

        $this->gitWithExit($gitDir, 'revert ' . escapeshellarg($revertId));
        $repo = Pitmaster::open($pitDir);

        try {
            $repo->revert($revertId);
            self::fail('Expected revert conflict');
        } catch (MergeConflictException) {
        }

        $this->gitWithExit($gitDir, 'revert --abort');
        $repo->revertAbort();

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%P HEAD'), $this->git($pitDir, 'show -s --format=%P HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertSame(file_get_contents($gitDir . '/a.txt'), file_get_contents($pitDir . '/a.txt'));
        $this->assertNull($this->readGitFile($pitDir, 'REVERT_HEAD'));
        $this->assertNull($this->readGitFile($pitDir, 'MERGE_MSG'));
        $this->assertSame($this->readGitFile($gitDir, 'ORIG_HEAD'), $this->readGitFile($pitDir, 'ORIG_HEAD'));
        $this->assertSame($this->reflogMessage($gitDir), $this->reflogMessage($pitDir));
        $this->assertSame($this->reflogMessage($gitDir, 'refs/heads/main'), $this->reflogMessage($pitDir, 'refs/heads/main'));
    }

    #[Test]
    public function cleanCherryPickWritesGitLikeReflogs(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir, 'pick' => $pickId] = $this->createCherryPickCleanPair();

        $this->gitWithExit($gitDir, 'cherry-pick ' . escapeshellarg($pickId));
        $repo = Pitmaster::open($pitDir);
        $repo->cherryPick($pickId);

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%P HEAD'), $this->git($pitDir, 'show -s --format=%P HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertSame($this->reflogMessage($gitDir), $this->reflogMessage($pitDir));
        $this->assertSame($this->reflogMessage($gitDir, 'refs/heads/main'), $this->reflogMessage($pitDir, 'refs/heads/main'));
    }

    #[Test]
    public function cleanRevertWritesGitLikeReflogs(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir, 'revert' => $revertId] = $this->createRevertCleanPair();

        $this->gitWithExit($gitDir, 'revert ' . escapeshellarg($revertId));
        $repo = Pitmaster::open($pitDir);
        $repo->revert($revertId);

        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%T HEAD'), $this->git($pitDir, 'show -s --format=%T HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%P HEAD'), $this->git($pitDir, 'show -s --format=%P HEAD'));
        $this->assertSame($this->git($gitDir, 'show -s --format=%s HEAD'), $this->git($pitDir, 'show -s --format=%s HEAD'));
        $this->assertSame($this->reflogMessage($gitDir), $this->reflogMessage($pitDir));
        $this->assertSame($this->reflogMessage($gitDir, 'refs/heads/main'), $this->reflogMessage($pitDir, 'refs/heads/main'));
    }

    /**
     * @return array{git: string, pit: string}
     */
    private function createMergeConflictPair(): array
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
        });
    }

    /**
     * @return array{git: string, pit: string}
     */
    private function createCleanMergePair(): array
    {
        return $this->createRepoPair(function (string $dir): void {
            $this->writeFile($dir, 'shared.txt', "base\n");
            $this->git($dir, 'add shared.txt');
            $this->git($dir, 'commit -m base');
            $this->git($dir, 'checkout -b feature');
            $this->writeFile($dir, 'feature.txt', "feature\n");
            $this->git($dir, 'add feature.txt');
            $this->git($dir, 'commit -m feature');
            $this->git($dir, 'checkout main');
            $this->writeFile($dir, 'main.txt', "main\n");
            $this->git($dir, 'add main.txt');
            $this->git($dir, 'commit -m main');
        });
    }

    /**
     * @return array{git: string, pit: string, pick: string}
     */
    private function createCherryPickConflictPair(): array
    {
        return $this->createRepoPair(function (string $dir): array {
            $this->writeFile($dir, 'a.txt', "line 1\nline 2\nline 3\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m base');
            $this->git($dir, 'checkout -b feature');
            $this->writeFile($dir, 'a.txt', "line 1\nfeature change\nline 3\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m feature');
            $pickId = trim($this->git($dir, 'rev-parse HEAD'));
            $this->git($dir, 'checkout main');
            $this->writeFile($dir, 'a.txt', "line 1\nmain change\nline 3\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m main');

            return ['pick' => $pickId];
        });
    }

    /**
     * @return array{git: string, pit: string, pick: string}
     */
    private function createCherryPickCleanPair(): array
    {
        return $this->createRepoPair(function (string $dir): array {
            $this->writeFile($dir, 'a.txt', "line 1\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m base');
            $this->git($dir, 'checkout -b feature');
            $this->writeFile($dir, 'feature.txt', "feature\n");
            $this->git($dir, 'add feature.txt');
            $this->git($dir, 'commit -m feature');
            $pickId = trim($this->git($dir, 'rev-parse HEAD'));
            $this->git($dir, 'checkout main');

            return ['pick' => $pickId];
        });
    }

    /**
     * @return array{git: string, pit: string, revert: string}
     */
    private function createRevertConflictPair(): array
    {
        return $this->createRepoPair(function (string $dir): array {
            $this->writeFile($dir, 'a.txt', "line 1\nline 2\nline 3\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m base');
            $this->writeFile($dir, 'a.txt', "line 1\nchange\nline 3\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m change');
            $revertId = trim($this->git($dir, 'rev-parse HEAD'));
            $this->git($dir, 'checkout -b other HEAD~1');
            $this->writeFile($dir, 'a.txt', "line 1\nother\nline 3\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m other');

            return ['revert' => $revertId];
        });
    }

    /**
     * @return array{git: string, pit: string, revert: string}
     */
    private function createRevertCleanPair(): array
    {
        return $this->createRepoPair(function (string $dir): array {
            $this->writeFile($dir, 'a.txt', "line 1\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m base');
            $this->writeFile($dir, 'a.txt', "line 1\nline 2\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m two');

            return ['revert' => trim($this->git($dir, 'rev-parse HEAD'))];
        });
    }

    /**
     * @param callable(string): array<string, string>|void $setup
     * @return array<string, string>
     */
    private function createRepoPair(callable $setup): array
    {
        $baseDir = $this->createTempDir('pitmaster-merge-base-');
        $gitDir = $this->createTempDir('pitmaster-merge-git-');
        $pitDir = $this->createTempDir('pitmaster-merge-pit-');
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

    private function readGitFile(string $dir, string $name): ?string
    {
        $path = $dir . '/.git/' . $name;

        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content !== false ? $content : null;
    }

    private function reflogMessage(string $dir, string $ref = 'HEAD'): string
    {
        $command = $ref === 'HEAD'
            ? 'reflog --format=%gs -n 1'
            : 'reflog show ' . escapeshellarg($ref) . ' --format=%gs -n 1';

        return trim($this->git($dir, $command));
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
