<?php

declare(strict_types=1);

namespace Pitmaster\Diff;

use Pitmaster\Object\Blob;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\Tree;
use Pitmaster\Storage\ObjectDatabase;

/**
 * Tree-to-tree diff (recursive).
 *
 * Compares two trees and produces DiffResult entries for changed files.
 */
final class TreeDiff
{
    public function __construct(private readonly ObjectDatabase $objects)
    {
    }

    /**
     * Diff two trees, returning results for each changed file.
     *
     * @return array<int, DiffResult>
     */
    public function diff(?ObjectId $oldTree, ?ObjectId $newTree, string $prefix = ''): array
    {
        $oldEntries = $this->readTreeEntries($oldTree);
        $newEntries = $this->readTreeEntries($newTree);

        $allNames = array_unique(array_merge(array_keys($oldEntries), array_keys($newEntries)));
        sort($allNames);

        $results = [];

        foreach ($allNames as $name) {
            $oldEntry = $oldEntries[$name] ?? null;
            $newEntry = $newEntries[$name] ?? null;
            $path = $prefix !== '' ? $prefix . '/' . $name : $name;

            if ($oldEntry === null && $newEntry !== null) {
                // Added
                if ($newEntry['isTree']) {
                    $results = array_merge($results, $this->diff(null, ObjectId::fromHex($newEntry['hash']), $path));
                } else {
                    $newContent = $this->readBlobContent($newEntry['hash']);
                    $results[] = $this->makeDiffResult($path, '', $newContent, null, $newEntry['hash']);
                }
            } elseif ($oldEntry !== null && $newEntry === null) {
                // Deleted
                if ($oldEntry['isTree']) {
                    $results = array_merge($results, $this->diff(ObjectId::fromHex($oldEntry['hash']), null, $path));
                } else {
                    $oldContent = $this->readBlobContent($oldEntry['hash']);
                    $results[] = $this->makeDiffResult($path, $oldContent, '', $oldEntry['hash'], null);
                }
            } elseif ($oldEntry['hash'] !== $newEntry['hash']) {
                // Modified
                if ($oldEntry['isTree'] && $newEntry['isTree']) {
                    $results = array_merge(
                        $results,
                        $this->diff(ObjectId::fromHex($oldEntry['hash']), ObjectId::fromHex($newEntry['hash']), $path)
                    );
                } elseif (!$oldEntry['isTree'] && !$newEntry['isTree']) {
                    $oldContent = $this->readBlobContent($oldEntry['hash']);
                    $newContent = $this->readBlobContent($newEntry['hash']);
                    $results[] = $this->makeDiffResult($path, $oldContent, $newContent, $oldEntry['hash'], $newEntry['hash']);
                } else {
                    // Type change (file -> dir or dir -> file)
                    if ($oldEntry['isTree']) {
                        $results = array_merge($results, $this->diff(ObjectId::fromHex($oldEntry['hash']), null, $path));
                    } else {
                        $oldContent = $this->readBlobContent($oldEntry['hash']);
                        $results[] = $this->makeDiffResult($path, $oldContent, '', $oldEntry['hash'], null);
                    }

                    if ($newEntry['isTree']) {
                        $results = array_merge($results, $this->diff(null, ObjectId::fromHex($newEntry['hash']), $path));
                    } else {
                        $newContent = $this->readBlobContent($newEntry['hash']);
                        $results[] = $this->makeDiffResult($path, '', $newContent, null, $newEntry['hash']);
                    }
                }
            }
        }

        return $results;
    }

    /**
     * @return array<string, array{hash: string, mode: string, isTree: bool}>
     */
    private function readTreeEntries(?ObjectId $treeId): array
    {
        if ($treeId === null) {
            return [];
        }

        $tree = $this->objects->read($treeId);

        if (!$tree instanceof Tree) {
            return [];
        }

        $entries = [];

        foreach ($tree->entries as $entry) {
            $entries[$entry->name] = [
                'hash' => $entry->hash->hex,
                'mode' => $entry->mode,
                'isTree' => $entry->isTree(),
            ];
        }

        return $entries;
    }

    private function readBlobContent(string $hash): string
    {
        $object = $this->objects->read(ObjectId::fromHex($hash));

        if ($object instanceof Blob) {
            return $object->content;
        }

        return '';
    }

    private function makeDiffResult(string $path, string $oldContent, string $newContent, ?string $oldHash, ?string $newHash): DiffResult
    {
        if (MyersDiff::isBinary($oldContent) || MyersDiff::isBinary($newContent)) {
            return new DiffResult($path, $path, [], true, $oldHash, $newHash);
        }

        $hunks = MyersDiff::diff($oldContent, $newContent);

        return new DiffResult($path, $path, $hunks, false, $oldHash, $newHash);
    }
}
