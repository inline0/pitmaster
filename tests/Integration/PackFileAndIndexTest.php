<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\Blob;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;
use Pitmaster\Pack\PackEnumerator;
use Pitmaster\Pack\PackFile;
use Pitmaster\Pack\PackIndexer;
use Pitmaster\Pack\PackIndex;
use Pitmaster\Pack\PackWriter;
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

    private function encodePackEntryHeader(int $type, int $size): string
    {
        $byte = ($type << 4) | ($size & 0x0F);
        $size >>= 4;
        $header = '';

        while ($size > 0) {
            $header .= chr(($byte | 0x80) & 0xFF);
            $byte = $size & 0x7F;
            $size >>= 7;
        }

        return $header . chr($byte & 0xFF);
    }

    private function encodeDeltaSize(int $size): string
    {
        $encoded = '';

        do {
            $byte = $size & 0x7F;
            $size >>= 7;

            if ($size > 0) {
                $byte |= 0x80;
            }

            $encoded .= chr($byte);
        } while ($size > 0);

        return $encoded;
    }

    private function deltaAppend(string $base, string $suffix): string
    {
        $baseSize = strlen($base);
        $targetSize = $baseSize + strlen($suffix);
        $delta = $this->encodeDeltaSize($baseSize) . $this->encodeDeltaSize($targetSize);
        $copy = 0x80;
        $copyArgs = '';
        $size = $baseSize;

        for ($i = 0; $i < 3; $i++) {
            $byte = ($size >> ($i * 8)) & 0xFF;

            if ($byte !== 0) {
                $copy |= 0x10 << $i;
                $copyArgs .= chr($byte);
            }
        }

        $delta .= chr($copy) . $copyArgs;

        if ($suffix !== '') {
            $this->assertLessThanOrEqual(127, strlen($suffix));
            $delta .= chr(strlen($suffix)) . $suffix;
        }

        return $delta;
    }

    /**
     * @return array{packPath: string, idxPath: string, baseHash: string, baseDataOffset: int, targetHashes: list<string>}
     */
    private function writeSharedBaseDeltaPack(int $dependents): array
    {
        $packDir = $this->tmpDir . '/.git/objects/pack';
        if (!is_dir($packDir)) {
            mkdir($packDir, 0777, true);
        }

        $baseContent = str_repeat("shared base line\n", 128);
        $baseId = ObjectId::compute(ObjectType::Blob, $baseContent);
        $baseHeader = $this->encodePackEntryHeader(ObjectType::Blob->toPackType(), strlen($baseContent));
        $baseCompressed = zlib_encode($baseContent, ZLIB_ENCODING_DEFLATE);
        $this->assertIsString($baseCompressed);

        $body = $baseHeader . $baseCompressed;
        $baseDataOffset = 12 + strlen($baseHeader);
        $targetHashes = [];

        for ($i = 0; $i < $dependents; $i++) {
            $suffix = sprintf("dependent-%02d\n", $i);
            $targetContent = $baseContent . $suffix;
            $targetHashes[] = ObjectId::compute(ObjectType::Blob, $targetContent)->hex;
            $delta = $this->deltaAppend($baseContent, $suffix);
            $deltaHeader = $this->encodePackEntryHeader(7, strlen($delta)) . $baseId->binary;
            $deltaCompressed = zlib_encode($delta, ZLIB_ENCODING_DEFLATE);
            $this->assertIsString($deltaCompressed);
            $body .= $deltaHeader . $deltaCompressed;
        }

        $packData = 'PACK' . pack('N', 2) . pack('N', $dependents + 1) . $body;
        $packData .= sha1($packData, true);
        $packPath = $packDir . '/pack-shared-base.pack';
        file_put_contents($packPath, $packData);
        $idxPath = PackIndexer::writeIndex($packPath);

        return [
            'packPath' => $packPath,
            'idxPath' => $idxPath,
            'baseHash' => $baseId->hex,
            'baseDataOffset' => $baseDataOffset,
            'targetHashes' => $targetHashes,
        ];
    }

    private function privateProperty(object $object, string $property): mixed
    {
        $reflection = new \ReflectionProperty($object, $property);

        return $reflection->getValue($object);
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
    public function packIndexerCanRebuildGitGeneratedIndex(): void
    {
        $packDir = $this->createPackedRepo();
        $packFiles = $this->findPackFiles($packDir);
        $packPath = $packDir . '/' . $packFiles[0];
        $idxPath = substr($packPath, 0, -5) . '.idx';

        unlink($idxPath);
        $this->assertFileDoesNotExist($idxPath);

        PackIndexer::writeIndex($packPath);
        $this->assertFileExists($idxPath);

        $pack = PackFile::open($packPath, $idxPath);
        $hashes = $pack->allHashes();

        $this->assertNotEmpty($hashes);
        $this->assertNotNull($pack->read($hashes[0]));
    }

    #[Test]
    public function packFileStoreRefreshSeesNewlyWrittenPacks(): void
    {
        $packDir = $this->tmpDir . '/.git/objects/pack';
        if (!is_dir($packDir)) {
            mkdir($packDir, 0777, true);
        }

        $store = new PackFileStore($packDir);
        $this->assertFalse($store->exists(Blob::fromContent("missing\n")->id));

        $blob = Blob::fromContent("new pack content\n");
        PackWriter::write($packDir, [$blob]);

        $this->assertFalse($store->exists($blob->id));

        $store->refresh();

        $this->assertTrue($store->exists($blob->id));
        $this->assertNotNull($store->read($blob->id));
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

    #[Test]
    public function packedObjectInflateReadsBoundedChunksInsteadOfWholePackTail(): void
    {
        $packDir = $this->tmpDir . '/.git/objects/pack';
        $small = Blob::fromContent("small object\n");
        $objects = [$small];

        for ($i = 0; $i < 8; $i++) {
            $objects[] = Blob::fromContent(random_bytes(256 * 1024));
        }

        $paths = PackWriter::write($packDir, $objects);
        $this->assertGreaterThan(1024 * 1024, filesize($paths['packPath']));

        $pack = PackFile::open($paths['packPath'], $paths['idxPath']);
        $object = $pack->read($small->id->hex);

        $this->assertInstanceOf(Blob::class, $object);
        $this->assertSame($small->content, $object->content);
        $this->assertLessThanOrEqual(65536, $this->privateProperty($pack, 'maxCompressedChunkReadBytes'));
    }

    #[Test]
    public function sharedDeltaBaseIsInflatedOnceAcrossDependentObjects(): void
    {
        $fixture = $this->writeSharedBaseDeltaPack(12);
        $pack = PackFile::open($fixture['packPath'], $fixture['idxPath']);

        foreach ($fixture['targetHashes'] as $i => $hash) {
            $object = $pack->read($hash);

            $this->assertInstanceOf(Blob::class, $object);
            $this->assertStringEndsWith(sprintf("dependent-%02d\n", $i), $object->content);
        }

        $inflateCounts = $this->privateProperty($pack, 'inflateCountsByOffset');

        $this->assertSame(1, $inflateCounts[$fixture['baseDataOffset']] ?? 0);
    }
}
