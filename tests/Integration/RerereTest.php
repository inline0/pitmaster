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

    #[Test]
    public function resolveReadsGitGeneratedRerereCache(): void
    {
        $gitRepo = $this->tmpDir . '/git-rerere';
        mkdir($gitRepo, 0777, true);
        $this->gitIn($gitRepo, 'init --initial-branch=main');
        $this->gitIn($gitRepo, 'config user.email test@pitmaster.dev');
        $this->gitIn($gitRepo, 'config user.name "Test User"');
        $this->gitIn($gitRepo, 'config rerere.enabled true');
        $this->writeFile($gitRepo, 'a.txt', "line1\nline2\n");
        $this->gitIn($gitRepo, 'add a.txt');
        $this->gitIn($gitRepo, 'commit -m base');
        $this->gitIn($gitRepo, 'checkout -b feature');
        $this->writeFile($gitRepo, 'a.txt', "line1\nfeature\n");
        $this->gitIn($gitRepo, 'add a.txt');
        $this->gitIn($gitRepo, 'commit -m feature');
        $this->gitIn($gitRepo, 'checkout main');
        $this->writeFile($gitRepo, 'a.txt', "line1\nmain\n");
        $this->gitIn($gitRepo, 'add a.txt');
        $this->gitIn($gitRepo, 'commit -m main');
        $this->gitWithExit($gitRepo, 'merge feature');
        $conflicted = (string) file_get_contents($gitRepo . '/a.txt');
        $this->writeFile($gitRepo, 'a.txt', "line1\nresolved\n");
        $this->gitIn($gitRepo, 'add a.txt');
        $this->gitIn($gitRepo, 'rerere');

        $rerere = new Rerere($gitRepo . '/.git');

        $this->assertSame("line1\nresolved\n", $rerere->resolve($conflicted));
        $this->assertCount(1, $rerere->listRecorded());
    }

    #[Test]
    public function recordWritesSamePreimageAndPostimageAsGitRerere(): void
    {
        $gitRepo = $this->tmpDir . '/git-rerere-record';
        $pitRepo = $this->tmpDir . '/pit-rerere-record';
        mkdir($gitRepo, 0777, true);
        mkdir($pitRepo, 0777, true);
        $this->gitIn($gitRepo, 'init --initial-branch=main');
        $this->gitIn($gitRepo, 'config user.email test@pitmaster.dev');
        $this->gitIn($gitRepo, 'config user.name "Test User"');
        $this->gitIn($gitRepo, 'config rerere.enabled true');
        $this->writeFile($gitRepo, 'a.txt', "line1\nline2\n");
        $this->gitIn($gitRepo, 'add a.txt');
        $this->gitIn($gitRepo, 'commit -m base');
        $this->gitIn($gitRepo, 'checkout -b feature');
        $this->writeFile($gitRepo, 'a.txt', "line1\nfeature\n");
        $this->gitIn($gitRepo, 'add a.txt');
        $this->gitIn($gitRepo, 'commit -m feature');
        $this->gitIn($gitRepo, 'checkout main');
        $this->writeFile($gitRepo, 'a.txt', "line1\nmain\n");
        $this->gitIn($gitRepo, 'add a.txt');
        $this->gitIn($gitRepo, 'commit -m main');
        $this->gitWithExit($gitRepo, 'merge feature');
        $conflicted = (string) file_get_contents($gitRepo . '/a.txt');
        $resolved = "line1\nresolved\n";
        $this->writeFile($gitRepo, 'a.txt', $resolved);
        $this->gitIn($gitRepo, 'add a.txt');
        $this->gitIn($gitRepo, 'rerere');

        $this->gitIn($pitRepo, 'init --initial-branch=main');
        $rerere = new Rerere($pitRepo . '/.git');
        $rerere->record($conflicted, $resolved);

        $gitHash = basename((string) current(glob($gitRepo . '/.git/rr-cache/*', GLOB_ONLYDIR)));
        $pitHash = basename((string) current(glob($pitRepo . '/.git/rr-cache/*', GLOB_ONLYDIR)));
        $this->assertNotSame('', $pitHash);
        $this->assertSame($gitHash, $pitHash);
        $this->assertFileExists($pitRepo . '/.git/rr-cache/' . $pitHash . '/preimage');
        $this->assertFileExists($pitRepo . '/.git/rr-cache/' . $pitHash . '/postimage');
        $this->assertSame(
            file_get_contents($gitRepo . '/.git/rr-cache/' . $gitHash . '/preimage'),
            file_get_contents($pitRepo . '/.git/rr-cache/' . $pitHash . '/preimage'),
        );
        $this->assertSame(
            file_get_contents($gitRepo . '/.git/rr-cache/' . $gitHash . '/postimage'),
            file_get_contents($pitRepo . '/.git/rr-cache/' . $pitHash . '/postimage'),
        );
    }

    private function git(string $cmd): string
    {
        return shell_exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $cmd)) ?? '';
    }

    private function gitIn(string $dir, string $cmd): string
    {
        return shell_exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $cmd)) ?? '';
    }

    private function gitWithExit(string $dir, string $cmd): int
    {
        exec(sprintf('cd %s && git %s >/dev/null 2>&1', escapeshellarg($dir), $cmd), $output, $exitCode);

        return $exitCode;
    }

    private function writeFile(string $dir, string $path, string $content): void
    {
        file_put_contents($dir . '/' . $path, $content);
    }
}
