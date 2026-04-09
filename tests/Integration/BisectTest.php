<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Graph\Bisect;
use Pitmaster\Object\ObjectId;
use Pitmaster\Pitmaster;
use Pitmaster\Repository;

/**
 * Test Graph\Bisect against a real git repository.
 */
final class BisectTest extends TestCase
{
    private string $tmpDir;
    private Repository $repo;
    /** @var array<int, string> Commit hashes in chronological order */
    private array $commits = [];

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');

        // Create 8 commits for a meaningful bisect range
        for ($i = 1; $i <= 8; $i++) {
            $this->writeFile('file.txt', "version {$i}\n");
            $this->git('add file.txt');
            $this->git('commit -m "Commit ' . $i . '"');
            $this->commits[] = trim($this->git('rev-parse HEAD'));
        }

        $this->repo = Pitmaster::open($this->tmpDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function startReturnsMidpointCommit(): void
    {
        $bad = ObjectId::fromHex($this->commits[7]);   // Last commit
        $good = ObjectId::fromHex($this->commits[0]);  // First commit

        $bisect = new Bisect(
            $this->repo->objectDatabase(),
            $this->tmpDir . '/.git',
        );

        $midpoint = $bisect->start($bad, $good);

        // Midpoint should be one of the commits in our range
        $this->assertContains($midpoint->hex, $this->commits);

        // It should be roughly in the middle
        $midIndex = array_search($midpoint->hex, $this->commits, true);
        $this->assertGreaterThan(0, $midIndex);
        $this->assertLessThan(7, $midIndex);
    }

    #[Test]
    public function isActiveReturnsTrueDuringBisect(): void
    {
        $bisect = new Bisect(
            $this->repo->objectDatabase(),
            $this->tmpDir . '/.git',
        );

        $this->assertFalse($bisect->isActive());

        $bad = ObjectId::fromHex($this->commits[7]);
        $good = ObjectId::fromHex($this->commits[0]);
        $bisect->start($bad, $good);

        $this->assertTrue($bisect->isActive());
    }

    #[Test]
    public function resetClearsState(): void
    {
        $bisect = new Bisect(
            $this->repo->objectDatabase(),
            $this->tmpDir . '/.git',
        );

        $bad = ObjectId::fromHex($this->commits[7]);
        $good = ObjectId::fromHex($this->commits[0]);
        $bisect->start($bad, $good);
        $this->assertTrue($bisect->isActive());

        $bisect->reset();
        $this->assertFalse($bisect->isActive());

        // Bisect files should be cleaned up
        $this->assertFileDoesNotExist($this->tmpDir . '/.git/BISECT_START');
        $this->assertFileDoesNotExist($this->tmpDir . '/.git/BISECT_LOG');
        $this->assertDirectoryDoesNotExist($this->tmpDir . '/.git/refs/bisect');
    }

    #[Test]
    public function bisectWritesStateFiles(): void
    {
        $bisect = new Bisect(
            $this->repo->objectDatabase(),
            $this->tmpDir . '/.git',
        );

        $bad = ObjectId::fromHex($this->commits[7]);
        $good = ObjectId::fromHex($this->commits[0]);
        $bisect->start($bad, $good);

        $this->assertFileExists($this->tmpDir . '/.git/BISECT_START');
        $this->assertFileExists($this->tmpDir . '/.git/BISECT_LOG');
        $this->assertFileExists($this->tmpDir . '/.git/BISECT_EXPECTED_REV');
        $this->assertFileExists($this->tmpDir . '/.git/BISECT_TERMS');
        $this->assertFileExists($this->tmpDir . '/.git/refs/bisect/bad');
        $this->assertFileExists($this->tmpDir . '/.git/refs/bisect/good-' . $this->commits[0]);

        // Bad should contain the bad commit hash
        $badContent = trim(file_get_contents($this->tmpDir . '/.git/refs/bisect/bad'));
        $this->assertSame($this->commits[7], $badContent);
    }

    private function git(string $cmd): string
    {
        return shell_exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $cmd)) ?? '';
    }

    private function writeFile(string $path, string $content): void
    {
        $full = $this->tmpDir . '/' . $path;
        $dir = dirname($full);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($full, $content);
    }
}
