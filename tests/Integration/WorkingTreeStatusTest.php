<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Index\Index;
use Pitmaster\Object\ObjectId;
use Pitmaster\Status\FileStatus;
use Pitmaster\Status\WorkingTreeStatus;
use Pitmaster\Storage\ObjectDatabase;

final class WorkingTreeStatusTest extends TestCase
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

    private function git(string $cmd): string
    {
        return shell_exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $cmd)) ?? '';
    }

    private function writeFile(string $p, string $c): void
    {
        $d = $this->tmpDir . '/' . $p;
        if (!is_dir(dirname($d))) {
            mkdir(dirname($d), 0777, true);
        }
        file_put_contents($d, $c);
    }

    #[Test]
    public function statusDetectsAllChangeTypes(): void
    {
        // Create initial commit
        $this->writeFile('committed.txt', "committed\n");
        $this->git('add .');
        $this->git('commit -m "initial"');
        $headHash = trim($this->git('rev-parse HEAD'));

        // Create various changes
        $this->writeFile('committed.txt', "modified in worktree\n"); // Modified (unstaged)
        $this->writeFile('staged.txt', "staged\n");
        $this->git('add staged.txt'); // Added (staged)
        $this->writeFile('untracked.txt', "untracked\n"); // Untracked

        // Get Pitmaster status
        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $wts = new WorkingTreeStatus($objects, $this->tmpDir);
        $index = Index::open($this->tmpDir . '/.git/index');
        $entries = $wts->compute($index, ObjectId::fromHex($headHash));

        $paths = array_map(fn ($e) => $e->path, $entries);

        // All changed files should appear
        $this->assertContains('committed.txt', $paths);
        $this->assertContains('staged.txt', $paths);
        $this->assertContains('untracked.txt', $paths);

        // Verify specific statuses
        $map = [];
        foreach ($entries as $e) {
            $map[$e->path] = $e;
        }

        // committed.txt: worktree modified
        $this->assertSame(FileStatus::Modified, $map['committed.txt']->worktree);

        // staged.txt: index added
        $this->assertSame(FileStatus::Added, $map['staged.txt']->index);

        // untracked.txt: untracked
        $this->assertSame(FileStatus::Untracked, $map['untracked.txt']->index);
    }

    #[Test]
    public function cleanRepoReturnsEmpty(): void
    {
        $this->writeFile('file.txt', "content\n");
        $this->git('add .');
        $this->git('commit -m "initial"');
        $headHash = trim($this->git('rev-parse HEAD'));

        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $wts = new WorkingTreeStatus($objects, $this->tmpDir);
        $index = Index::open($this->tmpDir . '/.git/index');
        $entries = $wts->compute($index, ObjectId::fromHex($headHash));

        // Filter out untracked
        $tracked = array_filter($entries, fn ($e) => $e->index !== FileStatus::Untracked);
        $this->assertEmpty($tracked);
    }

    #[Test]
    public function deletedFileDetected(): void
    {
        $this->writeFile('file.txt', "content\n");
        $this->git('add .');
        $this->git('commit -m "initial"');
        $headHash = trim($this->git('rev-parse HEAD'));

        // Delete from worktree (not from index)
        unlink($this->tmpDir . '/file.txt');

        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $wts = new WorkingTreeStatus($objects, $this->tmpDir);
        $index = Index::open($this->tmpDir . '/.git/index');
        $entries = $wts->compute($index, ObjectId::fromHex($headHash));

        $fileEntry = null;
        foreach ($entries as $entry) {
            if ($entry->path === 'file.txt') {
                $fileEntry = $entry;
                break;
            }
        }

        $this->assertNotNull($fileEntry);
        $this->assertSame(FileStatus::Deleted, $fileEntry->worktree);
    }

    #[Test]
    public function stagedAdditionDetected(): void
    {
        $this->writeFile('existing.txt', "exists\n");
        $this->git('add .');
        $this->git('commit -m "initial"');
        $headHash = trim($this->git('rev-parse HEAD'));

        $this->writeFile('new.txt', "new\n");
        $this->git('add new.txt');

        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $wts = new WorkingTreeStatus($objects, $this->tmpDir);
        $index = Index::open($this->tmpDir . '/.git/index');
        $entries = $wts->compute($index, ObjectId::fromHex($headHash));

        $newEntry = null;
        foreach ($entries as $entry) {
            if ($entry->path === 'new.txt') {
                $newEntry = $entry;
                break;
            }
        }

        $this->assertNotNull($newEntry);
        $this->assertSame(FileStatus::Added, $newEntry->index);
    }

    #[Test]
    public function stagedRenameDetected(): void
    {
        $this->writeFile('old.txt', "content\n");
        $this->git('add old.txt');
        $this->git('commit -m "initial"');
        $headHash = trim($this->git('rev-parse HEAD'));

        $this->git('mv old.txt new.txt');

        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $wts = new WorkingTreeStatus($objects, $this->tmpDir, $this->tmpDir . '/.git');
        $index = Index::open($this->tmpDir . '/.git/index');
        $entries = $wts->compute($index, ObjectId::fromHex($headHash));

        $this->assertCount(1, $entries);
        $this->assertSame('new.txt', $entries[0]->path);
        $this->assertSame('old.txt', $entries[0]->origPath);
        $this->assertSame(FileStatus::Renamed, $entries[0]->index);
        $this->assertSame(100, $entries[0]->renameScore);
    }
}
