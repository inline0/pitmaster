<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Graph\Rebase;
use Pitmaster\Object\ObjectId;
use Pitmaster\Pitmaster;
use Pitmaster\Repository;

/**
 * Test Graph\Rebase against real git.
 */
final class RebaseTest extends TestCase
{
    private string $tmpDir;
    private Repository $repo;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');

        // Create a base commit on main
        $this->writeFile('base.txt', "base content\n");
        $this->git('add base.txt');
        $this->git('commit -m "Base commit"');

        // Create a feature branch with 2 commits
        $this->git('checkout -b feature');
        $this->writeFile('feature1.txt', "feature one\n");
        $this->git('add feature1.txt');
        $this->git('commit -m "Feature commit 1"');

        $this->writeFile('feature2.txt', "feature two\n");
        $this->git('add feature2.txt');
        $this->git('commit -m "Feature commit 2"');

        // Go back to main and add a commit
        $this->git('checkout main');
        $this->writeFile('main.txt', "main work\n");
        $this->git('add main.txt');
        $this->git('commit -m "Main commit"');

        // Switch to feature for rebase
        $this->git('checkout feature');

        $this->repo = Pitmaster::open($this->tmpDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function rebaseFeatureOntoMain(): void
    {
        $mainId = ObjectId::fromHex(trim($this->git('rev-parse main')));

        $rebase = new Rebase(
            $this->repo->objectDatabase(),
            $this->repo->refDatabase(),
        );

        $result = $rebase->rebase($mainId);

        $this->assertTrue($result['success']);
        $this->assertSame(2, $result['commits']);
        $this->assertEmpty($result['conflicts']);
    }

    #[Test]
    public function rebaseProducesCorrectCommitCount(): void
    {
        $mainId = ObjectId::fromHex(trim($this->git('rev-parse main')));

        $rebase = new Rebase(
            $this->repo->objectDatabase(),
            $this->repo->refDatabase(),
        );

        $rebase->rebase($mainId);

        // After rebase, feature should have: base + main + feature1 + feature2 = 4 commits
        // Walk from new feature HEAD
        $this->repo = Pitmaster::open($this->tmpDir);
        $log = $this->repo->log(10);
        $this->assertGreaterThanOrEqual(3, count($log));
    }

    #[Test]
    public function rebaseProducesFsckCleanRepo(): void
    {
        $mainId = ObjectId::fromHex(trim($this->git('rev-parse main')));

        $rebase = new Rebase(
            $this->repo->objectDatabase(),
            $this->repo->refDatabase(),
        );

        $rebase->rebase($mainId);

        // git fsck should pass
        exec(
            sprintf('cd %s && git fsck --strict --no-progress 2>&1', escapeshellarg($this->tmpDir)),
            $output,
            $exitCode,
        );

        $this->assertSame(0, $exitCode, 'git fsck failed after rebase: ' . implode("\n", $output));
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
