<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Ref\Reflog;
use Pitmaster\Repository;

/**
 * Test Ref\Reflog against real git reflog.
 */
final class ReflogTest extends TestCase
{
    private string $tmpDir;
    private Repository $repo;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');
        $this->git('config core.logAllRefUpdates true');

        // Create 3 commits so the reflog has entries
        $this->writeFile('a.txt', "first\n");
        $this->git('add a.txt');
        $this->git('commit -m "First"');

        $this->writeFile('a.txt', "second\n");
        $this->git('add a.txt');
        $this->git('commit -m "Second"');

        $this->writeFile('a.txt', "third\n");
        $this->git('add a.txt');
        $this->git('commit -m "Third"');

        $this->repo = Pitmaster::open($this->tmpDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function entryCountMatchesGitReflog(): void
    {
        $gitCount = (int) trim($this->git('reflog --format="%H" | wc -l'));
        $reflog = Reflog::open($this->tmpDir . '/.git', 'HEAD');

        $this->assertSame($gitCount, $reflog->count());
    }

    #[Test]
    public function latestEntryReturnsNewestCommit(): void
    {
        $gitHead = trim($this->git('rev-parse HEAD'));
        $reflog = Reflog::open($this->tmpDir . '/.git', 'HEAD');

        $latest = $reflog->latest();
        $this->assertNotNull($latest);
        $this->assertSame($gitHead, $latest['new']);
    }

    #[Test]
    public function entriesAreInChronologicalOrder(): void
    {
        $reflog = Reflog::open($this->tmpDir . '/.git', 'HEAD');
        $entries = $reflog->entries();

        $this->assertGreaterThanOrEqual(3, count($entries));

        // The last entry's 'new' should be HEAD
        $gitHead = trim($this->git('rev-parse HEAD'));
        $this->assertSame($gitHead, $entries[count($entries) - 1]['new']);
    }

    #[Test]
    public function emptyReflogForNonexistentRef(): void
    {
        $reflog = Reflog::open($this->tmpDir . '/.git', 'refs/heads/nonexistent');

        $this->assertSame(0, $reflog->count());
        $this->assertNull($reflog->latest());
        $this->assertSame([], $reflog->entries());
    }

    #[Test]
    public function branchReflogExists(): void
    {
        $defaultBranch = trim($this->git('branch --show-current'));
        $reflog = Reflog::open($this->tmpDir . '/.git', 'refs/heads/' . $defaultBranch);

        // Branch reflog should have entries from commits
        $this->assertGreaterThan(0, $reflog->count());
    }

    #[Test]
    public function pitmasterCommitWritesHeadAndBranchReflogs(): void
    {
        $defaultBranch = trim($this->git('branch --show-current'));
        $oldHead = $this->repo->head()->id->hex;

        $this->writeFile('a.txt', "fourth\n");
        $this->repo->add('a.txt');
        $newId = $this->repo->commit("Fourth\n");

        $headLog = Reflog::open($this->tmpDir . '/.git', 'HEAD')->latest();
        $branchLog = Reflog::open($this->tmpDir . '/.git', 'refs/heads/' . $defaultBranch)->latest();

        $this->assertNotNull($headLog);
        $this->assertNotNull($branchLog);
        $this->assertSame($oldHead, $headLog['old']);
        $this->assertSame($newId->hex, $headLog['new']);
        $this->assertStringContainsString('commit: Fourth', $headLog['message']);
        $this->assertSame($oldHead, $branchLog['old']);
        $this->assertSame($newId->hex, $branchLog['new']);
    }

    #[Test]
    public function pitmasterCreateBranchWritesBranchReflog(): void
    {
        $this->repo->createBranch('feature');

        $reflog = Reflog::open($this->tmpDir . '/.git', 'refs/heads/feature')->latest();

        $this->assertNotNull($reflog);
        $this->assertSame(str_repeat('0', 40), $reflog['old']);
        $this->assertSame($this->repo->head()->id->hex, $reflog['new']);
        $this->assertStringContainsString('branch: Created from HEAD', $reflog['message']);
    }

    #[Test]
    public function pitmasterCheckoutWritesHeadReflog(): void
    {
        $this->repo->createBranch('feature');
        $oldHead = $this->repo->head()->id->hex;

        $this->repo->checkout('feature');

        $reflog = Reflog::open($this->tmpDir . '/.git', 'HEAD')->latest();

        $this->assertNotNull($reflog);
        $this->assertSame($oldHead, $reflog['old']);
        $this->assertSame($oldHead, $reflog['new']);
        $this->assertStringContainsString('checkout: moving from main to feature', $reflog['message']);
    }

    #[Test]
    public function pitmasterResetWritesHeadAndBranchReflogs(): void
    {
        $defaultBranch = trim($this->git('branch --show-current'));

        $this->writeFile('a.txt', "fourth\n");
        $this->repo->add('a.txt');
        $newId = $this->repo->commit("Fourth\n");
        $target = trim($this->git('rev-parse HEAD~1'));

        $this->repo->reset($target, 'mixed');

        $headLog = Reflog::open($this->tmpDir . '/.git', 'HEAD')->latest();
        $branchLog = Reflog::open($this->tmpDir . '/.git', 'refs/heads/' . $defaultBranch)->latest();

        $this->assertNotNull($headLog);
        $this->assertNotNull($branchLog);
        $this->assertSame($newId->hex, $headLog['old']);
        $this->assertSame($target, $headLog['new']);
        $this->assertStringContainsString("reset: moving to {$target}", $headLog['message']);
        $this->assertSame($newId->hex, $branchLog['old']);
        $this->assertSame($target, $branchLog['new']);
    }

    #[Test]
    public function pitmasterFastForwardMergeWritesHeadAndBranchReflogs(): void
    {
        $defaultBranch = trim($this->git('branch --show-current'));
        $this->git('checkout -b feature');
        $this->writeFile('feature.txt', "feature\n");
        $this->git('add feature.txt');
        $this->git('commit -m "Feature"');
        $featureHead = trim($this->git('rev-parse HEAD'));
        $this->git('checkout ' . $defaultBranch);
        $oldHead = $this->repo->head()->id->hex;
        $this->repo = Pitmaster::open($this->tmpDir);

        $result = $this->repo->merge('feature');

        $this->assertTrue($result->clean);
        $headLog = Reflog::open($this->tmpDir . '/.git', 'HEAD')->latest();
        $branchLog = Reflog::open($this->tmpDir . '/.git', 'refs/heads/' . $defaultBranch)->latest();

        $this->assertNotNull($headLog);
        $this->assertNotNull($branchLog);
        $this->assertSame($oldHead, $headLog['old']);
        $this->assertSame($featureHead, $headLog['new']);
        $this->assertStringContainsString('merge feature: Fast-forward', $headLog['message']);
        $this->assertSame($oldHead, $branchLog['old']);
        $this->assertSame($featureHead, $branchLog['new']);
    }

    #[Test]
    public function pitmasterDeleteBranchRemovesBranchReflog(): void
    {
        $this->repo->createBranch('feature-delete');

        $this->assertFileExists($this->tmpDir . '/.git/logs/refs/heads/feature-delete');

        $this->repo->deleteBranch('feature-delete');

        $this->assertFileDoesNotExist($this->tmpDir . '/.git/logs/refs/heads/feature-delete');
    }

    #[Test]
    public function pitmasterAddWorktreeWritesLinkedHeadAndBranchReflogs(): void
    {
        $linkedPath = $this->tmpDir . '-linked-reflog';
        $headId = $this->repo->head()->id->hex;

        try {
            $worktree = $this->repo->addWorktree($linkedPath, 'linked-feature', name: 'linked-feature');

            $branchLog = Reflog::open($this->tmpDir . '/.git', 'refs/heads/linked-feature')->latest();
            $headLog = Reflog::open($worktree->gitDir, 'HEAD')->latest();

            $this->assertNotNull($branchLog);
            $this->assertNotNull($headLog);
            $this->assertSame(str_repeat('0', 40), $branchLog['old']);
            $this->assertSame($headId, $branchLog['new']);
            $this->assertSame('branch: Created from HEAD', $branchLog['message']);
            $this->assertSame($headId, $headLog['old']);
            $this->assertSame($headId, $headLog['new']);
            $this->assertSame('reset: moving to HEAD', $headLog['message']);
        } finally {
            exec('rm -rf ' . escapeshellarg($linkedPath));
        }
    }

    private function git(string $cmd): string
    {
        return shell_exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $cmd)) ?? '';
    }

    private function writeFile(string $path, string $content): void
    {
        $full = $this->tmpDir . '/' . $path;
        $dir = dirname($full);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($full, $content);
    }
}
