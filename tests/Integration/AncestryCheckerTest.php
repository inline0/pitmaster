<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Graph\AncestryChecker;
use Pitmaster\Object\ObjectId;
use Pitmaster\Storage\ObjectDatabase;

final class AncestryCheckerTest extends TestCase
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
    public function isAncestorReturnsTrueForLinearHistory(): void
    {
        // Create 3 linear commits: A -> B -> C
        $this->writeFile('a.txt', "a\n");
        $this->git('add .');
        $this->git('commit -m "A"');
        $a = trim($this->git('rev-parse HEAD'));

        $this->writeFile('b.txt', "b\n");
        $this->git('add .');
        $this->git('commit -m "B"');
        $b = trim($this->git('rev-parse HEAD'));

        $this->writeFile('c.txt', "c\n");
        $this->git('add .');
        $this->git('commit -m "C"');
        $c = trim($this->git('rev-parse HEAD'));

        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $checker = new AncestryChecker($objects);

        // A is ancestor of C
        $this->assertTrue($checker->isAncestor(ObjectId::fromHex($a), ObjectId::fromHex($c)));

        // A is ancestor of B
        $this->assertTrue($checker->isAncestor(ObjectId::fromHex($a), ObjectId::fromHex($b)));

        // B is ancestor of C
        $this->assertTrue($checker->isAncestor(ObjectId::fromHex($b), ObjectId::fromHex($c)));
    }

    #[Test]
    public function isAncestorReturnsFalseForReverseDirection(): void
    {
        $this->writeFile('a.txt', "a\n");
        $this->git('add .');
        $this->git('commit -m "A"');
        $a = trim($this->git('rev-parse HEAD'));

        $this->writeFile('b.txt', "b\n");
        $this->git('add .');
        $this->git('commit -m "B"');

        $this->writeFile('c.txt', "c\n");
        $this->git('add .');
        $this->git('commit -m "C"');
        $c = trim($this->git('rev-parse HEAD'));

        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $checker = new AncestryChecker($objects);

        // C is NOT ancestor of A
        $this->assertFalse($checker->isAncestor(ObjectId::fromHex($c), ObjectId::fromHex($a)));
    }

    #[Test]
    public function isAncestorReturnsTrueForSameCommit(): void
    {
        $this->writeFile('a.txt', "a\n");
        $this->git('add .');
        $this->git('commit -m "A"');
        $a = trim($this->git('rev-parse HEAD'));

        $objects = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $checker = new AncestryChecker($objects);

        // A is ancestor of itself
        $this->assertTrue($checker->isAncestor(ObjectId::fromHex($a), ObjectId::fromHex($a)));
    }
}
