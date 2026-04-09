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

        // Walk backwards assigning blame
        for ($i = 0; $i < count($versions); $i++) {
            $newerLines = $this->lines($versions[$i]['content']);

            foreach ($this->introducedLineIndexesForCommit($versions[$i]['commit'], $path, $newerLines) as $lineIndex) {
                if ($blame[$lineIndex] === null) {
                    $blame[$lineIndex] = $versions[$i]['commit'];
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
        if ($commit->parents === []) {
            return array_keys($newerLines);
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

        return $introduced;
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

    /**
     * @return array<int, string>
     */
    private function lines(string $content): array
    {
        $lines = explode("\n", $content);

        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    private function getFileContent(ObjectId $treeId, string $path): ?string
    {
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

                return $blob instanceof Blob ? $blob->content : null;
            }

            $current = $entry->hash;
        }

        return null;
    }
}
