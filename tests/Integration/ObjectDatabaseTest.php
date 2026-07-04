<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\Blob;
use Pitmaster\Object\ObjectId;
use Pitmaster\Storage\ObjectDatabase;

final class ObjectDatabaseTest extends TestCase
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
    public function readFindsLooseObjects(): void
    {
        $db = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $blob = Blob::fromContent("loose object\n");
        $db->write($blob);

        $readBack = $db->read($blob->id);
        $this->assertNotNull($readBack);
        $this->assertSame("loose object\n", $readBack->content);
    }

    #[Test]
    public function readFindsPackedObjects(): void
    {
        // Create objects and pack them
        for ($i = 1; $i <= 3; $i++) {
            $this->writeFile("f{$i}.txt", "content {$i}\n");
            $this->git('add .');
            $this->git("commit -m \"commit {$i}\"");
        }
        $this->git('gc');

        $db = new ObjectDatabase($this->tmpDir . '/.git/objects');

        // Get a known hash from git
        $hash = trim($this->git('rev-parse HEAD'));
        $object = $db->read(ObjectId::fromHex($hash));

        $this->assertNotNull($object);
        $this->assertSame($hash, $object->id->hex);
    }

    #[Test]
    public function existsChecksLooseAndPacked(): void
    {
        $db = new ObjectDatabase($this->tmpDir . '/.git/objects');

        // Write a loose object
        $blob = Blob::fromContent("exists check\n");
        $db->write($blob);
        $this->assertTrue($db->exists($blob->id));

        // Non-existent
        $missing = ObjectId::fromHex(str_repeat('00', 20));
        $this->assertFalse($db->exists($missing));
    }

    #[Test]
    public function writeGoesToLoose(): void
    {
        $db = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $blob = Blob::fromContent("write to loose\n");
        $db->write($blob);

        // Verify it exists in the loose store
        $this->assertTrue($db->looseStore()->exists($blob->id));
    }

    #[Test]
    public function listAllIncludesBothLooseAndPacked(): void
    {
        // Create some commits and pack them
        $this->writeFile('a.txt', "a\n");
        $this->git('add .');
        $this->git('commit -m "first"');
        $this->git('gc');

        $db = new ObjectDatabase($this->tmpDir . '/.git/objects');

        // Write a new loose object
        $blob = Blob::fromContent("new loose\n");
        $db->write($blob);

        $all = $db->listAll();

        // Should include the new loose object
        $this->assertContains($blob->id->hex, $all);

        // Should include packed objects too (at least the commit)
        $this->assertGreaterThan(1, count($all));
    }

    #[Test]
    public function looseStoreAndPackStoreAccessors(): void
    {
        $db = new ObjectDatabase($this->tmpDir . '/.git/objects');

        $this->assertInstanceOf(\Pitmaster\Storage\LooseObjectStore::class, $db->looseStore());
        $this->assertInstanceOf(\Pitmaster\Storage\PackFileStore::class, $db->packStore());
    }

    #[Test]
    public function readReturnsCachedObjectInstanceForRepeatedHits(): void
    {
        $db = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $blob = Blob::fromContent("cached object\n");
        $db->write($blob);

        $first = $db->read($blob->id);
        $second = $db->read($blob->id);

        $this->assertNotNull($first);
        $this->assertSame($first, $second);
    }

    #[Test]
    public function objectCacheIsBoundedDuringLongReadSequences(): void
    {
        $db = new ObjectDatabase($this->tmpDir . '/.git/objects');
        $ids = [];

        for ($i = 0; $i < 1100; $i++) {
            $blob = Blob::fromContent("cache-bound-{$i}\n");
            $ids[] = $blob->id;
            $db->write($blob);
        }

        foreach ($ids as $id) {
            $this->assertNotNull($db->read($id));
        }

        $reflection = new \ReflectionProperty($db, 'objectCache');
        $cache = $reflection->getValue($db);

        $this->assertIsArray($cache);
        $this->assertLessThanOrEqual(1024, count($cache));
    }

    #[Test]
    public function objectCachesDoNotLeakAcrossRepositories(): void
    {
        $otherDir = sys_get_temp_dir() . '/pitmaster-test-' . bin2hex(random_bytes(4));
        mkdir($otherDir . '/objects', 0777, true);

        try {
            $firstDb = new ObjectDatabase($this->tmpDir . '/.git/objects');
            $secondDb = new ObjectDatabase($otherDir . '/objects');
            $firstBlob = Blob::fromContent("same content\n");
            $secondBlob = Blob::fromContent("same content\n");

            $firstDb->write($firstBlob);
            $secondDb->write($secondBlob);

            $this->assertNotSame($firstDb->read($firstBlob->id), $secondDb->read($secondBlob->id));
        } finally {
            exec('rm -rf ' . escapeshellarg($otherDir));
        }
    }
}
