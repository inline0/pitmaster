<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Repository;
use Pitmaster\Worktree\WorktreeManager;

/**
 * Test Worktree\WorktreeManager against a real git repository.
 */
final class WorktreeTest extends TestCase
{
    private string $tmpDir;
    private string $gitDir;
    private Repository $repo;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');
        $this->gitDir = $this->tmpDir . '/.git';
        $this->repo = Pitmaster::open($this->tmpDir);

        // Need at least one commit for worktree operations
        $this->writeFile('a.txt', "content\n");
        $this->git('add a.txt');
        $this->git('commit -m "Initial commit"');

        // Create a branch for the linked worktree
        $this->git('branch feature');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function listReturnsMainWorktree(): void
    {
        $manager = new WorktreeManager($this->gitDir, $this->tmpDir);
        $worktrees = $manager->list();

        $this->assertCount(1, $worktrees);
        $this->assertTrue($worktrees[0]->isMain);
        $this->assertSame($this->tmpDir, $worktrees[0]->path);
    }

    #[Test]
    public function addCreatesLinkedWorktree(): void
    {
        $manager = new WorktreeManager($this->gitDir, $this->tmpDir);
        $linkedPath = $this->tmpDir . '-linked';

        $wt = $manager->add($linkedPath, 'feature');

        $this->assertFalse($wt->isMain);
        $this->assertSame(basename($linkedPath), $wt->name);
        $this->assertSame('feature', $wt->branch);

        // Metadata directory should exist
        $this->assertDirectoryExists($this->gitDir . '/worktrees/' . basename($linkedPath));

        // .git file in linked worktree should exist
        $this->assertFileExists($linkedPath . '/.git');

        // Clean up linked worktree
        exec('rm -rf ' . escapeshellarg($linkedPath));
    }

    #[Test]
    public function addAllowsSameBasenameWhenNamesDiffer(): void
    {
        $manager = new WorktreeManager($this->gitDir, $this->tmpDir);
        $firstPath = $this->tmpDir . '-a/divine-child';
        $secondPath = $this->tmpDir . '-b/divine-child';

        $first = $manager->add($firstPath, 'feature', 'app-divine-child');
        $second = $manager->add($secondPath, 'feature', 'sandbox-divine-child');

        $this->assertSame('app-divine-child', $first->name);
        $this->assertSame('sandbox-divine-child', $second->name);
        $this->assertSame($firstPath, $first->path);
        $this->assertSame($secondPath, $second->path);
        $this->assertDirectoryExists($this->gitDir . '/worktrees/app-divine-child');
        $this->assertDirectoryExists($this->gitDir . '/worktrees/sandbox-divine-child');
        $this->assertFileExists($firstPath . '/.git');
        $this->assertFileExists($secondPath . '/.git');

        $worktrees = $manager->list();
        $linkedNames = [];

        foreach ($worktrees as $worktree) {
            if (!$worktree->isMain) {
                $linkedNames[] = $worktree->name;
            }
        }

        sort($linkedNames);

        $this->assertSame(['app-divine-child', 'sandbox-divine-child'], $linkedNames);

        $manager->remove($firstPath);
        $manager->remove('sandbox-divine-child');

        $this->assertDirectoryDoesNotExist($this->gitDir . '/worktrees/app-divine-child');
        $this->assertDirectoryDoesNotExist($this->gitDir . '/worktrees/sandbox-divine-child');

        exec('rm -rf ' . escapeshellarg(dirname($firstPath)));
        exec('rm -rf ' . escapeshellarg(dirname($secondPath)));
    }

    #[Test]
    public function addThenListShowsBothWorktrees(): void
    {
        $manager = new WorktreeManager($this->gitDir, $this->tmpDir);
        $linkedPath = $this->tmpDir . '-wt2';

        $manager->add($linkedPath, 'feature');

        $worktrees = $manager->list();
        $this->assertCount(2, $worktrees);

        $mainCount = 0;
        $linkedCount = 0;

        foreach ($worktrees as $wt) {
            if ($wt->isMain) {
                $mainCount++;
            } else {
                $linkedCount++;
            }
        }

        $this->assertSame(1, $mainCount);
        $this->assertSame(1, $linkedCount);

        // Clean up
        exec('rm -rf ' . escapeshellarg($linkedPath));
    }

