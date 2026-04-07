<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Diff\TreeDiff;
use Pitmaster\Object\ObjectId;
use Pitmaster\Storage\ObjectDatabase;

final class TreeDiffTest extends TestCase
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
    public function diffBetweenTwoCommitsMatchesGitDiffTree(): void
    {
        // First commit
        $this->writeFile('a.txt', "line1\nline2\n");
        $this->writeFile('b.txt', "hello\n");
        $this->git('add .');
        $this->git('commit -m "first"');
        $tree1 = trim($this->git('rev-parse HEAD^{tree}'));

        // Second commit: modify a.txt, delete b.txt, add c.txt
        $this->writeFile('a.txt', "line1\nline2\nline3\n");
        $this->git('rm b.txt');
        $this->writeFile('c.txt', "new file\n");
        $this->git('add .');
        $this->git('commit -m "second"');
        $tree2 = trim($this->git('rev-parse HEAD^{tree}'));

        // Git diff-tree with no rename detection to get raw changed file names
        $gitOutput = trim($this->git("diff-tree --no-renames --name-only -r {$tree1} {$tree2}"));
        $gitFiles = array_filter(explode("\n", $gitOutput));
        sort($gitFiles);

        // Pitmaster TreeDiff (collects both oldPath and newPath to cover renames)
        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $treeDiff = new TreeDiff($objects);
        $results = $treeDiff->diff(ObjectId::fromHex($tree1), ObjectId::fromHex($tree2));

        $pmFiles = [];
        foreach ($results as $result) {
            $pmFiles[] = $result->oldPath;
            if ($result->newPath !== $result->oldPath) {
                $pmFiles[] = $result->newPath;
            }
        }
        $pmFiles = array_unique($pmFiles);
        sort($pmFiles);

        $this->assertSame($gitFiles, $pmFiles);
    }

    #[Test]
    public function diffDetectsAddedModifiedDeleted(): void
    {
        // Use files with different enough content to avoid rename detection pairing
        $this->writeFile('keep.txt', "stay\n");
        $this->writeFile('modify.txt', "original content that is quite long and unique\n");
        $this->writeFile('remove.txt', "this file will be completely removed and has very different content\n");
        $this->git('add .');
        $this->git('commit -m "first"');
        $tree1 = trim($this->git('rev-parse HEAD^{tree}'));

        $this->writeFile('modify.txt', "changed\n");
        $this->git('rm remove.txt');
        $this->writeFile('added.txt', "brand new file with totally different text\n");
        $this->git('add .');
        $this->git('commit -m "second"');
        $tree2 = trim($this->git('rev-parse HEAD^{tree}'));

        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $treeDiff = new TreeDiff($objects);
        $results = $treeDiff->diff(ObjectId::fromHex($tree1), ObjectId::fromHex($tree2));

        // Collect all paths involved (both old and new, to handle potential renames)
        $allPaths = [];
        foreach ($results as $r) {
            $allPaths[] = $r->oldPath;
            $allPaths[] = $r->newPath;
        }
        $allPaths = array_unique($allPaths);

        $this->assertContains('added.txt', $allPaths);
        $this->assertContains('modify.txt', $allPaths);
        $this->assertContains('remove.txt', $allPaths);
        $this->assertNotContains('keep.txt', $allPaths);
    }

    #[Test]
    public function diffFromNullTreeShowsAllAdded(): void
    {
        $this->writeFile('x.txt', "x\n");
        $this->writeFile('y.txt', "y\n");
        $this->git('add .');
        $this->git('commit -m "first"');
        $tree = trim($this->git('rev-parse HEAD^{tree}'));

        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $treeDiff = new TreeDiff($objects);
        $results = $treeDiff->diff(null, ObjectId::fromHex($tree));

        $paths = array_map(fn ($r) => $r->newPath, $results);
        sort($paths);

        $this->assertSame(['x.txt', 'y.txt'], $paths);
    }
}
