<?php

declare(strict_types=1);

namespace Pitmaster\Worktree;

use Pitmaster\Object\ObjectId;
use Pitmaster\Ref\RefDatabase;
use Pitmaster\Ref\SymbolicRef;

/**
 * Manages multiple working trees sharing a single repository.
 *
 * Git worktrees use a .git file (not directory) in linked worktrees that
 * contains "gitdir: <path to $GIT_DIR/worktrees/<name>>" as indirection.
 * The main worktree has the actual .git directory.
 *
 * Structure:
 *   $GIT_DIR/worktrees/<name>/
 *     gitdir    - path back to the linked .git file
 *     HEAD      - detached or symbolic ref for this worktree
 *     commondir - relative path to the shared git dir (usually "../..")
 *     locked    - present if worktree is locked (optional, may contain reason)
 */
final class WorktreeManager
{
    public function __construct(
        private readonly string $gitDir,
        private readonly string $workDir,
    ) {
    }

    /**
     * List all worktrees (main + linked).
     *
     * @return array<int, Worktree>
     */
    public function list(): array
    {
        $worktrees = [];

        // Main worktree
        $worktrees[] = $this->mainWorktree();

        // Linked worktrees
        $worktreesDir = $this->gitDir . '/worktrees';

        if (!is_dir($worktreesDir)) {
            return $worktrees;
        }

        foreach (scandir($worktreesDir) as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $wtDir = $worktreesDir . '/' . $name;

            if (!is_dir($wtDir)) {
                continue;
            }

            $worktrees[] = $this->readLinkedWorktree($name, $wtDir);
        }

        return $worktrees;
    }

    /**
     * Add a new linked worktree.
     */
    public function add(string $path, string $branchOrCommit): Worktree
    {
        $name = basename($path);
        $wtDir = $this->gitDir . '/worktrees/' . $name;

        if (is_dir($wtDir)) {
            throw new \RuntimeException("Worktree already exists: {$name}");
        }

        // Create worktree git metadata dir
        mkdir($wtDir, 0777, true);

        // Create the worktree directory
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        // Write .git file in the worktree (not a directory)
        $relativeGitDir = $this->relativePath($path, $wtDir);
        file_put_contents($path . '/.git', "gitdir: {$relativeGitDir}\n");

        // Write gitdir file in the worktree metadata (points back)
        file_put_contents($wtDir . '/gitdir', $path . '/.git' . "\n");

        // Write commondir (relative path to shared git dir)
        file_put_contents($wtDir . '/commondir', "../..\n");

        // Set HEAD for the worktree
        $refs = new RefDatabase($this->gitDir);
        $branchRef = "refs/heads/{$branchOrCommit}";
        $branchId = $refs->resolve($branchRef);

        if ($branchId !== null) {
            file_put_contents($wtDir . '/HEAD', "ref: {$branchRef}\n");
        } else {
            // Detached HEAD
            $id = $refs->resolve($branchOrCommit);

            if ($id === null) {
                throw new \RuntimeException("Cannot resolve: {$branchOrCommit}");
            }

            file_put_contents($wtDir . '/HEAD', $id->hex . "\n");
        }

        return $this->readLinkedWorktree($name, $wtDir);
    }

    /**
     * Remove a linked worktree.
     */
    public function remove(string $name, bool $force = false): void
    {
        $wtDir = $this->gitDir . '/worktrees/' . $name;

        if (!is_dir($wtDir)) {
            throw new \RuntimeException("Worktree not found: {$name}");
        }

        // Check if locked
        if (!$force && is_file($wtDir . '/locked')) {
            $reason = trim((string) file_get_contents($wtDir . '/locked'));
            throw new \RuntimeException("Worktree is locked: {$name}" . ($reason !== '' ? " ({$reason})" : ''));
        }

        // Read gitdir to find the worktree path
        $gitdirFile = $wtDir . '/gitdir';

        if (is_file($gitdirFile)) {
            $gitdirContent = trim((string) file_get_contents($gitdirFile));
            $wtPath = dirname($gitdirContent);

            // Remove the .git file in the worktree
            if (is_file($gitdirContent)) {
                unlink($gitdirContent);
            }
        }

        // Remove the worktree metadata
        $this->removeDir($wtDir);
    }