    #[Test]
    public function removeRemovesWorktreeMetadata(): void
    {
        $manager = new WorktreeManager($this->gitDir, $this->tmpDir);
        $linkedPath = $this->tmpDir . '-removable';

        $manager->add($linkedPath, 'feature');
        $name = basename($linkedPath);

        $this->assertDirectoryExists($this->gitDir . '/worktrees/' . $name);

        $manager->remove($name);

        $this->assertDirectoryDoesNotExist($this->gitDir . '/worktrees/' . $name);

        // Clean up
        exec('rm -rf ' . escapeshellarg($linkedPath));
    }

    #[Test]
    public function lockAndUnlock(): void
    {
        $manager = new WorktreeManager($this->gitDir, $this->tmpDir);
        $linkedPath = $this->tmpDir . '-lockable';

        $manager->add($linkedPath, 'feature');
        $name = basename($linkedPath);

        // Lock with a reason
        $manager->lock($name, 'maintenance');
        $this->assertFileExists($this->gitDir . '/worktrees/' . $name . '/locked');

        $lockContent = trim(file_get_contents($this->gitDir . '/worktrees/' . $name . '/locked'));
        $this->assertSame('maintenance', $lockContent);

        // Removing a locked worktree should throw
        $this->expectException(\RuntimeException::class);

        try {
            $manager->remove($name);
        } finally {
            // Unlock and clean up
            $manager->unlock($name);
            $this->assertFileDoesNotExist($this->gitDir . '/worktrees/' . $name . '/locked');
            $manager->remove($name);
            exec('rm -rf ' . escapeshellarg($linkedPath));
        }
    }

    #[Test]
    public function mainWorktreeHasBranchInfo(): void
    {
        $manager = new WorktreeManager($this->gitDir, $this->tmpDir);
        $worktrees = $manager->list();

        $main = $worktrees[0];
        $this->assertTrue($main->isMain);
        $this->assertFalse($main->isDetached);
        $this->assertNotNull($main->branch);
        $this->assertNotNull($main->head);
        $this->assertNull($main->name);
    }

    #[Test]
    public function repositoryApiPassesExplicitMetadataNamesThrough(): void
    {
        $firstPath = $this->tmpDir . '-repo-a/divine-child';
        $secondPath = $this->tmpDir . '-repo-b/divine-child';

        $first = $this->repo->addWorktree($firstPath, 'feature', name: 'app-theme');
        $second = $this->repo->addWorktree($secondPath, 'feature', name: 'sandbox-theme');

        $this->assertSame('app-theme', $first->name);
        $this->assertSame('sandbox-theme', $second->name);
        $this->assertDirectoryExists($this->gitDir . '/worktrees/app-theme');
        $this->assertDirectoryExists($this->gitDir . '/worktrees/sandbox-theme');

        $linkedNames = [];

        foreach ($this->repo->worktrees() as $worktree) {
            if (!$worktree->isMain) {
                $linkedNames[] = $worktree->name;
            }
        }

        sort($linkedNames);

        $this->assertSame(['app-theme', 'sandbox-theme'], $linkedNames);

        $this->repo->removeWorktree($firstPath);
        $this->repo->removeWorktree('sandbox-theme');

        $this->assertDirectoryDoesNotExist($this->gitDir . '/worktrees/app-theme');
        $this->assertDirectoryDoesNotExist($this->gitDir . '/worktrees/sandbox-theme');

        exec('rm -rf ' . escapeshellarg(dirname($firstPath)));
        exec('rm -rf ' . escapeshellarg(dirname($secondPath)));
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
