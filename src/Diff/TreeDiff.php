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
    /** @var array<string, string> */
    private array $blobCache = [];

    /** @var array<string, array<string, array{hash: string, mode: string, isTree: bool}>> */
    private array $treeEntriesCache = [];

    public function __construct(
        private readonly ObjectDatabase $objects,
        private readonly string $algorithm = 'myers',
    ) {
    }

    /**
     * Diff two trees, returning results for each changed file.
     *
     * @return array<int, DiffResult>
     */
    public function diff(?ObjectId $oldTree, ?ObjectId $newTree, string $prefix = ''): array
    {
        $results = [];
        $this->diffInto($oldTree, $newTree, $prefix, $results);

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
                    $score = (int) (($common / $total) * 100);

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
                $hunks = DiffAlgorithm::diff($oldContent, $newContent, $this->algorithm);

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

        if (isset($this->treeEntriesCache[$treeId->hex])) {
            return $this->treeEntriesCache[$treeId->hex];
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

        $this->treeEntriesCache[$treeId->hex] = $entries;

        return $entries;
    }

    private function readBlobContent(string $hash): string
    {
        if (isset($this->blobCache[$hash])) {
            return $this->blobCache[$hash];
        }

        $object = $this->objects->read(ObjectId::fromHex($hash));

        if ($object instanceof Blob) {
            return $this->blobCache[$hash] = $object->content;
        }

        return '';
    }

    /**
     * @param array<int, DiffResult> $results
     */
    private function diffInto(?ObjectId $oldTree, ?ObjectId $newTree, string $prefix, array &$results): void
    {
        $oldEntries = $this->readTreeEntries($oldTree);
        $newEntries = $this->readTreeEntries($newTree);
        $allNames = array_unique(array_merge(array_keys($oldEntries), array_keys($newEntries)));
        sort($allNames);

        foreach ($allNames as $name) {
            $oldEntry = $oldEntries[$name] ?? null;
            $newEntry = $newEntries[$name] ?? null;
            $path = $prefix !== '' ? $prefix . '/' . $name : $name;

            if ($oldEntry === null && $newEntry !== null) {
                if ($newEntry['isTree']) {
                    $this->diffInto(null, ObjectId::fromHex($newEntry['hash']), $path, $results);
                } else {
                    $results[] = $this->makeDiffResult($path, '', $this->readBlobContent($newEntry['hash']), null, $newEntry['hash']);
                }

                continue;
            }

            if ($oldEntry !== null && $newEntry === null) {
                if ($oldEntry['isTree']) {
                    $this->diffInto(ObjectId::fromHex($oldEntry['hash']), null, $path, $results);
                } else {
                    $results[] = $this->makeDiffResult($path, $this->readBlobContent($oldEntry['hash']), '', $oldEntry['hash'], null);
                }

                continue;
            }

            if ($oldEntry['hash'] === $newEntry['hash']) {
                continue;
            }

            if ($oldEntry['isTree'] && $newEntry['isTree']) {
                $this->diffInto(ObjectId::fromHex($oldEntry['hash']), ObjectId::fromHex($newEntry['hash']), $path, $results);
                continue;
            }

            if (!$oldEntry['isTree'] && !$newEntry['isTree']) {
                $results[] = $this->makeDiffResult(
                    $path,
                    $this->readBlobContent($oldEntry['hash']),
                    $this->readBlobContent($newEntry['hash']),
                    $oldEntry['hash'],
                    $newEntry['hash'],
                );
                continue;
            }

            if ($oldEntry['isTree']) {
                $this->diffInto(ObjectId::fromHex($oldEntry['hash']), null, $path, $results);
            } else {
                $results[] = $this->makeDiffResult($path, $this->readBlobContent($oldEntry['hash']), '', $oldEntry['hash'], null);
            }

            if ($newEntry['isTree']) {
                $this->diffInto(null, ObjectId::fromHex($newEntry['hash']), $path, $results);
            } else {
                $results[] = $this->makeDiffResult($path, '', $this->readBlobContent($newEntry['hash']), null, $newEntry['hash']);
            }
        }
    }

    private function makeDiffResult(
        string $path,
        string $oldContent,
        string $newContent,
        ?string $oldHash,
        ?string $newHash,
    ): DiffResult {
        if (MyersDiff::isBinary($oldContent) || MyersDiff::isBinary($newContent)) {
            return new DiffResult($path, $path, [], true, $oldHash, $newHash);
        }

        $hunks = DiffAlgorithm::diff($oldContent, $newContent, $this->algorithm);

        return new DiffResult($path, $path, $hunks, false, $oldHash, $newHash);
    }
}
