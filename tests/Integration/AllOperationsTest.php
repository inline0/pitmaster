<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Repository;

/**
 * Test every remaining operation against real git.
 *
 * Each test creates a fresh repo, performs the operation with Pitmaster,
 * and verifies the result using the git binary.
 */
final class AllOperationsTest extends TestCase
{
    private string $tmpDir;
    private Repository $repo;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-allops-' . bin2hex(random_bytes(4));
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

    // ---- merge ----

    #[Test]
    public function testMergeFastForward(): void
    {
        // Setup: main has 1 commit, feature has 1 more
        $this->writeFile('a.txt', "base\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Base\n");
        $this->assertFsckClean();

        $this->git('checkout -b feature');
        $this->writeFile('b.txt', "feature work\n");
        $this->git('add b.txt');
        $this->git('commit -m "Feature commit"');
        $this->git('checkout main');

        $this->repo = Pitmaster::open($this->tmpDir);
        $result = $this->repo->merge('feature');

        $this->assertTrue($result->clean);
        $this->assertFsckClean();

        // git should see both files
        $gitLs = $this->git('ls-tree --name-only HEAD');
        $this->assertStringContainsString('a.txt', $gitLs);
        $this->assertStringContainsString('b.txt', $gitLs);
        $this->assertFileExists($this->tmpDir . '/b.txt');
        $this->assertSame("feature work\n", file_get_contents($this->tmpDir . '/b.txt'));
        $this->assertSame('', trim($this->git('status --short')));
    }

    #[Test]
    public function testMergeClean(): void
    {
        // Setup: diverged branches modifying different files
        $this->writeFile('a.txt', "line 1\nline 2\nline 3\n");
        $this->writeFile('b.txt', "line 1\nline 2\nline 3\n");
        $this->repo->add('a.txt', 'b.txt');
        $this->repo->commit("Base\n");
        $this->assertFsckClean();

        // Create feature branch with changes to b.txt
        $this->git('checkout -b feature');
        $this->writeFile('b.txt', "line 1\nline 2 modified\nline 3\n");
        $this->git('add b.txt');
        $this->git('commit -m "Modify b on feature"');

        // Back to main, modify a.txt
        $this->git('checkout main');
        $this->writeFile('a.txt', "line 1\nline 2 modified on main\nline 3\n");
        $this->repo = Pitmaster::open($this->tmpDir);
        $this->repo->add('a.txt');
        $this->repo->commit("Modify a on main\n");
        $this->assertFsckClean();

        $result = $this->repo->merge('feature');
        $this->assertTrue($result->clean);
        $this->assertNotNull($result->commitId);
        $this->assertFsckClean();

        // Verify merge commit has 2 parents
        $head = $this->repo->head();
        $this->assertTrue($head->isMerge());
        $this->assertCount(2, $head->parents);
        $this->assertSame("line 1\nline 2 modified on main\nline 3\n", file_get_contents($this->tmpDir . '/a.txt'));
        $this->assertSame("line 1\nline 2 modified\nline 3\n", file_get_contents($this->tmpDir . '/b.txt'));
        $this->assertSame('', trim($this->git('status --short')));
    }

    // ---- checkout ----

    #[Test]
    public function testCheckoutBranch(): void
    {
        $this->writeFile('a.txt', "main content\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Main commit\n");

        $this->git('checkout -b feature');
        $this->writeFile('a.txt', "feature content\n");
        $this->git('add a.txt');
        $this->git('commit -m "Feature commit"');

        $this->git('checkout main');
        $this->repo = Pitmaster::open($this->tmpDir);

        // Checkout feature using Pitmaster
        $this->repo->checkout('feature');

        // Verify HEAD points to feature
        $gitHead = trim($this->git('rev-parse HEAD'));
        $pmHead = $this->repo->head()->id->hex;
        $this->assertSame($gitHead, $pmHead);

        // Verify file content was updated
        $content = file_get_contents($this->tmpDir . '/a.txt');
        $this->assertSame("feature content\n", $content);
    }

