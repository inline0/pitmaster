<?php

declare(strict_types=1);

namespace Pitmaster\Graph;

use Pitmaster\Object\Blob;
use Pitmaster\Object\Commit;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\Tree;
use Pitmaster\Storage\ObjectDatabase;

/**
 * Git blame: annotate each line of a file with the commit that last changed it.
 */
final class Blame
{
    /** @var array<string, string|null> */
    private array $fileContentCache = [];

    /** @var array<string, array<int, string>> */
    private array $lineCache = [];

    /** @var array<string, array<int, int>> */
    private array $introducedCache = [];

    public function __construct(private readonly ObjectDatabase $objects)
    {
    }

    /**
     * Blame a file, returning commit hash per line.
     *
     * @return array<int, array{line: int, hash: string, author: string, content: string}>
     */
    public function blame(ObjectId $headId, string $path): array
    {
        // Walk history collecting versions of this file
        $walker = new CommitWalker($this->objects);
        $commits = $walker->walk($headId, 500);

        $versions = [];

        foreach ($commits as $commit) {
            $content = $this->getFileContent($commit->tree, $path);

            if ($content !== null) {
                $versions[] = ['commit' => $commit, 'content' => $content];
            }
        }

        if ($versions === []) {
            return [];
        }

        // Start with the current version (strip trailing empty line from \n)
        $currentLines = $this->lines($versions[0]['content']);
        $blame = array_fill(0, count($currentLines), null);
        $remaining = count($currentLines);

        // Walk backwards assigning blame
        for ($i = 0; $i < count($versions) && $remaining > 0; $i++) {
            $newerLines = $this->lines($versions[$i]['content']);

            foreach ($this->introducedLineIndexesForCommit($versions[$i]['commit'], $path, $newerLines) as $lineIndex) {
                if ($blame[$lineIndex] === null) {
                    $blame[$lineIndex] = $versions[$i]['commit'];
                    $remaining--;
                }
            }
        }

        // Any remaining unblamed lines belong to the oldest commit
        $oldestCommit = $versions[count($versions) - 1]['commit'];

        foreach ($blame as $i => &$entry) {
            if ($entry === null) {
                $entry = $oldestCommit;
            }
        }

        $result = [];

        foreach ($currentLines as $i => $line) {
            $commit = $blame[$i];
            $result[] = [
                'line' => $i + 1,
                'hash' => $commit instanceof Commit ? $commit->id->hex : '',
                'author' => $commit instanceof Commit ? $commit->author : '',
                'content' => $line,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, string> $newerLines
     * @return array<int, int>
     */
    private function introducedLineIndexesForCommit(Commit $commit, string $path, array $newerLines): array
    {
        $cacheKey = $commit->id->hex . "\0" . $path;

        if (isset($this->introducedCache[$cacheKey])) {
            return $this->introducedCache[$cacheKey];
        }

        if ($commit->parents === []) {
            return $this->introducedCache[$cacheKey] = array_keys($newerLines);
        }

        $matchedNewIndexes = [];

        foreach ($commit->parents as $parentId) {
            $parent = $this->objects->read($parentId);

            if (!$parent instanceof Commit) {
                continue;
            }

            $content = $this->getFileContent($parent->tree, $path);

            if ($content === null) {
                continue;
            }

            foreach ($this->matchedLineIndexes($this->lines($content), $newerLines) as $lineIndex) {
                $matchedNewIndexes[$lineIndex] = true;
            }
        }

        $introduced = [];

        foreach (array_keys($newerLines) as $index) {
            if (!isset($matchedNewIndexes[$index])) {
                $introduced[] = $index;
            }
        }

        return $this->introducedCache[$cacheKey] = $introduced;
    }

    /**
     * @param array<int, string> $olderLines
     * @param array<int, string> $newerLines
     * @return array<int, int>
     */
    private function matchedLineIndexes(array $olderLines, array $newerLines): array
    {
        $oldCount = count($olderLines);
        $newCount = count($newerLines);

        if ($oldCount === 0 || $newCount === 0) {
            return [];
        }

        if ($olderLines === $newerLines) {
            return array_keys($newerLines);
        }

        if ($oldCount <= $newCount && $this->isPrefixMatch($olderLines, $newerLines, $oldCount)) {
            return range(0, $oldCount - 1);
        }

        if ($oldCount <= $newCount && $this->isSuffixMatch($olderLines, $newerLines, $oldCount, $newCount)) {
            return range($newCount - $oldCount, $newCount - 1);
        }

        $lcs = array_fill(0, $oldCount + 1, array_fill(0, $newCount + 1, 0));

        for ($oldIndex = $oldCount - 1; $oldIndex >= 0; $oldIndex--) {
            for ($newIndex = $newCount - 1; $newIndex >= 0; $newIndex--) {
                if ($olderLines[$oldIndex] === $newerLines[$newIndex]) {
                    $lcs[$oldIndex][$newIndex] = $lcs[$oldIndex + 1][$newIndex + 1] + 1;
                    continue;
                }

                $lcs[$oldIndex][$newIndex] = max(
                    $lcs[$oldIndex + 1][$newIndex],
                    $lcs[$oldIndex][$newIndex + 1],
                );
            }
        }

        $matchedNewIndexes = [];
        $oldIndex = 0;
        $newIndex = 0;

        while ($oldIndex < $oldCount && $newIndex < $newCount) {
            if ($olderLines[$oldIndex] === $newerLines[$newIndex]) {
                $matchedNewIndexes[$newIndex] = true;
                $oldIndex++;
                $newIndex++;
                continue;
            }

            if ($lcs[$oldIndex + 1][$newIndex] >= $lcs[$oldIndex][$newIndex + 1]) {
                $oldIndex++;
                continue;
            }

            $newIndex++;
        }

        return array_keys($matchedNewIndexes);
    }

    private function isPrefixMatch(array $olderLines, array $newerLines, int $length): bool
    {
        for ($index = 0; $index < $length; $index++) {
            if ($olderLines[$index] !== $newerLines[$index]) {
                return false;
            }
        }

        return true;
    }

    private function isSuffixMatch(array $olderLines, array $newerLines, int $oldCount, int $newCount): bool
    {
        $offset = $newCount - $oldCount;

        for ($index = 0; $index < $oldCount; $index++) {
            if ($olderLines[$index] !== $newerLines[$offset + $index]) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function lines(string $content): array
    {
        if (isset($this->lineCache[$content])) {
            return $this->lineCache[$content];
        }

        $lines = explode("\n", $content);

        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return $this->lineCache[$content] = $lines;
    }

    private function getFileContent(ObjectId $treeId, string $path): ?string
    {
        $cacheKey = $treeId->hex . "\0" . $path;

        if (array_key_exists($cacheKey, $this->fileContentCache)) {
            return $this->fileContentCache[$cacheKey];
        }

        $parts = explode('/', $path);
        $current = $treeId;

        foreach ($parts as $i => $part) {
            $tree = $this->objects->read($current);

            if (!$tree instanceof Tree) {
                return null;
            }

            $entry = $tree->entry($part);

            if ($entry === null) {
                return null;
            }

            if ($i === count($parts) - 1) {
                $blob = $this->objects->read($entry->hash);

                return $this->fileContentCache[$cacheKey] = $blob instanceof Blob ? $blob->content : null;
            }

            $current = $entry->hash;
        }

        return $this->fileContentCache[$cacheKey] = null;
    }
}
