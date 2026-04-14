<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Hooks\HookRunner;
use Pitmaster\Pitmaster;

final class HookParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-hook-parity-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function postCheckoutMatchesGit(): void
    {
        $sourceDir = $this->tmpDir . '/checkout-source';
        $pitDir = $this->tmpDir . '/checkout-pit';
        $gitDir = $this->tmpDir . '/checkout-git';
        $this->createBranchingRepo($sourceDir);
        $this->copyRepo($sourceDir, $pitDir);
        $this->copyRepo($sourceDir, $gitDir);
        $hook = "#!/bin/sh\necho \"$1|$2|$3\" >> .hook-log\n";
        $this->installHook($pitDir, 'post-checkout', $hook);
        $this->installHook($gitDir, 'post-checkout', $hook);

        Pitmaster::open($pitDir)->checkout('feature');
        $this->git('checkout feature', $gitDir);

        $this->assertSame(
            file_get_contents($gitDir . '/.hook-log'),
            file_get_contents($pitDir . '/.hook-log'),
        );
    }

    #[Test]
    public function postMergeMatchesGit(): void
    {
        $sourceDir = $this->tmpDir . '/merge-source';
        $pitDir = $this->tmpDir . '/merge-pit';
        $gitDir = $this->tmpDir . '/merge-git';
        $this->createMergeRepo($sourceDir);
        $this->copyRepo($sourceDir, $pitDir);
        $this->copyRepo($sourceDir, $gitDir);
        $hook = "#!/bin/sh\necho \"$1\" >> .hook-log\n";
        $this->installHook($pitDir, 'post-merge', $hook);
        $this->installHook($gitDir, 'post-merge', $hook);

        Pitmaster::open($pitDir)->merge('feature');
        $this->git('merge feature', $gitDir);

        $this->assertSame(
            file_get_contents($gitDir . '/.hook-log'),
            file_get_contents($pitDir . '/.hook-log'),
        );
    }

    #[Test]
    public function preRebaseMatchesGit(): void
    {
        $sourceDir = $this->tmpDir . '/rebase-source';
        $pitDir = $this->tmpDir . '/rebase-pit';
        $gitDir = $this->tmpDir . '/rebase-git';
        $this->createRebaseRepo($sourceDir);
        $this->copyRepo($sourceDir, $pitDir);
        $this->copyRepo($sourceDir, $gitDir);
        $hook = "#!/bin/sh\necho \"$1|$2\" >> .hook-log\n";
        $this->installHook($pitDir, 'pre-rebase', $hook);
        $this->installHook($gitDir, 'pre-rebase', $hook);

        Pitmaster::open($pitDir)->rebase('main');
        $this->git('rebase main', $gitDir);

        $this->assertSame(
            file_get_contents($gitDir . '/.hook-log'),
            file_get_contents($pitDir . '/.hook-log'),
        );
    }

    #[Test]
    public function hooksCanBeDisabledForCommitAndCheckout(): void
    {
        $dir = $this->tmpDir . '/hookless-commit-checkout';
        $this->createBranchingRepo($dir);

        $commitHook = "#!/bin/sh\necho \"commit\" >> .hook-log\n";
        $checkoutHook = "#!/bin/sh\necho \"$1|$2|$3\" >> .hook-log\n";
        $this->installHook($dir, 'pre-commit', $commitHook);
        $this->installHook($dir, 'prepare-commit-msg', $commitHook);
        $this->installHook($dir, 'commit-msg', $commitHook);
        $this->installHook($dir, 'post-commit', $commitHook);
        $this->installHook($dir, 'post-checkout', $checkoutHook);

        $repo = Pitmaster::open($dir, ['hooks' => false]);
        file_put_contents($dir . '/extra.txt', "extra\n");
        $repo->add('extra.txt');
        $repo->commit('extra');
        $repo->checkout('feature');

        $this->assertFalse(file_exists($dir . '/.hook-log'));
    }

    #[Test]
    public function hooksCanBeDisabledForMergeAndRebase(): void
    {
        $mergeDir = $this->tmpDir . '/hookless-merge';
        $this->createMergeRepo($mergeDir);
        $this->installHook($mergeDir, 'post-merge', "#!/bin/sh\necho \"merge\" >> .hook-log\n");

        Pitmaster::open($mergeDir, ['hooks' => false])->merge('feature');

        $this->assertFalse(file_exists($mergeDir . '/.hook-log'));

        $rebaseDir = $this->tmpDir . '/hookless-rebase';
        $this->createRebaseRepo($rebaseDir);
        $this->installHook($rebaseDir, 'pre-rebase', "#!/bin/sh\necho \"$1|$2\" >> .hook-log\n");

        Pitmaster::open($rebaseDir, ['hooks' => false])->rebase('main');

        $this->assertFalse(file_exists($rebaseDir . '/.hook-log'));
    }

    private function createBranchingRepo(string $path): void
    {
        mkdir($path, 0777, true);
        $this->git('init --initial-branch=main', $path);
        $this->git('config user.email test@pitmaster.dev', $path);
        $this->git('config user.name "Test User"', $path);
        file_put_contents($path . '/tracked.txt', "base\n");
        $this->git('add tracked.txt', $path);
        $this->git('commit -m base', $path);
        $this->git('checkout -b feature', $path);
        file_put_contents($path . '/tracked.txt', "feature\n");
        $this->git('add tracked.txt', $path);
        $this->git('commit -m feature', $path);
        $this->git('checkout main', $path);
    }

    private function createMergeRepo(string $path): void
    {
        $this->createBranchingRepo($path);
        file_put_contents($path . '/main.txt', "main\n");
        $this->git('add main.txt', $path);
        $this->git('commit -m main', $path);
    }

    private function createRebaseRepo(string $path): void
    {
        mkdir($path, 0777, true);
        $this->git('init --initial-branch=main', $path);
        $this->git('config user.email test@pitmaster.dev', $path);
        $this->git('config user.name "Test User"', $path);
        file_put_contents($path . '/tracked.txt', "base\n");
        $this->git('add tracked.txt', $path);
        $this->git('commit -m base', $path);
        $this->git('checkout -b feature', $path);
        file_put_contents($path . '/feature.txt', "feature\n");
        $this->git('add feature.txt', $path);
        $this->git('commit -m feature', $path);
        $this->git('checkout main', $path);
        file_put_contents($path . '/main.txt', "main\n");
        $this->git('add main.txt', $path);
        $this->git('commit -m main', $path);
        $this->git('checkout feature', $path);
    }

    private function installHook(string $repoDir, string $hookName, string $content): void
    {
        (new HookRunner($repoDir . '/.git'))->install($hookName, $content);
    }

    private function copyRepo(string $source, string $target): void
    {
        exec(sprintf('cp -R %s %s', escapeshellarg($source), escapeshellarg($target)), $output, $exitCode);

        if ($exitCode !== 0) {
            self::fail("Failed to copy repository from {$source} to {$target}");
        }
    }

    private function git(string $command, string $dir): string
    {
        exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command), $output, $exitCode);
        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            self::fail("git {$command} failed:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }
}