    #[Test]
    public function testCheckoutBranchRemovesFilesMissingOnTarget(): void
    {
        $this->writeFile('keep.txt', "keep\n");
        $this->writeFile('remove.txt', "remove\n");
        $this->repo->add('keep.txt', 'remove.txt');
        $this->repo->commit("Base\n");

        $this->git('checkout -b feature');
        $this->git('rm remove.txt');
        $this->git('commit -m "Remove file"');
        $this->git('checkout main');

        $this->assertFileExists($this->tmpDir . '/remove.txt');

        $this->repo = Pitmaster::open($this->tmpDir);
        $this->repo->checkout('feature');

        $this->assertFileDoesNotExist($this->tmpDir . '/remove.txt');
        $this->assertSame('', trim($this->git('status --short')));
    }

    // ---- stash ----

    #[Test]
    public function testStashPushAndPop(): void
    {
        $this->writeFile('a.txt', "original\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Initial\n");
        $this->assertFsckClean();

        // Modify file
        $this->writeFile('a.txt', "modified\n");

        // Stash
        $stash = new \Pitmaster\Stash\Stash(
            $this->repo->objectDatabase(),
            $this->repo->refDatabase(),
            $this->repo->gitDir(),
            $this->repo->workDir(),
        );

        $stashId = $stash->push('test stash');
        $this->assertNotEmpty($stashId->hex);
        $this->assertFsckClean();

        // After stash, file should be back to original
        $content = file_get_contents($this->tmpDir . '/a.txt');
        $this->assertSame("original\n", $content);

        // Stash list should have 1 entry
        $entries = $stash->listEntries();
        $this->assertCount(1, $entries);

        // Pop stash
        $stash->pop();
        $content = file_get_contents($this->tmpDir . '/a.txt');
        $this->assertSame("modified\n", $content);
    }

    // ---- cherry-pick ----

    #[Test]
    public function testCherryPick(): void
    {
        $this->writeFile('a.txt', "base\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Base\n");

        $this->git('checkout -b feature');
        $this->writeFile('b.txt', "cherry content\n");
        $this->git('add b.txt');
        $this->git('commit -m "Add b.txt on feature"');

        $featureHead = trim($this->git('rev-parse HEAD'));

        $this->git('checkout main');
        $this->repo = Pitmaster::open($this->tmpDir);

        $newId = $this->repo->cherryPick($featureHead);
        $this->assertFsckClean();

        // b.txt should exist in worktree
        $this->assertFileExists($this->tmpDir . '/b.txt');
        $this->assertSame("cherry content\n", file_get_contents($this->tmpDir . '/b.txt'));

        // git log should show the cherry-picked commit
        $gitLog = $this->git('log --oneline');
        $this->assertStringContainsString('Add b.txt on feature', $gitLog);
    }

    #[Test]
    public function testCherryPickDeletesFiles(): void
    {
        $this->writeFile('keep.txt', "keep\n");
        $this->writeFile('remove.txt', "remove\n");
        $this->repo->add('keep.txt', 'remove.txt');
        $this->repo->commit("Base\n");

        $this->git('checkout -b feature');
        $this->git('rm remove.txt');
        $this->git('commit -m "Remove file on feature"');
        $featureHead = trim($this->git('rev-parse HEAD'));

        $this->git('checkout main');
        $this->repo = Pitmaster::open($this->tmpDir);
        $this->repo->cherryPick($featureHead);

        $this->assertFileDoesNotExist($this->tmpDir . '/remove.txt');
        $this->assertSame('', trim($this->git('status --short')));
    }

    // ---- revert ----

    #[Test]
    public function testRevert(): void
    {
        $this->writeFile('a.txt', "original\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Add a.txt\n");

        $this->writeFile('a.txt', "changed\n");
        $this->repo->add('a.txt');
        $secondId = $this->repo->commit("Change a.txt\n");
        $this->assertFsckClean();

        $this->repo->revert($secondId->hex);
        $this->assertFsckClean();

        // File should be back to original
        $content = file_get_contents($this->tmpDir . '/a.txt');
        $this->assertSame("original\n", $content);

        // Log should show the revert commit
        $gitLog = $this->git('log --oneline');
        $this->assertStringContainsString('Revert', $gitLog);
    }

