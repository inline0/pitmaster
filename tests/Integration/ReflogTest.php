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
