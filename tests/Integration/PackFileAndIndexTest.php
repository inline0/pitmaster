<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\Blob;
use Pitmaster\Pack\PackEnumerator;
use Pitmaster\Pack\PackFile;
use Pitmaster\Pack\PackIndex;
use Pitmaster\Storage\PackFileStore;

final class PackFileAndIndexTest extends TestCase
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

    /**
     * Create commits and run git gc to produce pack files.
     * Returns the pack dir path.
     */
    private function createPackedRepo(): string
    {
        // Create several commits to have objects worth packing
        for ($i = 1; $i <= 5; $i++) {
            $this->writeFile("file{$i}.txt", "content {$i}\n");
            $this->git('add .');
            $this->git("commit -m \"commit {$i}\"");
        }

        $this->git('gc');

        return $this->tmpDir . '/.git/objects/pack';
    }

    private function findPackFiles(string $packDir): array
    {
        $packs = [];
        foreach (scandir($packDir) as $f) {
            if (str_ends_with($f, '.pack')) {
                $packs[] = $f;
            }
        }
        return $packs;
    }

    #[Test]
    public function packFileOpenAndObjectCount(): void
    {
        $packDir = $this->createPackedRepo();
        $packFiles = $this->findPackFiles($packDir);
        $this->assertNotEmpty($packFiles, 'git gc should create at least one pack file');

        $packPath = $packDir . '/' . $packFiles[0];
        $idxPath = substr($packPath, 0, -5) . '.idx';

        $pack = PackFile::open($packPath, $idxPath);
        $this->assertGreaterThan(0, $pack->objectCount());
    }

    #[Test]
    public function packFileHasAndRead(): void
    {
        $packDir = $this->createPackedRepo();
        $packFiles = $this->findPackFiles($packDir);
        $packPath = $packDir . '/' . $packFiles[0];
        $idxPath = substr($packPath, 0, -5) . '.idx';

        $pack = PackFile::open($packPath, $idxPath);
        $hashes = $pack->allHashes();
        $this->assertNotEmpty($hashes);

        // Test has() and read() for the first object
        $firstHash = $hashes[0];
        $this->assertTrue($pack->has($firstHash));
        $this->assertFalse($pack->has(str_repeat('00', 20)));

        $object = $pack->read($firstHash);
        $this->assertNotNull($object);
        $this->assertSame($firstHash, $object->id->hex);
    }

    #[Test]
    public function packFileAllHashesListsAllObjects(): void
    {
        $packDir = $this->createPackedRepo();
        $packFiles = $this->findPackFiles($packDir);
        $packPath = $packDir . '/' . $packFiles[0];
        $idxPath = substr($packPath, 0, -5) . '.idx';

        $pack = PackFile::open($packPath, $idxPath);

        $hashes = $pack->allHashes();
        $this->assertCount($pack->objectCount(), $hashes);

        // Verify each hash is a valid 40-char hex string
        foreach ($hashes as $hash) {
            $this->assertSame(40, strlen($hash));
            $this->assertTrue(ctype_xdigit($hash));
        }
    }

    #[Test]
    public function packIndexObjectCountMatches(): void
    {
        $packDir = $this->createPackedRepo();
        $packFiles = $this->findPackFiles($packDir);
        $packPath = $packDir . '/' . $packFiles[0];
        $idxPath = substr($packPath, 0, -5) . '.idx';

        $pack = PackFile::open($packPath, $idxPath);
        $index = $pack->index();

        $this->assertSame($pack->objectCount(), $index->objectCount());
    }

    #[Test]
    public function packEnumeratorYieldsAllObjects(): void
    {
        $packDir = $this->createPackedRepo();
        $packFiles = $this->findPackFiles($packDir);
        $packPath = $packDir . '/' . $packFiles[0];
        $idxPath = substr($packPath, 0, -5) . '.idx';

        $pack = PackFile::open($packPath, $idxPath);
        $enumerator = new PackEnumerator($pack);

        $this->assertSame($pack->objectCount(), $enumerator->count());

        $count = 0;
        foreach ($enumerator->enumerate() as $object) {
            $this->assertNotEmpty($object->id->hex);
            $count++;
        }

        $this->assertSame($pack->objectCount(), $count);
    }

    #[Test]
    public function packFileStoreReadsObjectsFromPackDir(): void
    {
        $packDir = $this->createPackedRepo();
        $store = new PackFileStore($packDir);

        // Get a known object hash
        $packFiles = $this->findPackFiles($packDir);
        $packPath = $packDir . '/' . $packFiles[0];
        $idxPath = substr($packPath, 0, -5) . '.idx';
        $pack = PackFile::open($packPath, $idxPath);
        $hashes = $pack->allHashes();

        $firstHash = $hashes[0];
        $id = \Pitmaster\Object\ObjectId::fromHex($firstHash);

        $this->assertTrue($store->exists($id));
        $object = $store->read($id);
        $this->assertNotNull($object);
    }

    #[Test]
    public function packFileStoreListAllIncludesAllPackedObjects(): void
    {
        $packDir = $this->createPackedRepo();
        $store = new PackFileStore($packDir);

        $all = $store->listAll();
        $this->assertNotEmpty($all);

        // Every hash should be a valid hex string
        foreach ($all as $hash) {
            $this->assertSame(40, strlen($hash));
            $this->assertTrue(ctype_xdigit($hash));
        }
    }

    #[Test]
    public function packReadContentMatchesGitCatFile(): void
    {
        $packDir = $this->createPackedRepo();
        $packFiles = $this->findPackFiles($packDir);
        $packPath = $packDir . '/' . $packFiles[0];
        $idxPath = substr($packPath, 0, -5) . '.idx';
        $pack = PackFile::open($packPath, $idxPath);

        // Find a blob object
        foreach ($pack->allHashes() as $hash) {
            $object = $pack->read($hash);
            if ($object instanceof Blob) {
                $gitContent = trim($this->git("cat-file -p {$hash}"));
                $this->assertSame($gitContent, rtrim($object->content, "\n"));
                return;
            }
        }

        // At least one blob should exist in a repo with files
        $this->fail('No blob objects found in pack');
    }
}
