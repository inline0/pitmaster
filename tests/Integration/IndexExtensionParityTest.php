<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Index\Index;
use Pitmaster\Index\IndexWriter;

final class IndexExtensionParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-index-ext-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init -b main >/dev/null');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function treeExtensionRoundTripsGitGeneratedIndex(): void
    {
        mkdir($this->tmpDir . '/dir/sub', 0777, true);
        file_put_contents($this->tmpDir . '/dir/sub/file.txt', "content\n");
        $this->git('add .');
        $this->git('write-tree >/dev/null');

        $this->assertIndexRoundTripsExactly('TREE');
    }

    #[Test]
    public function reucExtensionRoundTripsGitGeneratedIndex(): void
    {
        file_put_contents($this->tmpDir . '/a.txt', "base\n");
        $this->git('add a.txt');
        $this->git('commit -m base >/dev/null');
        $this->git('checkout -b feature >/dev/null');
        file_put_contents($this->tmpDir . '/a.txt', "feature\n");
        $this->git('add a.txt');
        $this->git('commit -m feature >/dev/null');
        $this->git('checkout main >/dev/null');
        file_put_contents($this->tmpDir . '/a.txt', "main\n");
        $this->git('add a.txt');
        $this->git('commit -m main >/dev/null');
        $this->gitWithExit('merge feature >/dev/null');
        file_put_contents($this->tmpDir . '/a.txt', "resolved\n");
        $this->git('add a.txt');

        $this->assertIndexRoundTripsExactly('REUC');
    }

    private function assertIndexRoundTripsExactly(string $signature): void
    {
        $indexPath = $this->tmpDir . '/.git/index';
        $before = file_get_contents($indexPath);

        self::assertIsString($before);
        self::assertStringContainsString($signature, $before, "Expected Git to write {$signature} into the index");

        $lsFilesBefore = $this->git('ls-files --stage');
        $statusBefore = $this->git('status --porcelain=v2');
        $index = Index::open($indexPath);
        IndexWriter::write($index, $indexPath);
        $after = file_get_contents($indexPath);

        self::assertIsString($after);
        self::assertSame($before, $after);
        self::assertSame($lsFilesBefore, $this->git('ls-files --stage'));
        self::assertSame($statusBefore, $this->git('status --porcelain=v2'));
    }

    private function git(string $command): string
    {
        return shell_exec(sprintf(
            'cd %s && git %s 2>&1',
            escapeshellarg($this->tmpDir),
            $command,
        )) ?? '';
    }

    private function gitWithExit(string $command): int
    {
        exec(
            sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $command),
            $_,
            $exitCode,
        );

        return $exitCode;
    }
}
