<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pack\CommitGraph;
use Pitmaster\Pack\MultiPackIndex;

final class CommitGraphAndMidxTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-graph-midx-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init --initial-branch=main');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');
        $this->git('config gc.auto 0');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function commitGraphOpenOnNonexistentPathReturnsNull(): void
    {
        $this->assertNull(CommitGraph::open($this->tmpDir . '/missing-commit-graph'));
    }

    #[Test]
    public function multiPackIndexOpenOnNonexistentPathReturnsNull(): void
    {
        $this->assertNull(MultiPackIndex::open($this->tmpDir . '/missing-midx'));
    }

    #[Test]
    public function commitGraphParsesGitGeneratedMetadata(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            file_put_contents($this->tmpDir . '/history.txt', "commit {$i}\n");
            $this->git('add history.txt');
            $this->git('commit -m ' . escapeshellarg("commit {$i}"));
        }

        $this->git('commit-graph write --reachable');

        $graph = CommitGraph::open($this->tmpDir . '/.git/objects/info/commit-graph');

        $this->assertNotNull($graph);
        $this->assertSame(
            count($this->gitLines('rev-list --all')),
            $graph->objectCount(),
        );

        $head = trim($this->git('rev-parse HEAD'));
        [$tree, $timestamp] = explode('|', trim($this->git('show -s --format=%T\\|%ct HEAD')));
        $parents = $this->gitLines('show -s --format=%P HEAD');
        $lookup = $graph->lookup($head);

        $this->assertNotNull($lookup);
        $this->assertSame($tree, $lookup['tree']);
        $this->assertSame((int) $timestamp, $lookup['timestamp']);
        $this->assertGreaterThan(0, $lookup['generation']);
        $this->assertNotSame(-1, $lookup['parent1']);
        $this->assertSame(1, count(array_filter(explode(' ', $parents[0] ?? ''))));
    }

    #[Test]
    public function multiPackIndexParsesGitGeneratedIndex(): void
    {
        file_put_contents($this->tmpDir . '/tracked.txt', "base\n");
        $this->git('add tracked.txt');
        $this->git('commit -m base');
        $this->git('pack-objects .git/objects/pack/pack-one --all >/dev/null');

        file_put_contents($this->tmpDir . '/tracked.txt', "changed\n");
        file_put_contents($this->tmpDir . '/extra.txt', "extra\n");
        $this->git('add tracked.txt extra.txt');
        $this->git('commit -m second');
        $this->git('pack-objects .git/objects/pack/pack-two --all >/dev/null');
        $this->git('multi-pack-index write');

        $midx = MultiPackIndex::open($this->tmpDir . '/.git/objects/pack/multi-pack-index');

        $this->assertNotNull($midx);
        $this->assertSame(
            count($this->gitLines('rev-list --objects --all')),
            $midx->objectCount(),
        );

        $expectedPackNames = array_map(
            static fn (string $path): string => basename($path),
            $this->shellLines('find .git/objects/pack -maxdepth 1 -name \'*.idx\' | sort'),
        );

        $this->assertSame($expectedPackNames, $midx->packNames());

        $blobId = trim($this->git('rev-parse HEAD:tracked.txt'));
        $entry = $midx->findObject($blobId);

        $this->assertNotNull($entry);
        $this->assertArrayHasKey('pack', $entry);
        $this->assertArrayHasKey('offset', $entry);
        $this->assertGreaterThanOrEqual(0, $entry['pack']);
        $this->assertLessThan(count($expectedPackNames), $entry['pack']);
        $this->assertGreaterThan(0, $entry['offset']);
    }

    #[Test]
    public function commitGraphCanBeConstructedFromInvalidData(): void
    {
        $path = $this->tmpDir . '/bad-graph';
        file_put_contents($path, str_repeat("\x00", 100));

        $graph = CommitGraph::open($path);

        $this->assertNotNull($graph);
        $this->assertSame(0, $graph->objectCount());
        $this->assertNull($graph->lookup(str_repeat('ab', 20)));
    }

    #[Test]
    public function multiPackIndexCanBeConstructedFromInvalidData(): void
    {
        $path = $this->tmpDir . '/bad-midx';
        file_put_contents($path, str_repeat("\x00", 100));

        $midx = MultiPackIndex::open($path);

        $this->assertNotNull($midx);
        $this->assertSame(0, $midx->objectCount());
        $this->assertNull($midx->findObject(str_repeat('ab', 20)));
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

    /**
     * @return list<string>
     */
    private function shellLines(string $command): array
    {
        exec(
            sprintf('cd %s && %s 2>&1', escapeshellarg($this->tmpDir), $command),
            $output,
            $exitCode,
        );
        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->fail("shell {$command} failed:\n{$result}");
        }

        return array_values(array_filter(
            explode("\n", trim($result)),
            static fn (string $line): bool => $line !== '',
        ));
    }
}
