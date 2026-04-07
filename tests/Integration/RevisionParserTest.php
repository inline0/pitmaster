<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Graph\RevisionParser;
use Pitmaster\Pitmaster;
use Pitmaster\Repository;

/**
 * Test RevisionParser against real git rev-parse output.
 */
final class RevisionParserTest extends TestCase
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

        // Create 3 commits on main
        $this->writeFile('a.txt', "first\n");
        $this->git('add a.txt');
        $this->git('commit -m "First commit"');

        $this->writeFile('a.txt', "second\n");
        $this->git('add a.txt');
        $this->git('commit -m "Second commit"');

        $this->writeFile('a.txt', "third\n");
        $this->git('add a.txt');
        $this->git('commit -m "Third commit"');

        // Create a branch pointing at HEAD
        $this->git('branch feature');

        $this->repo = Pitmaster::open($this->tmpDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function resolveHeadMatchesGitRevParse(): void
    {
        $gitHead = trim($this->git('rev-parse HEAD'));
        $pmHead = $this->repo->resolve('HEAD');

        $this->assertSame($gitHead, $pmHead->hex);
    }

    #[Test]
    public function resolveHeadTildeOneMatchesGit(): void
    {
        $gitResult = trim($this->git('rev-parse HEAD~1'));
        $pmResult = $this->repo->resolve('HEAD~1');

        $this->assertSame($gitResult, $pmResult->hex);
    }

    #[Test]
    public function resolveHeadCaretOneMatchesGit(): void
    {
        $gitResult = trim($this->git('rev-parse HEAD^1'));
        $pmResult = $this->repo->resolve('HEAD^1');

        $this->assertSame($gitResult, $pmResult->hex);
    }

    #[Test]
    public function resolveHeadTildeTwoMatchesGit(): void
    {
        $gitResult = trim($this->git('rev-parse HEAD~2'));
        $pmResult = $this->repo->resolve('HEAD~2');

        $this->assertSame($gitResult, $pmResult->hex);
    }

    #[Test]
    public function resolveBranchNameMatchesGit(): void
    {
        $gitResult = trim($this->git('rev-parse feature'));
        $pmResult = $this->repo->resolve('feature');

        $this->assertSame($gitResult, $pmResult->hex);
    }

    #[Test]
    public function resolveMainBranchMatchesGit(): void
    {
        $defaultBranch = trim($this->git('branch --show-current'));
        $gitResult = trim($this->git('rev-parse ' . $defaultBranch));
        $pmResult = $this->repo->resolve($defaultBranch);

        $this->assertSame($gitResult, $pmResult->hex);
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
