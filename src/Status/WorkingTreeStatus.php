<?php

declare(strict_types=1);

namespace Pitmaster\Status;

use Pitmaster\Checkout\SparseCheckout;
use Pitmaster\Index\Index;
use Pitmaster\Index\IndexEntry;
use Pitmaster\Object\Blob;
use Pitmaster\Object\Commit;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;
use Pitmaster\Object\Tree;
use Pitmaster\Storage\ObjectDatabase;

/**
 * Compare HEAD vs index vs worktree.
 *
 * Produces StatusEntry[] describing the state of each file.
 */
final class WorkingTreeStatus
{
    public function __construct(
        private readonly ObjectDatabase $objects,
        private readonly string $workDir,
        private readonly ?string $gitDir = null,
    ) {
    }

    /**
     * Compute full status.
     *
     * @return array<int, StatusEntry>
     */
    public function compute(Index $index, ?ObjectId $headCommitId): array
    {
        $entries = [];
        $sparse = $this->gitDir !== null ? new SparseCheckout($this->gitDir) : null;

        // Build HEAD tree map: path => hash
        $headTree = [];

        if ($headCommitId !== null) {
            $commit = $this->objects->read($headCommitId);

            if ($commit instanceof Commit) {
                $headTree = $this->flattenTree($commit->tree);
            }
        }

        // Build index map: path => entry
        $indexEntries = $index->entries();
        $unmergedPaths = array_flip($index->unmergedPaths());

        // Build worktree file list
        $ignore = GitIgnore::forRepo($this->workDir);
        $worktreeFiles = $this->scanWorktree($this->workDir, '', $ignore);

        // All known paths
        $allPaths = array_unique(array_merge(
            array_keys($headTree),
            array_keys($indexEntries),
            $worktreeFiles,
        ));
        sort($allPaths);

        foreach ($allPaths as $path) {
            if (isset($unmergedPaths[$path])) {
                $entries[] = new StatusEntry($path, FileStatus::Unmerged, FileStatus::Unmerged);
                continue;
            }

            $inHead = isset($headTree[$path]);
            $inIndex = isset($indexEntries[$path]);
            $inWorktree = in_array($path, $worktreeFiles, true);
            $sparseExcluded = $sparse !== null && $sparse->isEnabled() && !$sparse->includes($path);

            // Determine index status (HEAD vs index)
            $indexStatus = FileStatus::Unmodified;

            if (!$inHead && $inIndex) {
                $indexStatus = FileStatus::Added;
            } elseif ($inHead && !$inIndex) {
                $indexStatus = FileStatus::Deleted;
            } elseif ($inHead && $inIndex) {
                if ($headTree[$path] !== $indexEntries[$path]->hash->hex) {
                    $indexStatus = FileStatus::Modified;
                }
            }

            // Determine worktree status (index vs worktree)
            $worktreeStatus = FileStatus::Unmodified;

            if ($inIndex && !$inWorktree && !$sparseExcluded) {
                $worktreeStatus = FileStatus::Deleted;
            } elseif ($inIndex && !$sparseExcluded) {
                if ($this->worktreeFileChanged($indexEntries[$path], $path)) {
                    $worktreeStatus = FileStatus::Modified;
                }
            } elseif (!$inHead && $inWorktree) {
                // Untracked
                $entries[] = new StatusEntry($path, FileStatus::Untracked, FileStatus::Untracked);
                continue;
            } elseif ($inWorktree && !$sparseExcluded) {
                $worktreeStatus = FileStatus::Modified;
            }

            // Only include if there's something to report
            if ($indexStatus !== FileStatus::Unmodified || $worktreeStatus !== FileStatus::Unmodified) {
                $entries[] = new StatusEntry($path, $indexStatus, $worktreeStatus);
            }
        }

        return $entries;
    }

    /**
     * Flatten a tree object into path => hex hash map (recursive).
     *
     * @return array<string, string>
     */
    private function flattenTree(ObjectId $treeId, string $prefix = ''): array
    {
        $result = [];
        $tree = $this->objects->read($treeId);

        if (!$tree instanceof Tree) {
            return $result;
        }

        foreach ($tree->entries as $entry) {
            $fullPath = $prefix !== '' ? $prefix . '/' . $entry->name : $entry->name;

            if ($entry->isTree()) {
                $result = array_merge($result, $this->flattenTree($entry->hash, $fullPath));
            } else {
                $result[$fullPath] = $entry->hash->hex;
            }
        }

        return $result;
    }

    /**
     * Check if a worktree file differs from its index entry.
     */
    private function worktreeFileChanged(IndexEntry $entry, string $path): bool
    {
        $fullPath = $this->workDir . '/' . $path;

        if (!is_file($fullPath)) {
            return true;
        }

        // Quick check: size mismatch means definitely changed
        $size = filesize($fullPath);

        if ($size !== false && $size !== $entry->fileSize) {
            return true;
        }

        // Always verify by hashing content. The mtime-based shortcut is unreliable
        // when writes happen in the same second (racily clean entries). Git handles
        // this with smudge/clean filters and nanosecond timestamps; we just hash.
        $content = file_get_contents($fullPath);

        if ($content === false) {
            return true;
        }

        $contentHash = ObjectId::compute(ObjectType::Blob, $content);

        return !$contentHash->equals($entry->hash);
    }

    /**
     * Recursively scan worktree for files (respecting .gitignore).
     *
     * @return array<int, string>
     */
    private function scanWorktree(string $dir, string $prefix, GitIgnore $ignore): array
    {
        $files = [];
        $entries = scandir($dir);

        if ($entries === false) {
            return $files;
        }

        foreach ($entries as $name) {
            if ($name === '.' || $name === '..' || $name === '.git') {
                continue;
            }

            $fullPath = $dir . '/' . $name;
            $relPath = $prefix !== '' ? $prefix . '/' . $name : $name;

            if ($ignore->isIgnored($relPath, is_dir($fullPath))) {
                continue;
            }

            if (is_dir($fullPath)) {
                $files = array_merge($files, $this->scanWorktree($fullPath, $relPath, $ignore));
            } elseif (is_file($fullPath)) {
                $files[] = $relPath;
            }
        }

        return $files;
    }
}
