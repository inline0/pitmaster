<?php

declare(strict_types=1);

namespace Pitmaster\Index;

use Pitmaster\Object\Commit;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\Tree;
use Pitmaster\Storage\ObjectDatabase;

/**
 * Compare index against HEAD tree or worktree.
 */
final class IndexDiff
{
    public function __construct(private readonly ObjectDatabase $objects)
    {
    }

    /**
     * Compare index entries against a commit's tree.
     *
     * @return array{added: array<string>, modified: array<string>, deleted: array<string>}
     */
    public function diffAgainstTree(Index $index, ?ObjectId $commitId): array
    {
        $treeMap = [];

        if ($commitId !== null) {
            $commit = $this->objects->read($commitId);

            if ($commit instanceof Commit) {
                $treeMap = $this->flattenTree($commit->tree);
            }
        }

        $added = [];
        $modified = [];
        $deleted = [];
        $indexEntries = $index->entries();

        // Files in index but not in tree = added
        // Files in both but different hash = modified
        foreach ($indexEntries as $path => $entry) {
            if (!isset($treeMap[$path])) {
                $added[] = $path;
            } elseif ($treeMap[$path] !== $entry->hash->hex) {
                $modified[] = $path;
            }
        }

        // Files in tree but not in index = deleted
        foreach ($treeMap as $path => $hash) {
            if (!isset($indexEntries[$path])) {
                $deleted[] = $path;
            }
        }

        sort($added);
        sort($modified);
        sort($deleted);

        return ['added' => $added, 'modified' => $modified, 'deleted' => $deleted];
    }

    /**
     * @return array<string, string> path => hex hash
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
}
