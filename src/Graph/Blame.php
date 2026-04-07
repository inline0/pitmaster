<?php

declare(strict_types=1);

namespace Pitmaster\Graph;

use Pitmaster\Diff\MyersDiff;
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

        // Start with the current version
        $currentLines = explode("\n", $versions[0]['content']);
        $blame = array_fill(0, count($currentLines), null);

        // Walk backwards assigning blame
        for ($i = 0; $i < count($versions) - 1; $i++) {
            $newer = explode("\n", $versions[$i]['content']);
            $older = explode("\n", $versions[$i + 1]['content']);

            // Lines present in newer but not in older were introduced by this commit
            $hunks = MyersDiff::diff($versions[$i + 1]['content'], $versions[$i]['content']);

            foreach ($hunks as $hunk) {
                foreach ($hunk->lines as $line) {
                    if (str_starts_with($line, '+')) {
                        $lineNum = $hunk->newStart - 1;

                        for ($j = $lineNum; $j < $lineNum + $hunk->newCount && $j < count($blame); $j++) {
                            if ($blame[$j] === null) {
                                $blame[$j] = $versions[$i]['commit'];
                            }
                        }
                    }
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
