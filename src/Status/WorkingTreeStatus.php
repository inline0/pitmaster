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
    /** @var array<string, array<string, string>> */
    private array $treeCache = [];

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
        $sparseEnabled = $sparse !== null && $sparse->isEnabled();

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
        $worktreeFileSet = array_fill_keys($worktreeFiles, true);

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
            $inWorktree = isset($worktreeFileSet[$path]);
            $sparseExcluded = $sparseEnabled && !$sparse->includes($path);

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

        return $this->detectIndexRenames($entries, $headTree, $indexEntries);
    }

    /**
     * Flatten a tree object into path => hex hash map (recursive).
     *
     * @return array<string, string>
     */
    private function flattenTree(ObjectId $treeId, string $prefix = ''): array
    {
        $result = [];
        $this->flattenTreeInto($treeId, $prefix, $result);

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

        $contentHash = ObjectId::compute(ObjectType::Blob, $content, $entry->hash->algo);

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
        $this->scanWorktreeInto($dir, $prefix, $ignore, $files);

        return $files;
    }

    /**
     * @param array<string, string> $result
     */
    private function flattenTreeInto(ObjectId $treeId, string $prefix, array &$result): void
    {
        $cacheKey = $prefix . "\0" . $treeId->hex;

        if (isset($this->treeCache[$cacheKey])) {
            $result += $this->treeCache[$cacheKey];

            return;
        }

        $tree = $this->objects->read($treeId);

        if (!$tree instanceof Tree) {
            return;
        }

        $local = [];

        foreach ($tree->entries as $entry) {
            $fullPath = $prefix !== '' ? $prefix . '/' . $entry->name : $entry->name;

            if ($entry->isTree()) {
                $this->flattenTreeInto($entry->hash, $fullPath, $local);
            } else {
                $local[$fullPath] = $entry->hash->hex;
            }
        }

        $this->treeCache[$cacheKey] = $local;
        $result += $local;
    }

    /**
     * @param list<string> $files
     */
    private function scanWorktreeInto(string $dir, string $prefix, GitIgnore $ignore, array &$files): void
    {
        $entries = scandir($dir);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $name) {
            if ($name === '.' || $name === '..' || $name === '.git') {
                continue;
            }

            $fullPath = $dir . '/' . $name;
            $isDirectory = is_dir($fullPath);
            $relPath = $prefix !== '' ? $prefix . '/' . $name : $name;

            if ($ignore->isIgnored($relPath, $isDirectory)) {
                continue;
            }

            if ($isDirectory) {
                $this->scanWorktreeInto($fullPath, $relPath, $ignore, $files);
            } elseif (is_file($fullPath)) {
                $files[] = $relPath;
            }
        }
    }

    /**
     * @param array<int, StatusEntry> $entries
     * @param array<string, string> $headTree
     * @param array<string, IndexEntry> $indexEntries
     * @return array<int, StatusEntry>
     */
    private function detectIndexRenames(array $entries, array $headTree, array $indexEntries): array
    {
        $deleted = [];
        $added = [];
        $other = [];

        foreach ($entries as $index => $entry) {
            if ($entry->index === FileStatus::Deleted && $entry->worktree === FileStatus::Unmodified) {
                $deleted[$index] = $entry;
                continue;
            }

            if ($entry->index === FileStatus::Added) {
                $added[$index] = $entry;
                continue;
            }

            $other[] = $entry;
        }

        $matchedAdds = [];

        foreach ($deleted as $deleteEntry) {
            $bestIndex = null;
            $bestScore = 0;
            $oldHash = $headTree[$deleteEntry->path] ?? null;

            if ($oldHash === null) {
                $other[] = $deleteEntry;
                continue;
            }

            $oldContent = $this->readBlobContent($oldHash);

            foreach ($added as $addIndex => $addEntry) {
                if (isset($matchedAdds[$addIndex])) {
                    continue;
                }

                $newEntry = $indexEntries[$addEntry->path] ?? null;

                if ($newEntry === null) {
                    continue;
                }

                if ($newEntry->hash->hex === $oldHash) {
                    $bestIndex = $addIndex;
                    $bestScore = 100;
                    break;
                }

                $newContent = $this->readBlobContent($newEntry->hash->hex);
                $score = $this->similarityScore($oldContent, $newContent);

                if ($score >= 50 && $score > $bestScore) {
                    $bestIndex = $addIndex;
                    $bestScore = $score;
                }
            }

            if ($bestIndex === null) {
                $other[] = $deleteEntry;
                continue;
            }

            $matchedAdds[$bestIndex] = true;
            $addEntry = $added[$bestIndex];
            $other[] = new StatusEntry(
                $addEntry->path,
                FileStatus::Renamed,
                $addEntry->worktree,
                $deleteEntry->path,
                $bestScore,
            );
        }

        foreach ($added as $addIndex => $addEntry) {
            if (!isset($matchedAdds[$addIndex])) {
                $other[] = $addEntry;
            }
        }

        usort(
            $other,
            static fn (StatusEntry $a, StatusEntry $b): int => strcmp($a->path, $b->path),
        );

        return $other;
    }

    private function readBlobContent(string $hash): string
    {
        $object = $this->objects->read(ObjectId::fromHex($hash));

        return $object instanceof Blob ? $object->content : '';
    }

    private function similarityScore(string $oldContent, string $newContent): int
    {
        if ($oldContent === $newContent) {
            return 100;
        }

        if ($oldContent === '' || $newContent === '') {
            return 0;
        }

        $oldLines = explode("\n", $oldContent);
        $newLines = explode("\n", $newContent);
        $common = count(array_intersect($oldLines, $newLines));
        $total = max(count($oldLines), count($newLines));

        if ($total === 0) {
            return 0;
        }

        return (int) floor(($common / $total) * 100);
    }
}
