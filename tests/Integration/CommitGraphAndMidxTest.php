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
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        exec(sprintf('cd %s && git init && git config user.email t@t.com && git config user.name T 2>&1', escapeshellarg($this->tmpDir)));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function commitGraphOpenOnNonexistentPathReturnsNull(): void
    {
        $result = CommitGraph::open($this->tmpDir . '/nonexistent-commit-graph');

        $this->assertNull($result);
    }

    #[Test]
    public function multiPackIndexOpenOnNonexistentPathReturnsNull(): void
    {
        $result = MultiPackIndex::open($this->tmpDir . '/nonexistent-midx');

        $this->assertNull($result);
    }

    #[Test]
    public function commitGraphCanBeConstructedFromInvalidData(): void
    {
        // Write a file with invalid magic
        $path = $this->tmpDir . '/bad-graph';
        file_put_contents($path, str_repeat("\x00", 100));

        $result = CommitGraph::open($path);

        // Should return a CommitGraph with 0 objects (invalid magic handled gracefully)
        $this->assertNotNull($result);
        $this->assertSame(0, $result->objectCount());
    }

    #[Test]
    public function multiPackIndexCanBeConstructedFromInvalidData(): void
    {
        $path = $this->tmpDir . '/bad-midx';
        file_put_contents($path, str_repeat("\x00", 100));

        $result = MultiPackIndex::open($path);

        $this->assertNotNull($result);
        $this->assertSame(0, $result->objectCount());
    }

    #[Test]
    public function commitGraphLookupOnEmptyGraphReturnsNull(): void
    {
        $path = $this->tmpDir . '/bad-graph';
        file_put_contents($path, str_repeat("\x00", 100));

        $graph = CommitGraph::open($path);
        $this->assertNotNull($graph);

        $result = $graph->lookup(str_repeat('ab', 20));
        $this->assertNull($result);
    }

    #[Test]
    public function multiPackIndexFindObjectOnEmptyReturnsNull(): void
    {
        $path = $this->tmpDir . '/bad-midx';
        file_put_contents($path, str_repeat("\x00", 100));

        $midx = MultiPackIndex::open($path);
        $this->assertNotNull($midx);

        $result = $midx->findObject(str_repeat('ab', 20));
        $this->assertNull($result);
    }
}
