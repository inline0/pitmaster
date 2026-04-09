<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Submodule\SubmoduleManager;

final class SubmoduleParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-submodule-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function listAndStatusMatchGitForAnInitializedSubmodule(): void
    {
        $depDir = $this->tmpDir . '/dep';
        $superDir = $this->tmpDir . '/super';
        $this->createRepository($depDir);
        file_put_contents($depDir . '/dep.txt', "dependency\n");
        $this->gitIn($depDir, 'add dep.txt');
        $this->gitIn($depDir, 'commit -m dep');

        $this->createRepository($superDir);
        $this->gitIn(
            $superDir,
            '-c protocol.file.allow=always submodule add ' . escapeshellarg($depDir) . ' vendor/lib',
        );
        $this->gitIn($superDir, 'commit -am "Add submodule"');

        $repo = Pitmaster::open($superDir);
        $manager = new SubmoduleManager(
            $repo->objectDatabase(),
            $repo->workDir(),
            $repo->gitDir(),
        );

        $submodules = $manager->list();
        $status = $manager->status($repo->head()->tree);

        $this->assertCount(1, $submodules);
        $this->assertSame('vendor/lib', $submodules[0]->name);
        $this->assertSame('vendor/lib', $submodules[0]->path);
        $this->assertSame($depDir, $submodules[0]->url);

        $gitStatus = trim($this->gitIn($superDir, 'submodule status --cached'));
        preg_match('/^[ +-]?([0-9a-f]{40})\s+(.+)$/', $gitStatus, $matches);
        $gitPath = preg_replace('/\s+\(.+\)$/', '', $matches[2] ?? '');

        $this->assertSame($matches[1], $status[0]['expected']);
        $this->assertSame(trim($this->gitIn($superDir . '/vendor/lib', 'rev-parse HEAD')), $status[0]['actual']);
        $this->assertFalse($status[0]['dirty']);
        $this->assertSame('vendor/lib', $gitPath);
    }

    #[Test]
    public function initAndUpdateMatchGitForAnUninitializedClone(): void
    {
        [$pitDir, $gitDir] = $this->createSubmoduleClones();
        $pitRepo = Pitmaster::open($pitDir);
        $manager = new SubmoduleManager(
            $pitRepo->objectDatabase(),
            $pitRepo->workDir(),
            $pitRepo->gitDir(),
        );

        $manager->init();
        $manager->update($pitRepo->head()->tree);
        $this->gitIn($gitDir, '-c protocol.file.allow=always submodule update --init');

        $this->assertSame(
            trim($this->gitIn($gitDir, 'submodule status --cached')),
            trim($this->gitIn($pitDir, 'submodule status --cached')),
        );
        $this->assertSame(
            trim($this->gitIn($gitDir . '/vendor/lib', 'rev-parse HEAD')),
            trim($this->gitIn($pitDir . '/vendor/lib', 'rev-parse HEAD')),
        );
        $this->assertFileExists($pitDir . '/.git/modules/vendor/lib/HEAD');
    }

    #[Test]
    public function initMatchesGitForConfigOnly(): void
    {
        [$pitDir, $gitDir] = $this->createSubmoduleClones();
        $pitRepo = Pitmaster::open($pitDir);
        $manager = new SubmoduleManager(
            $pitRepo->objectDatabase(),
            $pitRepo->workDir(),
            $pitRepo->gitDir(),
        );

        $manager->init();
        $this->gitIn($gitDir, 'submodule init');

        $this->assertSame(
            trim($this->gitIn($gitDir, "config --file .git/config --get-regexp '^submodule\\.'")),
            trim($this->gitIn($pitDir, "config --file .git/config --get-regexp '^submodule\\.'")),
        );
        $this->assertSame(
            is_file($gitDir . '/vendor/lib/.git'),
            is_file($pitDir . '/vendor/lib/.git'),
        );
    }

    #[Test]
    public function updateLeavesSubmoduleDetachedAtPinnedCommitLikeGit(): void
    {
        [$pitDir, $gitDir] = $this->createSubmoduleClones();
        $pitRepo = Pitmaster::open($pitDir);
        $manager = new SubmoduleManager(
            $pitRepo->objectDatabase(),
            $pitRepo->workDir(),
            $pitRepo->gitDir(),
        );

        $manager->init();
        $manager->update($pitRepo->head()->tree);
        $this->gitIn($gitDir, '-c protocol.file.allow=always submodule update --init');

        $this->assertSame(
            trim($this->gitIn($gitDir . '/vendor/lib', 'rev-parse --symbolic-full-name HEAD')),
            trim($this->gitIn($pitDir . '/vendor/lib', 'rev-parse --symbolic-full-name HEAD')),
        );
        $this->assertSame(
            trim($this->gitIn($gitDir . '/vendor/lib', 'branch --show-current')),
            trim($this->gitIn($pitDir . '/vendor/lib', 'branch --show-current')),
        );
    }

    private function createRepository(string $path): void
    {
        mkdir($path, 0777, true);
        $this->gitIn($path, 'init --initial-branch=main');
        $this->gitIn($path, 'config user.email test@pitmaster.dev');
        $this->gitIn($path, 'config user.name "Test User"');
    }

    /**
     * @return array{string, string}
     */
    private function createSubmoduleClones(): array
    {
        $depDir = $this->tmpDir . '/dep-clone-source';
        $superSource = $this->tmpDir . '/super-source';
        $pitDir = $this->tmpDir . '/pit-clone';
        $gitDir = $this->tmpDir . '/git-clone';

        $this->createRepository($depDir);
        file_put_contents($depDir . '/dep.txt', "dependency\n");
        $this->gitIn($depDir, 'add dep.txt');
        $this->gitIn($depDir, 'commit -m dep');

        $this->createRepository($superSource);
        $this->gitIn(
            $superSource,
            '-c protocol.file.allow=always submodule add ' . escapeshellarg($depDir) . ' vendor/lib',
        );
        $this->gitIn($superSource, 'commit -am "Add submodule"');

        $this->gitIn($this->tmpDir, 'clone ' . escapeshellarg($superSource) . ' ' . escapeshellarg($pitDir));
        $this->gitIn($this->tmpDir, 'clone ' . escapeshellarg($superSource) . ' ' . escapeshellarg($gitDir));

        return [$pitDir, $gitDir];
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
