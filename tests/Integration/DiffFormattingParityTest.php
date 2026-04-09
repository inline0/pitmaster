<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Diff\ColorDiff;
use Pitmaster\Diff\WordDiff;
use Pitmaster\Pitmaster;

final class DiffFormattingParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-diff-format-' . bin2hex(random_bytes(4));
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
    public function wordDiffMatchesGitPlainWordDiff(): void
    {
        file_put_contents($this->tmpDir . '/article.txt', "hello world\n");
        $this->git('add article.txt');
        $this->git('commit -m base');
        file_put_contents($this->tmpDir . '/article.txt', "hello planet\n");

        $gitLines = $this->gitLines('diff --word-diff=plain --no-color -- article.txt');
        $this->assertNotEmpty($gitLines);
        $this->assertSame(
            end($gitLines),
            WordDiff::diff("hello world\n", "hello planet\n"),
        );
    }

    #[Test]
    public function colorizedUnifiedDiffMatchesGitForSingleParentCommit(): void
    {
        file_put_contents($this->tmpDir . '/article.txt', "hello world\n");
        $this->git('add article.txt');
        $this->git('commit -m base');
        file_put_contents($this->tmpDir . '/article.txt', "hello planet\n");
        $this->git('add article.txt');
        $this->git('commit -m second');

        $repo = Pitmaster::open($this->tmpDir);
        $result = $repo->show('HEAD');
        $diff = implode('', array_map(
            static fn ($entry) => $entry->format(),
            $result['diff'],
        ));

        $this->assertSame(
            $this->git('show --format= --no-color --no-renames HEAD'),
            $diff,
        );
        $this->assertSame(
            $this->git('show --format= --color=always --no-renames HEAD'),
            ColorDiff::colorize($diff),
        );
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

    /**
     * @return list<string>
     */
    private function gitLines(string $command): array
    {
        return array_values(array_filter(
            explode("\n", trim($this->git($command))),
            static fn (string $line): bool => $line !== '',
        ));
    }
}
