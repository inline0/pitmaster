<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;

final class IndexFormatParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-index-format-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init --initial-branch=main');
        $this->git('config user.email test@example.com');
        $this->git('config user.name Test');
        $this->writeTree();
        $this->git('add .');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function version3IndexReadsLikeGitLsFiles(): void
    {
        $fixtureRepo = $this->tmpDir . '/fixture-v3';
        $fixtureIndex = dirname(__DIR__, 2) . '/fixtures/upstream/jgit/org.eclipse.jgit.test/tst-rsrc/org/eclipse/jgit/test/resources/gitgit.index.v3';

        mkdir($fixtureRepo, 0777, true);
        $this->gitIn($this->tmpDir, 'init --initial-branch=main ' . escapeshellarg($fixtureRepo));
        copy($fixtureIndex, $fixtureRepo . '/.git/index');

        $repo = Pitmaster::open($fixtureRepo);

        $this->assertSame(3, $repo->index()->version());
        $this->assertSame($this->gitIn($fixtureRepo, 'ls-files --stage'), $this->formatIndexEntries($repo));
    }

    #[Test]
    public function version4IndexReadsLikeGitLsFiles(): void
    {
        $this->git('update-index --index-version 4 --force-write-index');

        $repo = Pitmaster::open($this->tmpDir);

        $this->assertSame(4, $repo->index()->version());
        $this->assertSame($this->git('ls-files --stage'), $this->formatIndexEntries($repo));
    }

    private function writeTree(): void
    {
        foreach (
            [
            'alpha.txt' => "alpha\n",
            'nested/one.txt' => "one\n",
            'nested/two.txt' => "two\n",
            'nested/deeper/three.txt' => "three\n",
            'nested/deeper/four.txt' => "four\n",
            ] as $path => $content
        ) {
            $fullPath = $this->tmpDir . '/' . $path;
            $dir = dirname($fullPath);

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            file_put_contents($fullPath, $content);
        }
    }

    private function formatIndexEntries(\Pitmaster\Repository $repo): string
    {
        $entries = [];

        foreach ($repo->index()->allEntries() as $entry) {
            $entries[] = [
                'path' => $entry->path,
                'stage' => $entry->stage(),
                'line' => sprintf(
                    '%06o %s %d	%s',
                    $entry->mode,
                    $entry->hash->hex,
                    $entry->stage(),
                    $entry->path,
                ),
            ];
        }

        usort($entries, static function (array $a, array $b): int {
            $pathCmp = strcmp($a['path'], $b['path']);

            if ($pathCmp !== 0) {
                return $pathCmp;
            }

            return $a['stage'] <=> $b['stage'];
        });

        $lines = array_map(static fn (array $entry): string => $entry['line'], $entries);

        return implode("\n", $lines) . ($lines !== [] ? "\n" : '');
    }

    private function git(string $command): string
    {
        return $this->gitIn($this->tmpDir, $command);
    }

    private function gitIn(string $dir, string $command): string
    {
        exec(
            sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command),
            $output,
            $exitCode,
        );

        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            self::fail("git {$command} failed in {$dir}:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }
}
