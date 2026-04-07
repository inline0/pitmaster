<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\Blob;
use Pitmaster\Pack\PackWriter;

/**
 * Test Pack\PackWriter output against git verify-pack and git index-pack.
 */
final class PackWriterTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function writtenPackPassesGitVerifyPack(): void
    {
        $objects = [
            Blob::fromContent("Hello World\n"),
            Blob::fromContent("Goodbye World\n"),
            Blob::fromContent("Third blob content\n"),
        ];

        $packDir = $this->tmpDir . '/.git/objects/pack';
        $result = PackWriter::write($packDir, $objects);

        $this->assertFileExists($result['packPath']);
        $this->assertFileExists($result['idxPath']);

        // git verify-pack should succeed
        $output = $this->git('verify-pack -v ' . escapeshellarg($result['packPath']));
        $this->assertStringNotContainsString('error', strtolower($output));

        // Verify all object hashes appear in the output
        foreach ($objects as $obj) {
            $this->assertStringContainsString($obj->id->hex, $output);
        }
    }

    #[Test]
    public function writtenPackCanBeIndexedByGit(): void
    {
        $objects = [
            Blob::fromContent("index test content\n"),
            Blob::fromContent("second file for indexing\n"),
        ];

        $packDir = $this->tmpDir . '/pack-test';
        mkdir($packDir, 0777, true);
        $result = PackWriter::write($packDir, $objects);

        // Remove the index file so git index-pack can recreate it
        unlink($result['idxPath']);

        // git index-pack should succeed
        exec(
            sprintf('git index-pack %s 2>&1', escapeshellarg($result['packPath'])),
            $output,
            $exitCode,
        );

        $this->assertSame(0, $exitCode, 'git index-pack failed: ' . implode("\n", $output));

        // The index file should be recreated
        $this->assertFileExists($result['idxPath']);
    }

    #[Test]
    public function singleObjectPack(): void
    {
        $objects = [
            Blob::fromContent("solo blob\n"),
        ];

        $packDir = $this->tmpDir . '/.git/objects/pack';
        $result = PackWriter::write($packDir, $objects);

        $output = $this->git('verify-pack -v ' . escapeshellarg($result['packPath']));
        $this->assertStringContainsString($objects[0]->id->hex, $output);
        $this->assertStringContainsString('blob', $output);
    }

    #[Test]
    public function packContainsCorrectObjectCount(): void
    {
        $objects = [];

        for ($i = 0; $i < 10; $i++) {
            $objects[] = Blob::fromContent("blob number {$i}\n");
        }

        $packDir = $this->tmpDir . '/.git/objects/pack';
        $result = PackWriter::write($packDir, $objects);

        // Verify the pack header contains the right count
        $packData = file_get_contents($result['packPath']);
        $this->assertStringStartsWith('PACK', $packData);

        // Unpack header: 4 bytes magic, 4 bytes version, 4 bytes count
        $count = unpack('N', substr($packData, 8, 4))[1];
        $this->assertSame(10, $count);
    }

    private function git(string $cmd): string
    {
        return shell_exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $cmd)) ?? '';
    }
}
