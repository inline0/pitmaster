<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Index\Index;
use Pitmaster\Index\IndexEntry;
use Pitmaster\Index\IndexWriter;
use Pitmaster\Object\ObjectId;

final class IndexEntryAndWriterTest extends TestCase
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
    public function createBuildsValidEntry(): void
    {
        $hash = ObjectId::fromHex(str_repeat('ab', 20));
        $entry = IndexEntry::create('src/main.php', $hash, 0100644, 42);

        $this->assertSame('src/main.php', $entry->path);
        $this->assertSame($hash->hex, $entry->hash->hex);
        $this->assertSame(0100644, $entry->mode);
        $this->assertSame(42, $entry->fileSize);
        $this->assertSame(0, $entry->stage());
        $this->assertFalse($entry->assumeValid());
    }

    #[Test]
    public function fromStatBuildsEntryFromRealFile(): void
    {
        $this->writeFile('test.txt', "hello world\n");
        $fullPath = $this->tmpDir . '/test.txt';
        $hash = ObjectId::fromHex(str_repeat('cd', 20));

        $entry = IndexEntry::fromStat('test.txt', $hash, $fullPath);

        $this->assertSame('test.txt', $entry->path);
        $this->assertSame($hash->hex, $entry->hash->hex);
        $this->assertGreaterThan(0, $entry->mtimeSec);
        $this->assertSame(12, $entry->fileSize);
    }

    #[Test]
    public function writeAndReadBackRoundTrip(): void
    {
        // Stage files using git
        $this->writeFile('a.txt', "alpha\n");
        $this->writeFile('b.txt', "beta\n");
        $this->git('add .');

        // Read the git-created index
        $indexPath = $this->tmpDir . '/.git/index';
        $originalIndex = Index::open($indexPath);
        $this->assertSame(2, $originalIndex->count());

        // Write it back using IndexWriter
        $outputPath = $this->tmpDir . '/.git/index-test';
        IndexWriter::write($originalIndex, $outputPath);

        // Read the written index back
        $rereadIndex = Index::open($outputPath);
        $this->assertSame(2, $rereadIndex->count());

        // Verify entries match
        foreach ($originalIndex->entries() as $path => $origEntry) {
            $rereadEntry = $rereadIndex->entry($path);
            $this->assertNotNull($rereadEntry, "Missing entry: {$path}");
            $this->assertSame($origEntry->hash->hex, $rereadEntry->hash->hex);
            $this->assertSame($origEntry->mode, $rereadEntry->mode);
            $this->assertSame($origEntry->path, $rereadEntry->path);
        }
    }

    #[Test]
    public function writtenIndexMatchesGitLsFiles(): void
    {
        // Create index with IndexWriter
        $index = new Index();
        $this->writeFile('hello.txt', "hello\n");

        // Hash the file with git to get the correct blob hash
        $blobHash = trim($this->git('hash-object hello.txt'));
        $entry = IndexEntry::create('hello.txt', ObjectId::fromHex($blobHash), 0100644, 6);
        $index->addEntry($entry);

        // Write the index
        IndexWriter::write($index, $this->tmpDir . '/.git/index');

        // Verify git can read it
        $gitLsFiles = trim($this->git('ls-files --stage'));
        $this->assertStringContainsString($blobHash, $gitLsFiles);
        $this->assertStringContainsString('hello.txt', $gitLsFiles);
    }

    #[Test]
    public function stageFieldIsCorrect(): void
    {
        $hash = ObjectId::fromHex(str_repeat('00', 20));

        $entry0 = IndexEntry::create('file.txt', $hash, 0100644, 0, 0);
        $this->assertSame(0, $entry0->stage());

        $entry1 = IndexEntry::create('file.txt', $hash, 0100644, 0, 1);
        $this->assertSame(1, $entry1->stage());

        $entry2 = IndexEntry::create('file.txt', $hash, 0100644, 0, 2);
        $this->assertSame(2, $entry2->stage());

        $entry3 = IndexEntry::create('file.txt', $hash, 0100644, 0, 3);
        $this->assertSame(3, $entry3->stage());
    }

    #[Test]
    public function writeAndReadConflictStagesForSamePath(): void
    {
        $index = new Index();
        $base = ObjectId::fromHex(str_repeat('11', 20));
        $ours = ObjectId::fromHex(str_repeat('22', 20));
        $theirs = ObjectId::fromHex(str_repeat('33', 20));

        $index->addEntry(IndexEntry::create('conflict.txt', $base, 0100644, 0, 1));
        $index->addEntry(IndexEntry::create('conflict.txt', $ours, 0100644, 0, 2));
        $index->addEntry(IndexEntry::create('conflict.txt', $theirs, 0100644, 0, 3));
        IndexWriter::write($index, $this->tmpDir . '/.git/index');

        $reread = Index::open($this->tmpDir . '/.git/index');
        $stages = $reread->stageEntries('conflict.txt');

        $this->assertCount(3, $reread->allEntries());
        $this->assertNull($reread->entry('conflict.txt'));
        $this->assertSame($base->hex, $stages[1]->hash->hex);
        $this->assertSame($ours->hex, $stages[2]->hash->hex);
        $this->assertSame($theirs->hex, $stages[3]->hash->hex);
        $this->assertSame(
            "100644 {$base->hex} 1\tconflict.txt\n"
            . "100644 {$ours->hex} 2\tconflict.txt\n"
            . "100644 {$theirs->hex} 3\tconflict.txt",
            trim($this->git('ls-files --stage'))
        );
    }

    #[Test]
    public function bulkAddAndRemovePreserveSequentialSemantics(): void
    {
        $index = new Index();
        $base = ObjectId::fromHex(str_repeat('11', 20));
        $ours = ObjectId::fromHex(str_repeat('22', 20));
        $theirs = ObjectId::fromHex(str_repeat('33', 20));
        $resolved = ObjectId::fromHex(str_repeat('44', 20));

        $index->addEntry(IndexEntry::create('conflict.txt', $base, 0100644, 0));
        $index->addEntries([
            IndexEntry::create('conflict.txt', $ours, 0100644, 0, 2),
            IndexEntry::create('conflict.txt', $theirs, 0100644, 0, 3),
        ]);

        $this->assertNull($index->entry('conflict.txt'));
        $this->assertCount(2, $index->stageEntries('conflict.txt'));

        $index->addEntries([
            IndexEntry::create('conflict.txt', $resolved, 0100644, 0),
            IndexEntry::create('other.txt', $base, 0100644, 0),
        ]);

        $this->assertSame($resolved->hex, $index->entry('conflict.txt')?->hash->hex);
        $this->assertSame(['conflict.txt', 'other.txt'], $index->paths());

        $index->removeEntries(['conflict.txt']);
        $this->assertNull($index->entry('conflict.txt'));
        $this->assertSame(['other.txt'], $index->paths());
    }

    #[Test]
    public function bulkStageRemovalCanTargetSpecificStages(): void
    {
        $index = new Index();
        $base = ObjectId::fromHex(str_repeat('11', 20));
        $ours = ObjectId::fromHex(str_repeat('22', 20));
        $theirs = ObjectId::fromHex(str_repeat('33', 20));

        $index->addEntries([
            IndexEntry::create('conflict.txt', $base, 0100644, 0, 1),
            IndexEntry::create('conflict.txt', $ours, 0100644, 0, 2),
            IndexEntry::create('conflict.txt', $theirs, 0100644, 0, 3),
        ]);

        $index->removeEntries(['conflict.txt'], 2);

        $this->assertArrayHasKey(1, $index->stageEntries('conflict.txt'));
        $this->assertArrayNotHasKey(2, $index->stageEntries('conflict.txt'));
        $this->assertArrayHasKey(3, $index->stageEntries('conflict.txt'));
    }
}
