<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Repository;
use Pitmaster\Worktree\Worktree;
use Pitmaster\Worktree\WorktreeManager;

final class WorktreeTopologyTest extends TestCase
{
    private string $tmpDir;
    private string $gitDir;
    private Repository $repo;

    protected function setUp(): void
    {
        $tmpRoot = realpath(sys_get_temp_dir()) ?: sys_get_temp_dir();
        $this->tmpDir = $tmpRoot . '/pitmaster-worktree-topology-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');

        $this->writeFile('README.md', "base\n");
        $this->git('add README.md');
        $this->git('commit -m "Initial commit"');
        $this->git('branch feature');

        $this->gitDir = $this->tmpDir . '/.git';
        $this->repo = Pitmaster::open($this->tmpDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
        exec('rm -rf ' . escapeshellarg($this->tmpDir . '-app'));
        exec('rm -rf ' . escapeshellarg($this->tmpDir . '-sandbox'));
        exec('rm -rf ' . escapeshellarg($this->tmpDir . '-broken'));
        exec('rm -rf ' . escapeshellarg($this->tmpDir . '-theme'));
        exec('rm -rf ' . escapeshellarg($this->tmpDir . '-plugin'));
    }

    #[Test]
    public function nestedConsumerThemeWorktreeIsUsableAfterReopen(): void
    {
        $appRoot = $this->tmpDir . '-app';
        $themePath = $appRoot . '/wp-content/themes/divine-child';

        $this->repo->addWorktree($themePath, 'feature', name: 'app-theme-divine-child');

        $linkedRepo = Pitmaster::open($themePath);

        $this->assertLinkedWorktree(
            $linkedRepo,
            $themePath,
            'app-theme-divine-child',
            'feature',
        );
        $this->assertSame('', trim($this->git('status --short', $themePath)));

        $this->writeFileAt($themePath, 'feature.txt', "from linked worktree\n");
        $linkedRepo->add('feature.txt');
        $commitId = $linkedRepo->commit("Feature work\n");

        $reopenedMain = Pitmaster::open($this->tmpDir);

        $this->assertSame($commitId->hex, $reopenedMain->branch('feature'));
        $this->assertSame('', trim($this->git('status --short', $themePath)));
        $this->assertSame("from linked worktree\n", file_get_contents($themePath . '/feature.txt'));
    }

    #[Test]
    public function linkedWorktreeHandleCanCreateSiblingWorktreeFromNestedConsumerPath(): void
    {
        $appRoot = $this->tmpDir . '-app';
        $sandboxRoot = $this->tmpDir . '-sandbox';
        $appPath = $appRoot . '/wp-content/themes/divine-child';
        $sandboxPath = $sandboxRoot . '/wp-content/themes/divine-child';

        $this->repo->addWorktree($appPath, 'feature', name: 'app-theme-divine-child');

        $linkedRepo = Pitmaster::open($appPath);
        $linkedRepo->addWorktree(
            $sandboxPath,
            'sandbox-feature',
            $linkedRepo->resolve('HEAD'),
            name: 'sandbox-theme-divine-child',
        );

        $this->assertLinkedWorktree(
            $linkedRepo,
            $appPath,
            'app-theme-divine-child',
            'feature',
        );

        $sandboxRepo = Pitmaster::open($sandboxPath);

        $this->assertLinkedWorktree(
            $sandboxRepo,
            $sandboxPath,
            'sandbox-theme-divine-child',
            'sandbox-feature',
        );

        $linkedNames = $this->linkedWorktreeNames(Pitmaster::open($this->tmpDir));

        $this->assertSame(
            ['app-theme-divine-child', 'sandbox-theme-divine-child'],
            $linkedNames,
        );
    }

    #[Test]
    public function failedWorktreeCreationDoesNotLeavePartialGitIndirection(): void
    {
        $root = $this->tmpDir . '-broken';
        $pluginPath = $root . '/wp-content/plugins/divine-child';
        $manager = new WorktreeManager($this->gitDir, $this->tmpDir);

        try {
            $manager->add($pluginPath, 'does-not-exist', 'broken-divine-child');
            $this->fail('Expected worktree creation to fail');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Cannot resolve', $e->getMessage());
        }

        $this->assertDirectoryDoesNotExist($this->gitDir . '/worktrees/broken-divine-child');
        $this->assertFileDoesNotExist($pluginPath . '/.git');
        $this->assertFalse(Pitmaster::isRepository($pluginPath));
    }

    #[Test]
    public function reopenedRepositoryCanRoundTripListAndRemoveConsumerStyleWorktrees(): void
    {
        $themeRoot = $this->tmpDir . '-theme';
        $pluginRoot = $this->tmpDir . '-plugin';
        $themePath = $themeRoot . '/wp-content/themes/divine-child';
        $pluginPath = $pluginRoot . '/wp-content/plugins/divine-child';

        $this->repo->addWorktree($themePath, 'feature', name: 'theme-divine-child');
        $this->repo->addWorktree(
            $pluginPath,
            'plugin-feature',
            $this->repo->resolve('HEAD'),
            name: 'plugin-divine-child',
        );

        $reopened = Pitmaster::open($this->tmpDir);
        $worktreesByName = [];

        foreach ($reopened->worktrees() as $worktree) {
            if (!$worktree->isMain && $worktree->name !== null) {
                $worktreesByName[$worktree->name] = $worktree->path;
            }
        }

        ksort($worktreesByName);

        $this->assertSame(
            [
                'plugin-divine-child' => $pluginPath,
                'theme-divine-child' => $themePath,
            ],
            $worktreesByName,
        );

        $reopened->removeWorktree($themePath);
        $reopened->removeWorktree('plugin-divine-child');

        $this->assertDirectoryDoesNotExist($this->gitDir . '/worktrees/theme-divine-child');
        $this->assertDirectoryDoesNotExist($this->gitDir . '/worktrees/plugin-divine-child');
        $this->assertFalse(Pitmaster::isRepository($themePath));
        $this->assertFalse(Pitmaster::isRepository($pluginPath));

        $remaining = array_filter(
            Pitmaster::open($this->tmpDir)->worktrees(),
            static fn (Worktree $worktree): bool => !$worktree->isMain,
        );

        $this->assertCount(0, $remaining);
    }

    private function assertLinkedWorktree(
        Repository $repo,
        string $path,
        string $name,
        string $branch,
    ): void {
        $this->assertTrue($repo->isLinkedWorktree());
        $this->assertSame($path, $repo->workDir());
        $this->assertSame($this->gitDir, $repo->commonGitDir());
        $this->assertSame($this->gitDir . '/worktrees/' . $name, $repo->gitDir());
        $this->assertSame($branch, $repo->branch());
        $this->assertFileExists($path . '/.git');
    }

    /**
     * @return array<int, string>
     */
    private function linkedWorktreeNames(Repository $repo): array
    {
        $names = [];

        foreach ($repo->worktrees() as $worktree) {
            if (!$worktree->isMain && $worktree->name !== null) {
                $names[] = $worktree->name;
            }
        }

        sort($names);

        return $names;
    }

    private function git(string $cmd, ?string $cwd = null): string
    {
        $cwd = $cwd ?? $this->tmpDir;

        return shell_exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($cwd), $cmd)) ?? '';
    }

    private function writeFile(string $path, string $content): void
    {
        $this->writeFileAt($this->tmpDir, $path, $content);
    }

    private function writeFileAt(string $root, string $path, string $content): void
    {
        $full = $root . '/' . $path;
        $dir = dirname($full);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($full, $content);
    }
}