    /**
     * Lock a worktree.
     */
    public function lock(string $name, string $reason = ''): void
    {
        $wtDir = $this->gitDir . '/worktrees/' . $name;

        if (!is_dir($wtDir)) {
            throw new \RuntimeException("Worktree not found: {$name}");
        }

        file_put_contents($wtDir . '/locked', $reason);
    }

    /**
     * Unlock a worktree.
     */
    public function unlock(string $name): void
    {
        $lockFile = $this->gitDir . '/worktrees/' . $name . '/locked';

        if (is_file($lockFile)) {
            unlink($lockFile);
        }
    }

    /**
     * Resolve a .git file to the actual git directory.
     * Used when opening a linked worktree.
     */
    public static function resolveGitFile(string $path): ?string
    {
        $gitFile = $path . '/.git';

        if (!is_file($gitFile)) {
            return null;
        }

        $content = trim((string) file_get_contents($gitFile));

        if (!str_starts_with($content, 'gitdir: ')) {
            return null;
        }

        $gitdir = substr($content, 8);

        // Resolve relative path
        if (!str_starts_with($gitdir, '/')) {
            $gitdir = $path . '/' . $gitdir;
        }

        return realpath($gitdir) ?: $gitdir;
    }

    private function mainWorktree(): Worktree
    {
        $refs = new RefDatabase($this->gitDir);
        $head = $refs->readHead();
        $headId = $refs->resolveHead();

        $branch = null;
        $isDetached = true;

        if ($head !== null && str_starts_with($head->target, 'refs/heads/')) {
            $branch = substr($head->target, 11);
            $isDetached = false;
        }

        return new Worktree(
            path: $this->workDir,
            gitDir: $this->gitDir,
            branch: $branch,
            head: $headId,
            isMain: true,
            isDetached: $isDetached,
            isLocked: false,
            lockReason: null,
        );
    }

    private function readLinkedWorktree(string $name, string $wtDir): Worktree
    {
        // Read HEAD
        $headContent = is_file($wtDir . '/HEAD') ? trim((string) file_get_contents($wtDir . '/HEAD')) : '';
        $branch = null;
        $headId = null;
        $isDetached = true;

        $symref = SymbolicRef::parse('HEAD', $headContent . "\n");

        if ($symref !== null && str_starts_with($symref->target, 'refs/heads/')) {
            $branch = substr($symref->target, 11);
            $isDetached = false;

            // Resolve the branch from the shared refs
            $refs = new RefDatabase($this->gitDir);
            $headId = $refs->resolve($symref->target);
        } elseif (strlen($headContent) === 40 && ctype_xdigit($headContent)) {
            $headId = ObjectId::fromHex($headContent);
        }

        // Read gitdir to find worktree path
        $path = '';

        if (is_file($wtDir . '/gitdir')) {
            $gitdirContent = trim((string) file_get_contents($wtDir . '/gitdir'));
            $path = dirname($gitdirContent);
        }

        // Check lock
        $isLocked = is_file($wtDir . '/locked');
        $lockReason = $isLocked ? trim((string) file_get_contents($wtDir . '/locked')) : null;

        return new Worktree(
            path: $path,
            gitDir: $wtDir,
            branch: $branch,
            head: $headId,
            isMain: false,
            isDetached: $isDetached,
            isLocked: $isLocked,
            lockReason: $lockReason ?: null,
        );
    }

    private function relativePath(string $from, string $to): string
    {
        $fromParts = explode('/', realpath($from) ?: $from);
        $toParts = explode('/', realpath($to) ?: $to);

        $common = 0;

        while ($common < count($fromParts) && $common < count($toParts) && $fromParts[$common] === $toParts[$common]) {
            $common++;
        }

        $ups = count($fromParts) - $common;

        return str_repeat('../', $ups) . implode('/', array_slice($toParts, $common));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;

            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
