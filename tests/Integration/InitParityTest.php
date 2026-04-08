<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Tests\Support\Workspace;

final class InitParityTest extends TestCase
{
    /** @var array<int, string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            Workspace::remove($path);
        }
    }

    #[Test]
    public function initMatchesGitForRepoShapeAndDefaults(): void
    {
        $gitDir = $this->createDirectory('init-git-');
        $pitDir = $this->createDirectory('init-pit-');

        $this->git($gitDir, 'init -b main');
        Pitmaster::init($pitDir);

        $this->assertTrue(Pitmaster::isRepository($pitDir));
        $this->assertSame(
            file_get_contents($gitDir . '/.git/HEAD'),
            file_get_contents($pitDir . '/.git/HEAD'),
        );
        $this->assertSame(
            file_get_contents($gitDir . '/.git/description'),
            file_get_contents($pitDir . '/.git/description'),
        );
        $this->assertSame(
            file_get_contents($gitDir . '/.git/info/exclude'),
            file_get_contents($pitDir . '/.git/info/exclude'),
        );
        $this->assertSame($this->configSnapshot($gitDir), $this->configSnapshot($pitDir));
        $this->assertSame($this->layoutSnapshot($gitDir), $this->layoutSnapshot($pitDir));
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'fsck --full'), $this->git($pitDir, 'fsck --full'));
    }

    private function configSnapshot(string $dir): string
    {
        $lines = [];

        foreach (
            [
            'core.repositoryformatversion',
            'core.filemode',
            'core.bare',
            'core.ignorecase',
            'core.precomposeunicode',
            ] as $key
        ) {
            $value = trim($this->gitAllowFailure($dir, 'config --local --get ' . escapeshellarg($key)));

            if ($value !== '') {
                $lines[] = "{$key}={$value}";
            }
        }

        return implode("\n", $lines) . ($lines !== [] ? "\n" : '');
    }

    private function layoutSnapshot(string $dir): string
    {
        $lines = [];

        foreach (
            [
            '.git/HEAD',
            '.git/config',
            '.git/description',
            '.git/hooks',
            '.git/info',
            '.git/info/exclude',
            '.git/objects',
            '.git/objects/info',
            '.git/objects/pack',
            '.git/refs',
            '.git/refs/heads',
            '.git/refs/tags',
            ] as $relativePath
        ) {
            $fullPath = $dir . '/' . $relativePath;
            $type = is_dir($fullPath) ? 'dir' : (is_file($fullPath) ? 'file' : 'missing');
            $lines[] = "{$type} {$relativePath}";
        }

        return implode("\n", $lines) . "\n";
    }

    private function git(string $dir, string $command): string
    {
        exec(
            sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command),
            $output,
            $exitCode,
        );

        if ($exitCode !== 0) {
            throw new \RuntimeException("Git command failed: git {$command}\n" . implode("\n", $output));
        }

        return implode("\n", $output) . ($output !== [] ? "\n" : '');
    }

    private function gitAllowFailure(string $dir, string $command): string
    {
        exec(
            sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command),
            $output,
            $exitCode,
        );

        if ($exitCode !== 0) {
            return '';
        }

        return implode("\n", $output) . ($output !== [] ? "\n" : '');
    }

    private function createDirectory(string $prefix): string
    {
        $path = Workspace::createDirectory($prefix);
        $this->paths[] = $path;

        return $path;
    }
}
