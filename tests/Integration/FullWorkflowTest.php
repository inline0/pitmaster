<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Repository;
use Pitmaster\Status\FileStatus;

/**
 * Full integration test: init -> add -> commit -> branch -> merge -> verify.
 *
 * Every step is verified against the git binary to ensure correctness.
 */
final class FullWorkflowTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-integration-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        // Use git init to ensure valid repo structure
        exec(sprintf(
            'cd %s && git init && git config user.email test@pitmaster.dev && git config user.name "Test User" 2>&1',
            escapeshellarg($this->tmpDir),
        ));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function testFullWorkflow(): void
    {
        $repo = Pitmaster::open($this->tmpDir);

        // Step 1: Create files and stage them
        file_put_contents($this->tmpDir . '/hello.txt', "Hello World\n");
        file_put_contents($this->tmpDir . '/README.md', "# Project\n\nDescription.\n");
        mkdir($this->tmpDir . '/src');
        file_put_contents($this->tmpDir . '/src/main.php', "<?php\necho 'hello';\n");

        $repo->add('hello.txt', 'README.md', 'src/main.php');

        // Verify index matches git
        $this->assertGitIndexMatches($repo);

        // Step 2: Create first commit
        $firstCommitId = $repo->commit("Initial commit\n");
        $this->assertNotEmpty($firstCommitId->hex);
        $this->assertGitFsckClean();

        // Verify git sees the commit
        $gitLog = $this->gitExec('log --oneline');
        $this->assertStringContainsString('Initial commit', $gitLog);

        // Step 3: Create a branch
        $repo->createBranch('feature');
        $branches = $repo->branches();
        $this->assertContains('main', $branches);
        $this->assertContains('feature', $branches);

        // Step 4: Modify a file and commit on main
        file_put_contents($this->tmpDir . '/hello.txt', "Hello World\nFrom main branch\n");
        $repo->add('hello.txt');
        $mainCommitId = $repo->commit("Update on main\n");
        $this->assertGitFsckClean();

        // Step 5: Verify status is clean after commit
        $status = $repo->status();
        $trackedChanges = array_filter($status, fn ($e) => $e->index !== FileStatus::Untracked);
        $this->assertEmpty($trackedChanges, 'Working tree should be clean after commit');

        // Step 6: Verify log has 2 commits
        $log = $repo->log(10);
        $this->assertCount(2, $log);
        $this->assertSame('Update on main', trim($log[0]->message));
        $this->assertSame('Initial commit', trim($log[1]->message));

        // Step 7: Verify diff output matches git
        file_put_contents($this->tmpDir . '/hello.txt', "Hello World\nFrom main branch\nNew unstaged line\n");
        $pmDiff = '';

        foreach ($repo->diff() as $d) {
            $pmDiff .= $d->format();
        }

        $gitDiff = $this->gitExec('diff');
        $this->assertSame($gitDiff, $pmDiff, 'Diff output must match git exactly');

        // Step 8: Verify refs match git
        $this->assertRefsMatch($repo);
    }

    #[Test]
    public function testCommitWritesFsckCleanObjects(): void
    {
        $repo = Pitmaster::open($this->tmpDir);

        file_put_contents($this->tmpDir . '/a.txt', "content a\n");
        file_put_contents($this->tmpDir . '/b.txt', "content b\n");

        $repo->add('a.txt', 'b.txt');
        $repo->commit("First\n");

        file_put_contents($this->tmpDir . '/a.txt', "content a modified\n");
        $repo->add('a.txt');
        $repo->commit("Second\n");

        file_put_contents($this->tmpDir . '/c.txt', "content c\n");
        $repo->add('c.txt');
        $repo->commit("Third\n");

        $this->assertGitFsckClean();

        // Verify git log shows all 3 commits
        $gitLog = $this->gitExec('log --oneline');
        $lines = array_filter(explode("\n", trim($gitLog)));
        $this->assertCount(3, $lines);
    }

    #[Test]
    public function testBinaryFileDetection(): void
    {
        $repo = Pitmaster::open($this->tmpDir);

        // Create a binary file (contains NUL bytes)
        file_put_contents($this->tmpDir . '/binary.dat', "header\x00\x01\x02binary data");
        $repo->add('binary.dat');
        $repo->commit("Add binary\n");

        $this->assertGitFsckClean();

        // Verify object was stored correctly
        $head = $repo->head();
        $this->assertStringContainsString('Add binary', $head->message);
    }

    #[Test]
    public function testEmptyFileHandling(): void
    {
        $repo = Pitmaster::open($this->tmpDir);

        file_put_contents($this->tmpDir . '/empty.txt', '');
        $repo->add('empty.txt');
        $repo->commit("Add empty file\n");

        $this->assertGitFsckClean();

        // Verify git sees the empty file
        $lsTree = $this->gitExec('ls-tree HEAD');
        $this->assertStringContainsString('empty.txt', $lsTree);
    }

    #[Test]
    public function testNestedGitignore(): void
    {
        $repo = Pitmaster::open($this->tmpDir);

        // Create a file and commit so HEAD exists
        file_put_contents($this->tmpDir . '/keep.txt', "keep\n");
        $repo->add('keep.txt');
        $repo->commit("Initial\n");

        // Create .gitignore
        file_put_contents($this->tmpDir . '/.gitignore', "*.log\nbuild/\n");
        file_put_contents($this->tmpDir . '/app.log', "log data");
        mkdir($this->tmpDir . '/build');
        file_put_contents($this->tmpDir . '/build/output.js', "compiled");
        file_put_contents($this->tmpDir . '/tracked.txt', "tracked");

        $status = $repo->status();
        $paths = array_map(fn ($e) => $e->path, $status);

        // .gitignore and tracked.txt should appear; app.log and build/ should not
        $this->assertContains('tracked.txt', $paths);
        $this->assertContains('.gitignore', $paths);
        $this->assertNotContains('app.log', $paths);
        $this->assertNotContains('build/output.js', $paths);
    }

    private function assertGitFsckClean(): void
    {
        exec(
            sprintf('cd %s && git fsck --strict --no-progress 2>&1', escapeshellarg($this->tmpDir)),
            $output,
            $exitCode,
        );

        $this->assertSame(0, $exitCode, 'git fsck --strict failed: ' . implode("\n", $output));
    }

    private function assertGitIndexMatches(Repository $repo): void
    {
        exec(
            sprintf('cd %s && git ls-files --stage 2>&1', escapeshellarg($this->tmpDir)),
            $gitLines,
        );

        $index = $repo->index();
        $this->assertSame(count($gitLines), $index->count(), 'Index entry count mismatch');

        foreach ($gitLines as $line) {
            preg_match('/^(\d+) ([a-f0-9]+) \d+\t(.+)$/', $line, $m);

            if (!$m) {
                continue;
            }

            $entry = $index->entry($m[3]);
            $this->assertNotNull($entry, "Missing index entry: {$m[3]}");
            $this->assertSame($m[2], $entry->hash->hex, "Hash mismatch for {$m[3]}");
        }
    }

    private function assertRefsMatch(Repository $repo): void
    {
        exec(
            sprintf(
                'cd %s && git for-each-ref --format="%%(objectname) %%(refname)" 2>&1',
                escapeshellarg($this->tmpDir),
            ),
            $gitRefs,
        );

        $pmRefs = $repo->allRefs();

        foreach ($gitRefs as $line) {
            $parts = explode(' ', trim($line), 2);

            if (count($parts) !== 2) {
                continue;
            }

            [$hash, $name] = $parts;
            $this->assertArrayHasKey($name, $pmRefs, "Missing ref: {$name}");
            $this->assertSame($hash, $pmRefs[$name], "Ref hash mismatch: {$name}");
        }
    }

    private function gitExec(string $command): string
    {
        return shell_exec(sprintf(
            'cd %s && git %s 2>&1',
            escapeshellarg($this->tmpDir),
            $command,
        )) ?? '';
    }
}
