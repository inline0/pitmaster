<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Index\Index;
use Pitmaster\Index\IndexDiff;
use Pitmaster\Object\ObjectId;
use Pitmaster\Storage\ObjectDatabase;

final class IndexDiffTest extends TestCase
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
    public function diffAgainstTreeShowsAddedFiles(): void
    {
        // Create initial commit
        $this->writeFile('existing.txt', "exists\n");
        $this->git('add .');
        $this->git('commit -m "initial"');
        $commitId = trim($this->git('rev-parse HEAD'));

        // Stage a new file
        $this->writeFile('new.txt', "new content\n");
        $this->git('add new.txt');

        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $indexDiff = new IndexDiff($objects);
        $index = Index::open($this->tmpDir . '/.git/index');
        $diff = $indexDiff->diffAgainstTree($index, ObjectId::fromHex($commitId));

        $this->assertContains('new.txt', $diff['added']);
        $this->assertNotContains('existing.txt', $diff['added']);
        $this->assertEmpty($diff['deleted']);
    }

    #[Test]
    public function diffAgainstTreeShowsModifiedFiles(): void
    {
        $this->writeFile('file.txt', "original\n");
        $this->git('add .');
        $this->git('commit -m "initial"');
        $commitId = trim($this->git('rev-parse HEAD'));

        // Modify and re-stage
        $this->writeFile('file.txt', "modified\n");
        $this->git('add file.txt');

        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $indexDiff = new IndexDiff($objects);
        $index = Index::open($this->tmpDir . '/.git/index');
        $diff = $indexDiff->diffAgainstTree($index, ObjectId::fromHex($commitId));

        $this->assertContains('file.txt', $diff['modified']);
        $this->assertEmpty($diff['added']);
        $this->assertEmpty($diff['deleted']);
    }

    #[Test]
    public function diffAgainstTreeShowsDeletedFiles(): void
    {
        $this->writeFile('remove.txt', "will be removed\n");
        $this->writeFile('keep.txt', "stays\n");
        $this->git('add .');
        $this->git('commit -m "initial"');
        $commitId = trim($this->git('rev-parse HEAD'));

        // Remove from index
        $this->git('rm --cached remove.txt');

        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $indexDiff = new IndexDiff($objects);
        $index = Index::open($this->tmpDir . '/.git/index');
        $diff = $indexDiff->diffAgainstTree($index, ObjectId::fromHex($commitId));

        $this->assertContains('remove.txt', $diff['deleted']);
        $this->assertEmpty($diff['added']);
        $this->assertEmpty($diff['modified']);
    }

    #[Test]
    public function diffAgainstNullCommitTreatsAllAsAdded(): void
    {
        $this->writeFile('a.txt', "a\n");
        $this->writeFile('b.txt', "b\n");
        $this->git('add .');

        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $indexDiff = new IndexDiff($objects);
        $index = Index::open($this->tmpDir . '/.git/index');
        $diff = $indexDiff->diffAgainstTree($index, null);

        $this->assertCount(2, $diff['added']);
        $this->assertContains('a.txt', $diff['added']);
        $this->assertContains('b.txt', $diff['added']);
        $this->assertEmpty($diff['modified']);
        $this->assertEmpty($diff['deleted']);
    }
}
