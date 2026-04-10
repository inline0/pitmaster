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
    /** @var array<string, Blob|null> */
    private array $blobCache = [];

    public function __construct(private readonly ObjectDatabase $objects)
    {
    }

    /**
     * Search for a pattern in all files in a tree.
     *
     * @return array<int, array{path: string, line: int, content: string}>
     */
    public function grep(ObjectId $treeId, string $pattern, string $prefix = '', array $options = []): array
    {
        $results = [];
        $matcher = $this->compilePattern(
            $pattern,
            (bool) ($options['regex'] ?? false),
            (bool) ($options['ignore_case'] ?? false),
        );
        $this->grepTree($treeId, $matcher, $prefix, $results);

        return $results;
    }

    private function compilePattern(string $pattern, bool $regex, bool $ignoreCase): string
    {
        $delimiter = '~';
        $body = $regex ? str_replace($delimiter, '\\' . $delimiter, $pattern) : preg_quote($pattern, $delimiter);
        $compiled = $delimiter . $body . $delimiter . ($ignoreCase ? 'i' : '');

        if (@preg_match($compiled, '') === false) {
            throw new \InvalidArgumentException('Invalid grep pattern');
        }

        return $compiled;
    }

    /**
     * @param array<int, array{path: string, line: int, content: string}> $results
     */
    private function grepTree(ObjectId $treeId, string $matcher, string $prefix, array &$results): void
    {
        $tree = $this->objects->read($treeId);

        if (!$tree instanceof Tree) {
            return;
        }

        foreach ($tree->entries as $entry) {
            $path = $prefix !== '' ? $prefix . '/' . $entry->name : $entry->name;

            if ($entry->isTree()) {
                $this->grepTree($entry->hash, $matcher, $path, $results);
                continue;
            }

            $blob = $this->readBlob($entry->hash);

            if ($blob === null) {
                continue;
            }

            if (str_contains(substr($blob->content, 0, 8192), "\0")) {
                if (preg_match($matcher, $blob->content) === 1) {
                    $results[] = [
                        'path' => $path,
                        'line' => 0,
                        'content' => '',
                    ];
                }

                continue;
            }

            foreach (explode("\n", $blob->content) as $lineNum => $line) {
                if (preg_match($matcher, $line) === 1) {
                    $results[] = [
                        'path' => $path,
                        'line' => $lineNum + 1,
                        'content' => $line,
                    ];
                }
            }
        }
    }

    private function readBlob(ObjectId $id): ?Blob
    {
        if (array_key_exists($id->hex, $this->blobCache)) {
            return $this->blobCache[$id->hex];
        }

        $blob = $this->objects->read($id);
        $this->blobCache[$id->hex] = $blob instanceof Blob ? $blob : null;

        return $this->blobCache[$id->hex];
    }
}
