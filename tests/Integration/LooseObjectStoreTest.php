<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\Blob;
use Pitmaster\Storage\LooseObjectStore;

final class LooseObjectStoreTest extends TestCase
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
    public function writeAndReadBackBlob(): void
    {
        $store = new LooseObjectStore($this->tmpDir . '/.git/objects');
        $blob = Blob::fromContent("Hello, Pitmaster!\n");

        $id = $store->write($blob);
        $readBack = $store->read($id);

        $this->assertInstanceOf(Blob::class, $readBack);
        $this->assertSame("Hello, Pitmaster!\n", $readBack->content);
        $this->assertSame($id->hex, $readBack->id->hex);
    }

    #[Test]
    public function existsReturnsTrueForWrittenObject(): void
    {
        $store = new LooseObjectStore($this->tmpDir . '/.git/objects');
        $blob = Blob::fromContent("exists test\n");

        $id = $store->write($blob);

        $this->assertTrue($store->exists($id));
    }

    #[Test]
    public function existsReturnsFalseForMissingObject(): void
    {
        $store = new LooseObjectStore($this->tmpDir . '/.git/objects');
        $blob = Blob::fromContent("not written\n");

        $this->assertFalse($store->exists($blob->id));
    }

    #[Test]
    public function listAllIncludesWrittenHash(): void
    {
        $store = new LooseObjectStore($this->tmpDir . '/.git/objects');
        $blob = Blob::fromContent("list test\n");

        $store->write($blob);
        $all = $store->listAll();

        $this->assertContains($blob->id->hex, $all);
    }

    #[Test]
    public function writtenBlobReadableByGitCatFile(): void
    {
        $store = new LooseObjectStore($this->tmpDir . '/.git/objects');
        $content = "Git can read this\n";
        $blob = Blob::fromContent($content);

        $store->write($blob);

        $gitContent = $this->git(sprintf('cat-file -p %s', $blob->id->hex));
        $this->assertSame($content, $gitContent);
    }

    #[Test]
    public function readObjectWrittenByGit(): void
    {
        $this->writeFile('fromgit.txt', "Written by git\n");
        $hash = trim($this->git('hash-object -w fromgit.txt'));

        $store = new LooseObjectStore($this->tmpDir . '/.git/objects');
        $object = $store->read(\Pitmaster\Object\ObjectId::fromHex($hash));

        $this->assertInstanceOf(Blob::class, $object);
        $this->assertSame("Written by git\n", $object->content);
    }

    #[Test]
    public function emptyBlobRoundTrip(): void
    {
        $store = new LooseObjectStore($this->tmpDir . '/.git/objects');
        $blob = Blob::fromContent('');

        $store->write($blob);
        $readBack = $store->read($blob->id);

        $this->assertInstanceOf(Blob::class, $readBack);
        $this->assertSame('', $readBack->content);

        $gitContent = $this->git(sprintf('cat-file -p %s', $blob->id->hex));
        $this->assertSame('', $gitContent);
    }
}