    #[Test]
    public function testRevertRemovesAddedFiles(): void
    {
        $this->writeFile('base.txt', "base\n");
        $this->repo->add('base.txt');
        $this->repo->commit("Base\n");

        $this->writeFile('added.txt', "added\n");
        $this->repo->add('added.txt');
        $addedCommit = $this->repo->commit("Add file\n");

        $this->repo->revert($addedCommit->hex);

        $this->assertFileDoesNotExist($this->tmpDir . '/added.txt');
        $this->assertSame('', trim($this->git('status --short')));
    }

    // ---- reset --mixed and --hard ----

    #[Test]
    public function testResetMixed(): void
    {
        $this->writeFile('a.txt', "v1\n");
        $this->repo->add('a.txt');
        $firstId = $this->repo->commit("First\n");

        $this->writeFile('a.txt', "v2\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Second\n");

        $this->repo->reset($firstId->hex, 'mixed');

        // HEAD should be at first commit
        $this->assertSame($firstId->hex, $this->repo->head()->id->hex);

        // Worktree should still have v2 (mixed doesn't touch worktree)
        $content = file_get_contents($this->tmpDir . '/a.txt');
        $this->assertSame("v2\n", $content);
    }

    #[Test]
    public function testResetHard(): void
    {
        $this->writeFile('a.txt', "v1\n");
        $this->repo->add('a.txt');
        $firstId = $this->repo->commit("First\n");

        $this->writeFile('a.txt', "v2\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Second\n");

        $this->repo->reset($firstId->hex, 'hard');

        // HEAD at first commit
        $this->assertSame($firstId->hex, $this->repo->head()->id->hex);

        // Worktree should be reset to v1
        $content = file_get_contents($this->tmpDir . '/a.txt');
        $this->assertSame("v1\n", $content);
    }

    // ---- restore ----

    #[Test]
    public function testRestore(): void
    {
        $this->writeFile('a.txt', "original\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Initial\n");

        $this->writeFile('a.txt', "modified\n");

        // Restore from index
        $this->repo->restore('a.txt');

        $content = file_get_contents($this->tmpDir . '/a.txt');
        $this->assertSame("original\n", $content);
    }

    #[Test]
    public function testRestoreFromCommit(): void
    {
        $this->writeFile('a.txt', "v1\n");
        $this->repo->add('a.txt');
        $firstId = $this->repo->commit("First\n");

        $this->writeFile('a.txt', "v2\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Second\n");

        $this->repo->restore('a.txt', $firstId->hex);

        $content = file_get_contents($this->tmpDir . '/a.txt');
        $this->assertSame("v1\n", $content);
    }

    #[Test]
    public function testCommitCanCreateEmptyTree(): void
    {
        $this->writeFile('only.txt', "content\n");
        $this->repo->add('only.txt');
        $this->repo->commit("Add only file\n");

        unlink($this->tmpDir . '/only.txt');
        $this->repo->remove('only.txt');
        $this->repo->commit("Remove only file\n");

        $this->assertSame('', trim($this->git('ls-tree --name-only HEAD')));
        $this->assertSame('', trim($this->git('status --short')));
    }

    // ---- mv ----

    #[Test]
    public function testMv(): void
    {
        $this->writeFile('old.txt', "content\n");
        $this->repo->add('old.txt');
        $this->repo->commit("Initial\n");

        $this->repo->mv('old.txt', 'new.txt');

        $this->assertFileDoesNotExist($this->tmpDir . '/old.txt');
        $this->assertFileExists($this->tmpDir . '/new.txt');
        $this->assertSame("content\n", file_get_contents($this->tmpDir . '/new.txt'));

        // Index should have new.txt, not old.txt
        $index = $this->repo->index();
        $this->assertNull($index->entry('old.txt'));
        $this->assertNotNull($index->entry('new.txt'));
    }

    // ---- diffStaged ----

