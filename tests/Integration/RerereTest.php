<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Merge\Rerere;

/**
 * Test Merge\Rerere conflict resolution recording and replay.
 */
final class RerereTest extends TestCase
{
    private string $tmpDir;
    private string $gitDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');
        $this->gitDir = $this->tmpDir . '/.git';
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function recordConflictResolution(): void
    {
        $rerere = new Rerere($this->gitDir);

        $conflicted = "<<<<<<< ours\nour line\n=======\ntheir line\n>>>>>>> theirs\n";
        $resolved = "merged line\n";

        $rerere->record($conflicted, $resolved);

        // rr-cache directory should exist with preimage and postimage
        $cacheEntries = glob($this->gitDir . '/rr-cache/*/postimage');
        $this->assertCount(1, $cacheEntries);
    }

    #[Test]
    public function resolveReturnsRecordedResolution(): void
    {
        $rerere = new Rerere($this->gitDir);

        $conflicted = "<<<<<<< HEAD\nline A\n=======\nline B\n>>>>>>> feature\n";
        $resolved = "line AB\n";

        $rerere->record($conflicted, $resolved);

        $result = $rerere->resolve($conflicted);
        $this->assertSame($resolved, $result);
    }

    #[Test]
    public function resolveMatchesRegardlessOfBranchLabels(): void
    {
        $rerere = new Rerere($this->gitDir);

        // Record with one set of branch labels
        $conflicted1 = "<<<<<<< main\nours\n=======\ntheirs\n>>>>>>> feature\n";
        $resolved = "merged\n";
        $rerere->record($conflicted1, $resolved);

        // Resolve with different branch labels but same conflict content
        $conflicted2 = "<<<<<<< develop\nours\n=======\ntheirs\n>>>>>>> hotfix\n";
        $result = $rerere->resolve($conflicted2);

        $this->assertSame($resolved, $result);
    }

    #[Test]
    public function forgetRemovesResolution(): void
    {
        $rerere = new Rerere($this->gitDir);

        $conflicted = "<<<<<<< HEAD\nfoo\n=======\nbar\n>>>>>>> branch\n";
        $resolved = "foobar\n";

        $rerere->record($conflicted, $resolved);
        $this->assertNotNull($rerere->resolve($conflicted));

        $rerere->forget($conflicted);
        $this->assertNull($rerere->resolve($conflicted));
    }

    #[Test]
    public function listRecordedShowsRecordedHashes(): void
    {
        $rerere = new Rerere($this->gitDir);

        $this->assertSame([], $rerere->listRecorded());

        $rerere->record(
            "<<<<<<< a\nline1\n=======\nline2\n>>>>>>> b\n",
            "resolved1\n",
        );

        $rerere->record(
            "<<<<<<< a\ndifferent\n=======\ncontent\n>>>>>>> b\n",
            "resolved2\n",
        );

        $recorded = $rerere->listRecorded();
        $this->assertCount(2, $recorded);

        // Each entry should be a SHA-1 hash
        foreach ($recorded as $hash) {
            $this->assertSame(40, strlen($hash));
            $this->assertTrue(ctype_xdigit($hash));
        }
    }

    #[Test]
    public function resolveReturnsNullForUnknownConflict(): void
    {
        $rerere = new Rerere($this->gitDir);

        $result = $rerere->resolve("<<<<<<< x\nnever seen\n=======\nbefore\n>>>>>>> y\n");
        $this->assertNull($result);
    }

    private function git(string $cmd): string
    {
        return shell_exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $cmd)) ?? '';
    }
}
