<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;

final class DiffAlgorithmParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-diff-algorithms-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init --initial-branch=main');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function patienceDiffMatchesGit(): void
    {
        $repo = $this->seedMovedBlockRepo();

        $this->assertSame(
            $this->git('diff --patience --no-color -- article.txt'),
            $this->formatDiff($repo->diff('article.txt', 'patience')),
        );
    }

    #[Test]
    public function histogramDiffMatchesGit(): void
    {
        $repo = $this->seedMovedBlockRepo();

        $this->assertSame(
            $this->git('diff --histogram --no-color -- article.txt'),
            $this->formatDiff($repo->diff('article.txt', 'histogram')),
        );
    }

    #[Test]
    public function minimalDiffMatchesGit(): void
    {
        $repo = $this->seedMovedBlockRepo();

        $this->assertSame(
            $this->git('diff --minimal --no-color -- article.txt'),
            $this->formatDiff($repo->diff('article.txt', 'minimal')),
        );
    }

    private function seedMovedBlockRepo(): \Pitmaster\Repository
    {
        file_put_contents($this->tmpDir . '/article.txt', implode("\n", [
            'a',
            'unique1',
            'repeat',
            'repeat',
            'unique2',
            'z',
            '',
        ]));
        $this->git('add article.txt');
        $this->git('commit -m base');

        file_put_contents($this->tmpDir . '/article.txt', implode("\n", [
            'a',
            'unique1',
            'unique2',
            'repeat',
            'repeat',
            'z',
            '',
        ]));

        return Pitmaster::open($this->tmpDir);
    }

    /**
     * @param array<int, \Pitmaster\Diff\DiffResult> $diffs
     */
    private function formatDiff(array $diffs): string
    {
        return implode('', array_map(static fn ($entry) => $entry->format(), $diffs));
    }

    private function git(string $command): string
    {
        exec(
            sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $command),
            $output,
            $exitCode,
        );

        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->fail("git {$command} failed:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }
}
