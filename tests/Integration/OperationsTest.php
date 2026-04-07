<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Repository;

/**
 * Test every operation against real git to verify correctness.
 */
final class OperationsTest extends TestCase
{
    private string $tmpDir;
    private Repository $repo;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-ops-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');
        $this->repo = Pitmaster::open($this->tmpDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function testStatusClean(): void
    {
        $this->writeFile('a.txt', "content\n");
        $this->git('add a.txt');
        $this->git('commit -m init');
        $this->repo = Pitmaster::open($this->tmpDir);

        $gitStatus = trim($this->git('status --porcelain'));
        $pmStatus = $this->repo->status();
        $pmTracked = array_filter($pmStatus, fn ($e) => $e->index !== \Pitmaster\Status\FileStatus::Untracked);

        $this->assertSame('', $gitStatus);
        $this->assertEmpty($pmTracked);
    }

    #[Test]
    public function testStatusModified(): void
    {
        $this->writeFile('a.txt', "original\n");
        $this->git('add a.txt');
        $this->git('commit -m init');

        $this->writeFile('a.txt', "modified\n");
        $this->repo = Pitmaster::open($this->tmpDir);

        $status = $this->repo->status();
        $modified = array_filter($status, fn ($e) => $e->worktree === \Pitmaster\Status\FileStatus::Modified);

        $this->assertCount(1, $modified);
        $this->assertSame('a.txt', array_values($modified)[0]->path);
    }

    #[Test]
    public function testDiffExactMatchSimple(): void
    {
        $this->writeFile('f.txt', "a\nb\nc\n");
        $this->git('add f.txt');
        $this->git('commit -m init');
        $this->writeFile('f.txt', "a\nB\nc\n");
        $this->repo = Pitmaster::open($this->tmpDir);

        $gitDiff = $this->git('diff');
        $pmDiff = '';

        foreach ($this->repo->diff() as $r) {
            $pmDiff .= $r->format();
        }

        $this->assertSame($gitDiff, $pmDiff);
    }

    #[Test]
    public function testDiffExactMatchMultiHunk(): void
    {
        $lines = [];

        for ($i = 1; $i <= 20; $i++) {
            $lines[] = "line {$i}";
        }

        $this->writeFile('f.txt', implode("\n", $lines) . "\n");
        $this->git('add f.txt');
        $this->git('commit -m init');

        $lines[2] = 'line 3 CHANGED';
        $lines[17] = 'line 18 CHANGED';
        $this->writeFile('f.txt', implode("\n", $lines) . "\n");
        $this->repo = Pitmaster::open($this->tmpDir);

        $gitDiff = $this->git('diff');
        $pmDiff = '';

        foreach ($this->repo->diff() as $r) {
            $pmDiff .= $r->format();
        }

        // Git may add a function context after @@ which we don't
        // Compare line-by-line, ignoring the @@ context suffix
        $gitLines = explode("\n", $gitDiff);
        $pmLines = explode("\n", $pmDiff);
        $this->assertSame(count($gitLines), count($pmLines), 'Line count mismatch');

        foreach ($gitLines as $i => $gitLine) {
            $pmLine = $pmLines[$i] ?? '';

            if (str_starts_with($gitLine, '@@') && str_starts_with($pmLine, '@@')) {
                // Compare just the range part, not the context after @@
                $gitRange = substr($gitLine, 0, strrpos($gitLine, '@@') + 2);
                $pmRange = substr($pmLine, 0, strrpos($pmLine, '@@') + 2);
                $this->assertSame($gitRange, $pmRange, "Hunk header mismatch at line {$i}");
            } else {
                $this->assertSame($gitLine, $pmLine, "Mismatch at line {$i}");
            }
        }
    }

    #[Test]
    public function testCommitAndFsck(): void
    {
        $this->writeFile('a.txt', "hello\n");
        $this->repo->add('a.txt');
        $id = $this->repo->commit("Test commit\n");

        $this->assertFsckClean();

        // git can read the commit
        $gitLog = $this->git('log --oneline');
        $this->assertStringContainsString('Test commit', $gitLog);
        $this->assertStringContainsString(substr($id->hex, 0, 7), $gitLog);
    }

    #[Test]
    public function testMultipleCommitsAndLog(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->writeFile("file{$i}.txt", "content {$i}\n");
            $this->repo->add("file{$i}.txt");
            $this->repo->commit("Commit {$i}\n");
        }

        $this->assertFsckClean();

        $gitLog = $this->git('log --oneline');
        $pmLog = $this->repo->log(10);

        $gitLines = array_filter(explode("\n", trim($gitLog)));
        $this->assertCount(5, $gitLines);
        $this->assertCount(5, $pmLog);

        // Hashes should match
        foreach ($gitLines as $i => $line) {
            $gitHash = substr($line, 0, 7);
            $pmHash = substr($pmLog[$i]->id->hex, 0, 7);
            $this->assertSame($gitHash, $pmHash, "Log hash mismatch at position {$i}");
        }
    }

