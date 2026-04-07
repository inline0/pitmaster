<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Merge\MergeBase;
use Pitmaster\Object\ObjectId;
use Pitmaster\Storage\ObjectDatabase;

final class MergeBaseTest extends TestCase
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
    public function findReturnsCommonAncestorOfDivergedBranches(): void
    {
        // Create common ancestor
        $this->writeFile('base.txt', "base\n");
        $this->git('add .');
        $this->git('commit -m "base"');
        $base = trim($this->git('rev-parse HEAD'));

        // Create branch and diverge
        $this->git('checkout -b feature');
        $this->writeFile('feature.txt', "feature\n");
        $this->git('add .');
        $this->git('commit -m "feature"');
        $featureHead = trim($this->git('rev-parse HEAD'));

        // Go back to main and diverge
        $this->git('checkout main');
        $this->writeFile('main.txt', "main\n");
        $this->git('add .');
        $this->git('commit -m "main"');
        $mainHead = trim($this->git('rev-parse HEAD'));

        // Git merge-base
        $gitMergeBase = trim($this->git("merge-base {$mainHead} {$featureHead}"));

        // Pitmaster MergeBase
        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $mergeBase = new MergeBase($objects);
        $result = $mergeBase->find(ObjectId::fromHex($mainHead), ObjectId::fromHex($featureHead));

        $this->assertNotNull($result);
        $this->assertSame($gitMergeBase, $result->hex);
        $this->assertSame($base, $result->hex);
    }

    #[Test]
    public function findReturnsNullForUnrelatedCommits(): void
    {
        // Create first branch with its own root
        $this->writeFile('a.txt', "a\n");
        $this->git('add .');
        $this->git('commit -m "a"');
        $a = trim($this->git('rev-parse HEAD'));

        // Create orphan branch (unrelated history)
        $this->git('checkout --orphan orphan');
        $this->git('rm -rf .');
        $this->writeFile('b.txt', "b\n");
        $this->git('add .');
        $this->git('commit -m "b"');
        $b = trim($this->git('rev-parse HEAD'));

        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $mergeBase = new MergeBase($objects);
        $result = $mergeBase->find(ObjectId::fromHex($a), ObjectId::fromHex($b));

        $this->assertNull($result);
    }

    #[Test]
    public function findReturnsSameCommitWhenBothAreSame(): void
    {
        $this->writeFile('a.txt', "a\n");
        $this->git('add .');
        $this->git('commit -m "a"');
        $a = trim($this->git('rev-parse HEAD'));

        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $mergeBase = new MergeBase($objects);
        $result = $mergeBase->find(ObjectId::fromHex($a), ObjectId::fromHex($a));

        $this->assertNotNull($result);
        $this->assertSame($a, $result->hex);
    }
}
