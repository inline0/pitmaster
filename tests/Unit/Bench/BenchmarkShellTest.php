<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Bench;

use PHPUnit\Framework\TestCase;
use Pitmaster\Bench\BenchmarkShell;

final class BenchmarkShellTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-bench-shell-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    public function testGitCommandsDoNotInheritAmbientObjectDirectory(): void
    {
        $repo = $this->tmpDir . '/repo';
        $ambientObjectDirectory = $this->tmpDir . '/ambient-objects';
        mkdir($ambientObjectDirectory, 0777, true);

        $previousServer = $_SERVER['GIT_OBJECT_DIRECTORY'] ?? null;
        $previousEnv = $_ENV['GIT_OBJECT_DIRECTORY'] ?? null;

        $_SERVER['GIT_OBJECT_DIRECTORY'] = $ambientObjectDirectory;
        $_ENV['GIT_OBJECT_DIRECTORY'] = $ambientObjectDirectory;

        try {
            BenchmarkShell::git('init --initial-branch=main ' . escapeshellarg($repo), $this->tmpDir);
            BenchmarkShell::git('config user.name ' . escapeshellarg('Pitmaster Bench'), $repo);
            BenchmarkShell::git('config user.email ' . escapeshellarg('bench@pitmaster.dev'), $repo);

            file_put_contents($repo . '/file.txt', "content\n");

            BenchmarkShell::git('add -A', $repo);
            BenchmarkShell::git('commit -m ' . escapeshellarg('Initial'), $repo);
        } finally {
            $this->restoreEnvironmentValue('GIT_OBJECT_DIRECTORY', $previousServer, $previousEnv);
        }

        self::assertSame("commit\n", BenchmarkShell::git('cat-file -t HEAD', $repo));
        self::assertDirectoryExists($repo . '/.git/objects');
        self::assertSame([], array_values(array_diff(scandir($ambientObjectDirectory) ?: [], ['.', '..'])));
    }

    public function testGitCommandsDisableBackgroundMaintenance(): void
    {
        $repo = $this->tmpDir . '/maintenance-repo';
        BenchmarkShell::git('init --initial-branch=main ' . escapeshellarg($repo), $this->tmpDir);

        self::assertSame("false\n", BenchmarkShell::git('config maintenance.auto', $repo));
        self::assertSame("false\n", BenchmarkShell::git('config gc.autoDetach', $repo));
    }

    private function restoreEnvironmentValue(string $name, ?string $serverValue, ?string $envValue): void
    {
        if ($serverValue === null) {
            unset($_SERVER[$name]);
        } else {
            $_SERVER[$name] = $serverValue;
        }

        if ($envValue === null) {
            unset($_ENV[$name]);
        } else {
            $_ENV[$name] = $envValue;
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }
}