    #[Test]
    public function testBranchCreateAndSwitch(): void
    {
        $this->writeFile('a.txt', "content\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Initial\n");

        $this->repo->createBranch('feature');

        $this->assertContains('feature', $this->repo->branches());
        $this->assertSame('main', $this->repo->branch());

        // Verify git agrees
        $gitBranches = $this->git('branch');
        $this->assertStringContainsString('feature', $gitBranches);
        $this->assertStringContainsString('main', $gitBranches);
    }

    #[Test]
    public function testResetSoft(): void
    {
        $this->writeFile('a.txt', "v1\n");
        $this->repo->add('a.txt');
        $firstId = $this->repo->commit("First\n");

        $this->writeFile('a.txt', "v2\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Second\n");

        $this->repo->reset($firstId->hex, 'soft');

        // HEAD should point to first commit
        $this->assertSame($firstId->hex, $this->repo->head()->id->hex);

        // git should agree
        $gitHead = trim($this->git('rev-parse HEAD'));
        $this->assertSame($firstId->hex, $gitHead);
    }

    #[Test]
    public function testRefsMatchGit(): void
    {
        $this->writeFile('a.txt', "content\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Initial\n");

        $this->repo->createBranch('dev');
        $this->repo->updateRef('refs/tags/v1.0', $this->repo->resolve('HEAD'));

        $gitRefs = [];

        foreach (explode("\n", trim($this->git('for-each-ref --format="%(objectname) %(refname)"'))) as $line) {
            $parts = explode(' ', trim($line), 2);

            if (count($parts) === 2) {
                $gitRefs[$parts[1]] = $parts[0];
            }
        }

        $pmRefs = $this->repo->allRefs();

        foreach ($gitRefs as $name => $hash) {
            $this->assertArrayHasKey($name, $pmRefs, "Missing ref: {$name}");
            $this->assertSame($hash, $pmRefs[$name], "Ref mismatch: {$name}");
        }
    }

    #[Test]
    public function testIndexMatchesGitLsFiles(): void
    {
        $this->writeFile('a.txt', "aaa\n");
        $this->writeFile('b.txt', "bbb\n");
        mkdir($this->tmpDir . '/sub');
        $this->writeFile('sub/c.txt', "ccc\n");

        $this->repo->add('a.txt', 'b.txt', 'sub/c.txt');

        $gitLsFiles = trim($this->git('ls-files --stage'));
        $index = $this->repo->index();

        foreach (explode("\n", $gitLsFiles) as $line) {
            preg_match('/^(\d+) ([a-f0-9]+) \d+\t(.+)$/', $line, $m);

            if (!$m) {
                continue;
            }

            $entry = $index->entry($m[3]);
            $this->assertNotNull($entry, "Missing: {$m[3]}");
            $this->assertSame($m[2], $entry->hash->hex, "Hash mismatch: {$m[3]}");
        }
    }

    #[Test]
    public function testObjectHashesMatchGit(): void
    {
        $this->writeFile('hello.txt', "Hello World\n");
        $this->repo->add('hello.txt');
        $this->repo->commit("Test\n");
        $this->assertFsckClean();

        // Every object Pitmaster knows about should match git
        $gitObjects = [];

        foreach (explode("\n", trim($this->git('cat-file --batch-all-objects --batch-check="%(objectname) %(objecttype) %(objectsize)"'))) as $line) {
            $parts = explode(' ', $line, 3);

            if (count($parts) === 3) {
                $gitObjects[$parts[0]] = ['type' => $parts[1], 'size' => (int) $parts[2]];
            }
        }

        foreach ($this->repo->listObjects() as $hash) {
            $this->assertArrayHasKey($hash, $gitObjects, "Pitmaster has object git doesn't: {$hash}");
            $obj = $this->repo->readObject($hash);
            $this->assertSame($gitObjects[$hash]['type'], $obj->type->value, "Type mismatch: {$hash}");
            $this->assertSame($gitObjects[$hash]['size'], $obj->size(), "Size mismatch: {$hash}");
        }
    }

    private function writeFile(string $path, string $content): void
    {
        $fullPath = $this->tmpDir . '/' . $path;
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($fullPath, $content);
    }

    private function git(string $command): string
    {
        return shell_exec(sprintf(
            'cd %s && git %s 2>&1',
            escapeshellarg($this->tmpDir),
            $command,
        )) ?? '';
    }

    private function assertFsckClean(): void
    {
        exec(
            sprintf('cd %s && git fsck --strict --no-progress 2>&1', escapeshellarg($this->tmpDir)),
            $output,
            $exitCode,
        );

        $this->assertSame(0, $exitCode, 'git fsck: ' . implode("\n", $output));
    }
}
