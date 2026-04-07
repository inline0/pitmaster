<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\ObjectId;
use Pitmaster\Ref\LooseRefStore;
use Pitmaster\Ref\PackedRefStore;
use Pitmaster\Ref\RefDatabase;

final class RefStoresTest extends TestCase
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

    private function createCommit(string $message): string
    {
        static $counter = 0;
        $counter++;
        $this->writeFile("file{$counter}.txt", "content {$counter}\n");
        $this->git('add .');
        $this->git("commit -m \"{$message}\"");
        return trim($this->git('rev-parse HEAD'));
    }

    #[Test]
    public function looseRefStoreResolveAndList(): void
    {
        $commitHash = $this->createCommit('first');
        $gitDir = $this->tmpDir . '/.git';
        $store = new LooseRefStore($gitDir);

        // Resolve HEAD (symbolic -> refs/heads/main -> hash)
        $resolved = $store->resolve('HEAD');
        $this->assertNotNull($resolved);
        $this->assertSame($commitHash, $resolved->hex);

        // Resolve branch ref
        $branch = $store->resolve('refs/heads/main');
        $this->assertNotNull($branch);
        $this->assertSame($commitHash, $branch->hex);

        // List should include refs/heads/main
        $refs = $store->list();
        $this->assertArrayHasKey('refs/heads/main', $refs);
    }

    #[Test]
    public function looseRefStoreUpdateAndDelete(): void
    {
        $commitHash = $this->createCommit('first');
        $gitDir = $this->tmpDir . '/.git';
        $store = new LooseRefStore($gitDir);

        // Create a new branch via update
        $id = ObjectId::fromHex($commitHash);
        $store->update('refs/heads/new-branch', $id);

        $resolved = $store->resolve('refs/heads/new-branch');
        $this->assertNotNull($resolved);
        $this->assertSame($commitHash, $resolved->hex);

        // Verify git sees it
        $gitBranches = $this->git('branch');
        $this->assertStringContainsString('new-branch', $gitBranches);

        // Delete it
        $store->delete('refs/heads/new-branch');
        $this->assertNull($store->resolve('refs/heads/new-branch'));
    }

    #[Test]
    public function packedRefStoreResolveAndList(): void
    {
        $commitHash = $this->createCommit('first');

        // Create a tag and another branch
        $this->git('tag v1.0');
        $this->git('branch feature');

        // Pack refs
        $this->git('pack-refs --all');

        $gitDir = $this->tmpDir . '/.git';
        $store = new PackedRefStore($gitDir);

        $refs = $store->list();
        $this->assertNotEmpty($refs);

        // Should have the branch and tag in packed-refs
        $mainRef = $store->resolve('refs/heads/main');
        $this->assertNotNull($mainRef);
        $this->assertSame($commitHash, $mainRef->hex);

        $tagRef = $store->resolve('refs/tags/v1.0');
        $this->assertNotNull($tagRef);
    }

    #[Test]
    public function refDatabaseLooseTakesPriorityOverPacked(): void
    {
        $hash1 = $this->createCommit('first');

        // Pack all refs
        $this->git('pack-refs --all');

        // Create a new commit and update main (loose overrides packed)
        $hash2 = $this->createCommit('second');

        $gitDir = $this->tmpDir . '/.git';
        $db = new RefDatabase($gitDir);

        // RefDatabase should resolve to the loose (newer) value
        $resolved = $db->resolve('refs/heads/main');
        $this->assertNotNull($resolved);
        $this->assertSame($hash2, $resolved->hex);
    }

    #[Test]
    public function refDatabaseListMergesLooseAndPacked(): void
    {
        $this->createCommit('first');
        $this->git('branch feature');
        $this->git('pack-refs --all');

        // Create a new branch (only in loose)
        $this->git('branch new-only-loose');

        $gitDir = $this->tmpDir . '/.git';
        $db = new RefDatabase($gitDir);
        $refs = $db->list();

        // Should contain both packed and loose refs
        $this->assertArrayHasKey('refs/heads/main', $refs);
        $this->assertArrayHasKey('refs/heads/feature', $refs);
        $this->assertArrayHasKey('refs/heads/new-only-loose', $refs);
    }

    #[Test]
    public function refsMatchGitForEachRef(): void
    {
        $this->createCommit('first');
        $this->git('branch feature');
        $this->git('tag v1.0');

        $gitDir = $this->tmpDir . '/.git';
        $db = new RefDatabase($gitDir);
        $pmRefs = $db->list();

        // Compare with git for-each-ref
        $gitOutput = trim($this->git('for-each-ref --format="%(objectname) %(refname)"'));
        $gitRefs = [];
        foreach (explode("\n", $gitOutput) as $line) {
            $parts = explode(' ', trim($line), 2);
            if (count($parts) === 2) {
                $gitRefs[$parts[1]] = $parts[0];
            }
        }

        foreach ($gitRefs as $name => $hash) {
            $this->assertArrayHasKey($name, $pmRefs, "Missing ref: {$name}");
            $this->assertSame($hash, $pmRefs[$name]->hex, "Hash mismatch for {$name}");
        }
    }

    #[Test]
    public function looseRefStoreImplementsRefStoreInterface(): void
    {
        $gitDir = $this->tmpDir . '/.git';
        $store = new LooseRefStore($gitDir);

        $this->assertInstanceOf(\Pitmaster\Ref\RefStore::class, $store);
    }

    #[Test]
    public function readHeadReturnsSymbolicRef(): void
    {
        $this->createCommit('first');
        $gitDir = $this->tmpDir . '/.git';
        $store = new LooseRefStore($gitDir);

        $head = $store->readHead();
        $this->assertNotNull($head);
        $this->assertSame('refs/heads/main', $head->target);
    }
}
