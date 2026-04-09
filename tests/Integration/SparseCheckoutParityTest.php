<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Checkout\SparseCheckout;
use Pitmaster\Config\GitConfig;
use Pitmaster\Pitmaster;

final class SparseCheckoutParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-sparse-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function initAndSetMatchGitGeneratedSparseCheckoutState(): void
    {
        $gitDir = $this->tmpDir . '/git';
        $pitDir = $this->tmpDir . '/pit';
        $this->initRepo($gitDir);
        $this->initRepo($pitDir);

        $this->gitIn($gitDir, 'sparse-checkout init --cone');
        $this->gitIn($gitDir, 'sparse-checkout set src docs');

        $sparse = new SparseCheckout($pitDir . '/.git');
        $sparse->init();
        $sparse->set(['src', 'docs']);

        $this->assertTrue($sparse->isEnabled());
        $this->assertSame(
            file_get_contents($gitDir . '/.git/info/sparse-checkout'),
            file_get_contents($pitDir . '/.git/info/sparse-checkout'),
        );
        $this->assertSame(
            GitConfig::fromFile($gitDir . '/.git/config')->all(),
            GitConfig::fromFile($pitDir . '/.git/config')->all(),
        );
        $this->assertSame(
            GitConfig::fromFile($gitDir . '/.git/config.worktree')->all(),
            GitConfig::fromFile($pitDir . '/.git/config.worktree')->all(),
        );
        $this->assertSame(['docs', 'src'], $sparse->includedDirectories());
    }

    #[Test]
    public function disableMatchesGitStateAndLeavesPatternFileIntact(): void
    {
        $gitDir = $this->tmpDir . '/git-disable';
        $pitDir = $this->tmpDir . '/pit-disable';
        $this->initRepo($gitDir);
        $this->initRepo($pitDir);

        $this->gitIn($gitDir, 'sparse-checkout init --cone');
        $this->gitIn($gitDir, 'sparse-checkout set src docs');
        $this->gitIn($gitDir, 'sparse-checkout disable');

        $sparse = new SparseCheckout($pitDir . '/.git');
        $sparse->init();
        $sparse->set(['src', 'docs']);
        $sparse->disable();

        $this->assertFalse($sparse->isEnabled());
        $this->assertSame(
            file_get_contents($gitDir . '/.git/info/sparse-checkout'),
            file_get_contents($pitDir . '/.git/info/sparse-checkout'),
        );
        $this->assertSame(
            GitConfig::fromFile($gitDir . '/.git/config.worktree')->all(),
            GitConfig::fromFile($pitDir . '/.git/config.worktree')->all(),
        );
    }

    #[Test]
    public function resetAndStatusMatchGitUnderSparseCheckout(): void
    {
        $source = $this->tmpDir . '/source-reset';
        $pitDir = $this->tmpDir . '/pit-reset';
        $gitDir = $this->tmpDir . '/git-reset';
        $this->createSparseHistoryRepo($source);
        $this->copyRepo($source, $pitDir);
        $this->copyRepo($source, $gitDir);

        $pitRepo = Pitmaster::open($pitDir);
        $sparse = new SparseCheckout($pitDir . '/.git');
        $sparse->init();
        $sparse->set(['src']);
        $pitRepo->reset('HEAD', 'hard');

        $this->gitIn($gitDir, 'sparse-checkout init --cone');
        $this->gitIn($gitDir, 'sparse-checkout set src');
        $this->gitIn($gitDir, 'reset --hard HEAD');

        $this->assertFileExists($pitDir . '/README.md');
        $this->assertFileExists($pitDir . '/src/app.txt');
        $this->assertFileDoesNotExist($pitDir . '/docs/guide.txt');
        $this->assertSame(
            $this->gitIn($gitDir, 'status --porcelain=v2'),
            $this->gitIn($pitDir, 'status --porcelain=v2'),
        );
        $this->assertSame(
            file_get_contents($gitDir . '/src/app.txt'),
            file_get_contents($pitDir . '/src/app.txt'),
        );
    }

    #[Test]
    public function mergeMatchesGitUnderSparseCheckout(): void
    {
        $source = $this->tmpDir . '/source-merge';
        $pitDir = $this->tmpDir . '/pit-merge';
        $gitDir = $this->tmpDir . '/git-merge';
        $this->createSparseHistoryRepo($source, withFeature: true);
        $this->copyRepo($source, $pitDir);
        $this->copyRepo($source, $gitDir);

        $pitRepo = Pitmaster::open($pitDir);
        $sparse = new SparseCheckout($pitDir . '/.git');
        $sparse->init();
        $sparse->set(['src']);
        $pitRepo->reset('HEAD', 'hard');
        $pitRepo->merge('feature');

        $this->gitIn($gitDir, 'sparse-checkout init --cone');
        $this->gitIn($gitDir, 'sparse-checkout set src');
        $this->gitIn($gitDir, 'reset --hard HEAD');
        $this->gitIn($gitDir, 'merge feature');

        $this->assertFileExists($pitDir . '/src/app.txt');
        $this->assertFileDoesNotExist($pitDir . '/docs/guide.txt');
        $this->assertSame(
            $this->gitIn($gitDir, 'status --porcelain=v2'),
            $this->gitIn($pitDir, 'status --porcelain=v2'),
        );
        $this->assertSame(
            $this->gitIn($gitDir, 'show HEAD:docs/guide.txt'),
            $this->gitIn($pitDir, 'show HEAD:docs/guide.txt'),
        );
        $this->assertSame(
            file_get_contents($gitDir . '/src/app.txt'),
            file_get_contents($pitDir . '/src/app.txt'),
        );
    }

    private function initRepo(string $path): void
    {
        mkdir($path, 0777, true);
        $this->gitIn($path, 'init --initial-branch=main');
    }

    private function createSparseHistoryRepo(string $path, bool $withFeature = false): void
    {
        $this->initRepo($path);
        $this->gitIn($path, 'config user.email test@pitmaster.dev');
        $this->gitIn($path, 'config user.name "Test User"');
        $this->writeFile($path, 'README.md', "root\n");
        $this->writeFile($path, 'src/app.txt', "src v1\n");
        $this->writeFile($path, 'docs/guide.txt', "docs v1\n");
        $this->gitIn($path, 'add .');
        $this->gitIn($path, 'commit -m initial');
        $this->writeFile($path, 'src/app.txt', "src v2\n");
        $this->writeFile($path, 'docs/guide.txt', "docs v2\n");
        $this->gitIn($path, 'add .');
        $this->gitIn($path, 'commit -m update-main');

        if (!$withFeature) {
            return;
        }

        $this->gitIn($path, 'reset --hard HEAD~1');
        $this->gitIn($path, 'checkout -b feature');
        $this->writeFile($path, 'src/app.txt', "src feature\n");
        $this->writeFile($path, 'docs/guide.txt', "docs feature\n");
        $this->gitIn($path, 'add .');
        $this->gitIn($path, 'commit -m feature-update');
        $this->gitIn($path, 'checkout main');
    }

    private function copyRepo(string $source, string $target): void
    {
        exec(sprintf('cp -R %s %s', escapeshellarg($source), escapeshellarg($target)), $output, $exitCode);

        if ($exitCode !== 0) {
            $this->fail("Failed to copy repository from {$source} to {$target}");
        }
    }

    private function writeFile(string $repoPath, string $path, string $content): void
    {
        $fullPath = $repoPath . '/' . $path;
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($fullPath, $content);
    }

    private function gitIn(string $dir, string $command): string
    {
        exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command), $output, $exitCode);
        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->fail("git {$command} failed:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }
}
