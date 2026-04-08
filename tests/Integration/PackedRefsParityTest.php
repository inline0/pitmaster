<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Tests\Support\Workspace;

final class PackedRefsParityTest extends TestCase
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
    public function packRefsMatchesGitForBranchesAndTags(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createRepoPair(function (string $dir): void {
            $this->writeFile($dir, 'a.txt', "base\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m base');
            $this->git($dir, 'branch feature');
            $this->git($dir, 'tag v1.0');
            $this->git($dir, 'tag -a v1.1 -m annotated');
        });

        $this->git($gitDir, 'pack-refs --all');
        Pitmaster::open($pitDir)->packRefs();

        $this->assertSame($this->packedRefs($gitDir), $this->packedRefs($pitDir));
        $this->assertSame(
            $this->git($gitDir, 'show-ref --head --dereference'),
            $this->git($pitDir, 'show-ref --head --dereference'),
        );
        $this->assertFileDoesNotExist($pitDir . '/.git/refs/heads/feature');
        $this->assertFileDoesNotExist($pitDir . '/.git/refs/tags/v1.0');
        $this->assertFileDoesNotExist($pitDir . '/.git/refs/tags/v1.1');
    }

    #[Test]
    public function deletingPackedRefsMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createRepoPair(function (string $dir): void {
            $this->writeFile($dir, 'a.txt', "base\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m base');
            $this->git($dir, 'branch feature');
            $this->git($dir, 'tag v1.0');
            $this->git($dir, 'tag -a v1.1 -m annotated');
        });

        $this->git($gitDir, 'pack-refs --all');
        Pitmaster::open($pitDir)->packRefs();

        $this->git($gitDir, 'branch -D feature');
        $this->git($gitDir, 'tag -d v1.0');
        $this->git($gitDir, 'tag -d v1.1');

        $repo = Pitmaster::open($pitDir);
        $repo->deleteBranch('feature');
        $repo->deleteTag('v1.0');
        $repo->deleteTag('v1.1');

        $this->assertSame($this->packedRefs($gitDir), $this->packedRefs($pitDir));
        $this->assertSame(
            $this->git($gitDir, 'show-ref --head --dereference'),
            $this->git($pitDir, 'show-ref --head --dereference'),
        );
    }

    #[Test]
    public function packRefsFromLinkedWorktreeUsesCommonGitDir(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir] = $this->createRepoPair(function (string $dir): void {
            $this->writeFile($dir, 'a.txt', "base\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m base');
            $this->git($dir, 'branch feature');
            $this->git($dir, 'tag v1.0');
        });

        $gitWorktree = $this->createDirectory('packed-refs-git-worktree-');
        $pitWorktree = $this->createDirectory('packed-refs-pit-worktree-');
        $this->git($gitDir, 'worktree add ' . escapeshellarg($gitWorktree) . ' feature');

        $mainRepo = Pitmaster::open($pitDir);
        $mainRepo->addWorktree($pitWorktree, 'feature', name: 'feature-worktree');

        $this->git($gitWorktree, 'pack-refs --all');
        $worktreeRepo = Pitmaster::open($pitWorktree);
        $worktreeRepo->packRefs();

        $this->assertSame($this->packedRefs($gitDir), $this->packedRefs($pitDir));
        $this->assertSame(
            $this->git($gitWorktree, 'show-ref --head --dereference'),
            $this->git($pitWorktree, 'show-ref --head --dereference'),
        );
        $this->assertSame($pitDir . '/.git', $worktreeRepo->commonGitDir());
        $this->assertNotSame($pitDir . '/.git', $worktreeRepo->gitDir());
        $this->assertFileDoesNotExist($worktreeRepo->gitDir() . '/packed-refs');
        $this->assertFileExists($worktreeRepo->commonGitDir() . '/packed-refs');
    }

    /**
     * @param callable(string): void $setup
     * @return array{git: string, pit: string}
     */
    private function createRepoPair(callable $setup): array
    {
        $baseDir = $this->createDirectory('packed-refs-base-');
        $gitDir = $this->createDirectory('packed-refs-git-');
        $pitDir = $this->createDirectory('packed-refs-pit-');

        $this->initRepo($baseDir);
        $setup($baseDir);
        $this->copyDirectory($baseDir, $gitDir);
        $this->copyDirectory($baseDir, $pitDir);

        return ['git' => $gitDir, 'pit' => $pitDir];
    }

    private function initRepo(string $dir): void
    {
        $this->git($dir, 'init -b main');
        $this->git($dir, 'config user.name "Pitmaster Test"');
        $this->git($dir, 'config user.email "pitmaster@example.com"');
    }

    private function packedRefs(string $dir): string
    {
        $path = $dir . '/.git/packed-refs';
        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }

    private function writeFile(string $dir, string $path, string $contents): void
    {
        $fullPath = $dir . '/' . $path;
        $parent = dirname($fullPath);

        if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
            throw new \RuntimeException("Failed to create directory: {$parent}");
        }

        file_put_contents($fullPath, $contents);
    }

    private function copyDirectory(string $from, string $to): void
    {
        exec(sprintf('cp -R %s/. %s', escapeshellarg($from), escapeshellarg($to)), $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException("Failed to copy {$from} to {$to}");
        }
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

    private function createDirectory(string $prefix): string
    {
        $path = Workspace::createDirectory($prefix);
        $this->paths[] = $path;

        return $path;
    }
}
