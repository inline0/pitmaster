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

        return $this->detectRenames($results);
    }

    /**
     * Detect renames by matching deleted files with added files by content similarity.
     *
     * @param array<int, DiffResult> $results
     * @return array<int, DiffResult>
     */
    private function detectRenames(array $results): array
    {
        $deleted = [];
        $added = [];
        $other = [];

        foreach ($results as $i => $result) {
            if ($result->oldHash !== null && $result->newHash === null && !$result->binary) {
                $deleted[$i] = $result;
            } elseif ($result->oldHash === null && $result->newHash !== null && !$result->binary) {
                $added[$i] = $result;
            } else {
                $other[$i] = $result;
            }
        }

        $matched = [];

        foreach ($deleted as $di => $del) {
            $bestScore = 0;
            $bestIdx = null;

            foreach ($added as $ai => $add) {
                if (isset($matched[$ai])) {
                    continue;
                }

                // Exact match by hash
                if ($del->oldHash === $add->newHash) {
                    $bestScore = 100;
                    $bestIdx = $ai;
                    break;
                }

                // Content similarity (simple: ratio of shared lines)
                $oldContent = $this->readBlobContent($del->oldHash);
                $newContent = $this->readBlobContent($add->newHash);

                if ($oldContent !== '' && $newContent !== '') {
                    $oldLines = explode("\n", $oldContent);
                    $newLines = explode("\n", $newContent);
                    $common = count(array_intersect($oldLines, $newLines));
                    $total = max(count($oldLines), count($newLines));
                    $score = $total > 0 ? (int) (($common / $total) * 100) : 0;

                    if ($score > $bestScore && $score >= 50) {
                        $bestScore = $score;
                        $bestIdx = $ai;
                    }
                }
            }

            if ($bestIdx !== null) {
                $add = $added[$bestIdx];
                $oldContent = $this->readBlobContent($del->oldHash);
                $newContent = $this->readBlobContent($add->newHash);
                $hunks = MyersDiff::diff($oldContent, $newContent);

                $other[] = new DiffResult(
                    $del->oldPath,
                    $add->newPath,
                    $hunks,
                    false,
                    $del->oldHash,
                    $add->newHash,
                );

                $matched[$bestIdx] = true;
                unset($deleted[$di]);
            }
        }

        // Add remaining unmatched deletes and adds
        foreach ($deleted as $del) {
            $other[] = $del;
        }

        foreach ($added as $ai => $add) {
            if (!isset($matched[$ai])) {
                $other[] = $add;
            }
        }

        // Sort by path
        usort($other, fn (DiffResult $a, DiffResult $b) => strcmp($a->newPath, $b->newPath));

        return $other;
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
