<?php

declare(strict_types=1);

namespace Pitmaster\Graph;

use Pitmaster\Object\Blob;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\Tree;
use Pitmaster\Storage\ObjectDatabase;

/**
 * Git grep: search file contents in a tree.
 */
final class Grep
{
    public function __construct(private readonly ObjectDatabase $objects)
    {
    }

    /**
     * Search for a pattern in all files in a tree.
     *
     * @return array<int, array{path: string, line: int, content: string}>
     */
    public function grep(ObjectId $treeId, string $pattern, string $prefix = ''): array
    {
        $results = [];
        $tree = $this->objects->read($treeId);

        if (!$tree instanceof Tree) {
            return $results;
        }

        foreach ($tree->entries as $entry) {
            $path = $prefix !== '' ? $prefix . '/' . $entry->name : $entry->name;

            if ($entry->isTree()) {
                $results = array_merge($results, $this->grep($entry->hash, $pattern, $path));
                continue;
            }

            $blob = $this->objects->read($entry->hash);

            if (!$blob instanceof Blob) {
                continue;
            }

            // Skip binary
            if (str_contains(substr($blob->content, 0, 8192), "\0")) {
                continue;
            }

            foreach (explode("\n", $blob->content) as $lineNum => $line) {
                if (preg_match('/' . preg_quote($pattern, '/') . '/', $line)) {
                    $results[] = [
                        'path' => $path,
                        'line' => $lineNum + 1,
                        'content' => $line,
                    ];
                }
            }
        }

        return $results;
    }
}
