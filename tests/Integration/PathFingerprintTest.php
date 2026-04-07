<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Support\PathFingerprint;

final class PathFingerprintTest extends TestCase
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

    private function writeFile(string $p, string $c): void
    {
        $d = $this->tmpDir . '/' . $p;
        if (!is_dir(dirname($d))) {
            mkdir(dirname($d), 0777, true);
        }
        file_put_contents($d, $c);
    }

    #[Test]
    public function forPathsReturnsHashString(): void
    {
        $this->writeFile('a.txt', "alpha\n");
        $this->writeFile('b.txt', "beta\n");

        $hash = PathFingerprint::forPaths([
            $this->tmpDir . '/a.txt',
            $this->tmpDir . '/b.txt',
        ]);

        $this->assertSame(40, strlen($hash));
        $this->assertTrue(ctype_xdigit($hash));
    }

    #[Test]
    public function samePathsReturnSameHash(): void
    {
        $this->writeFile('file.txt', "content\n");

        $paths = [$this->tmpDir . '/file.txt'];

        $hash1 = PathFingerprint::forPaths($paths);
        $hash2 = PathFingerprint::forPaths($paths);

        $this->assertSame($hash1, $hash2);
    }

    #[Test]
    public function differentPathsReturnDifferentHash(): void
    {
        $this->writeFile('x.txt', "x content\n");
        $this->writeFile('y.txt', "y content\n");

        $hash1 = PathFingerprint::forPaths([$this->tmpDir . '/x.txt']);
        $hash2 = PathFingerprint::forPaths([$this->tmpDir . '/y.txt']);

        $this->assertNotSame($hash1, $hash2);
    }

    #[Test]
    public function missingPathsStillReturnHash(): void
    {
        $hash = PathFingerprint::forPaths([
            $this->tmpDir . '/nonexistent.txt',
        ]);

        $this->assertSame(40, strlen($hash));
        $this->assertTrue(ctype_xdigit($hash));
    }

    #[Test]
    public function orderDoesNotMatterDueToSorting(): void
    {
        $this->writeFile('a.txt', "a\n");
        $this->writeFile('b.txt', "b\n");

        $hash1 = PathFingerprint::forPaths([
            $this->tmpDir . '/a.txt',
            $this->tmpDir . '/b.txt',
        ]);
        $hash2 = PathFingerprint::forPaths([
            $this->tmpDir . '/b.txt',
            $this->tmpDir . '/a.txt',
        ]);

        $this->assertSame($hash1, $hash2);
    }
}