    #[Test]
    public function testDiffStaged(): void
    {
        $this->writeFile('a.txt', "line 1\nline 2\nline 3\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Initial\n");

        $this->writeFile('a.txt', "line 1\nline 2 changed\nline 3\n");
        $this->repo->add('a.txt');

        $staged = $this->repo->diffStaged();
        $this->assertNotEmpty($staged);
        $this->assertTrue($staged[0]->hasChanges());
    }

    // ---- show ----

    #[Test]
    public function testShow(): void
    {
        $this->writeFile('a.txt', "content\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Initial\n");

        $this->writeFile('a.txt', "new content\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Update\n");

        $result = $this->repo->show('HEAD');

        $this->assertSame('Update', trim($result['commit']->message));
        $this->assertNotEmpty($result['diff']);
    }

    // ---- createTag (annotated) ----

    #[Test]
    public function testCreateAnnotatedTag(): void
    {
        $this->writeFile('a.txt', "content\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Initial\n");

        $tagId = $this->repo->createTag('v1.0', "Release 1.0\n");
        $this->assertFsckClean();

        // git should see the tag
        $gitTags = $this->git('tag -l');
        $this->assertStringContainsString('v1.0', $gitTags);

        // git show should show the tag object
        $gitShow = $this->git('cat-file -t ' . $tagId->hex);
        $this->assertStringContainsString('tag', $gitShow);
    }

    // ---- blame ----

    #[Test]
    public function testBlame(): void
    {
        $this->writeFile('a.txt', "line 1\nline 2\n");
        $this->repo->add('a.txt');
        $firstId = $this->repo->commit("First\n");

        $this->writeFile('a.txt', "line 1\nline 2\nline 3\n");
        $this->repo->add('a.txt');
        $secondId = $this->repo->commit("Second\n");

        $blame = new \Pitmaster\Graph\Blame($this->repo->objectDatabase());
        $result = $blame->blame($secondId, 'a.txt');

        $this->assertCount(3, $result);
        // Line 3 should be blamed to the second commit
        $this->assertSame($secondId->hex, $result[2]['hash']);
        $this->assertSame('line 3', $result[2]['content']);
    }

    // ---- grep ----

    #[Test]
    public function testGrep(): void
    {
        $this->writeFile('a.txt', "hello world\nfoo bar\n");
        $this->writeFile('b.txt', "no match here\nhello again\n");
        $this->repo->add('a.txt', 'b.txt');
        $this->repo->commit("Initial\n");

        $head = $this->repo->head();
        $grep = new \Pitmaster\Graph\Grep($this->repo->objectDatabase());
        $results = $grep->grep($head->tree, 'hello');

        $this->assertCount(2, $results);

        $paths = array_map(fn ($r) => $r['path'], $results);
        $this->assertContains('a.txt', $paths);
        $this->assertContains('b.txt', $paths);
    }

    // ---- logPath (path-filtered log) ----

    #[Test]
    public function testLogPath(): void
    {
        $this->writeFile('a.txt', "a\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Add a\n");

        $this->writeFile('b.txt', "b\n");
        $this->repo->add('b.txt');
        $this->repo->commit("Add b\n");

        $this->writeFile('a.txt', "a modified\n");
        $this->repo->add('a.txt');
        $this->repo->commit("Modify a\n");

        $aLog = $this->repo->logPath('a.txt');
        $this->assertCount(2, $aLog); // "Add a" and "Modify a"

        $bLog = $this->repo->logPath('b.txt');
        $this->assertCount(1, $bLog); // "Add b"
    }

    // ---- helpers ----

    private function writeFile(string $path, string $content): void
    {
        $full = $this->tmpDir . '/' . $path;
        $dir = dirname($full);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($full, $content);
    }

    private function git(string $cmd): string
    {
        return shell_exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $cmd)) ?? '';
    }

    private function assertFsckClean(): void
    {
        exec(
            sprintf('cd %s && git fsck --strict --no-progress 2>&1', escapeshellarg($this->tmpDir)),
            $out,
            $code,
        );
        $this->assertSame(0, $code, 'git fsck: ' . implode("\n", $out));
    }
}
