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
    private const BINARY_PROBE_BYTES = 8192;

    /** @var array<string, Blob|null> */
    private array $blobCache = [];

    public function __construct(private readonly ObjectDatabase $objects)
    {
    }

    /**
     * Search for a pattern in all files in a tree.
     *
     * @param array{regex?: bool, ignore_case?: bool} $options
     * @return array<int, array{path: string, line: int, content: string}>
     */
    public function grep(ObjectId $treeId, string $pattern, string $prefix = '', array $options = []): array
    {
        $results = [];
        $matcher = $this->compileMatcher(
            $pattern,
            (bool) ($options['regex'] ?? false),
            (bool) ($options['ignore_case'] ?? false),
        );
        $this->grepTree($treeId, $matcher, $prefix, $results);

        return $results;
    }

    /**
     * @return array{regex: bool, ignore_case: bool, pattern: string, compiled: string}
     */
    private function compileMatcher(string $pattern, bool $regex, bool $ignoreCase): array
    {
        $delimiter = '~';
        $body = $regex ? str_replace($delimiter, '\\' . $delimiter, $pattern) : preg_quote($pattern, $delimiter);
        $compiled = $delimiter . $body . $delimiter . ($ignoreCase ? 'i' : '');

        if (@preg_match($compiled, '') === false) {
            throw new \InvalidArgumentException('Invalid grep pattern');
        }

        return [
            'regex' => $regex,
            'ignore_case' => $ignoreCase,
            'pattern' => $pattern,
            'compiled' => $compiled,
        ];
    }

    /**
     * @param array{regex: bool, ignore_case: bool, pattern: string, compiled: string} $matcher
     * @param array<int, array{path: string, line: int, content: string}> $results
     */
    private function grepTree(ObjectId $treeId, array $matcher, string $prefix, array &$results): void
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

            if (str_contains(substr($blob->content, 0, self::BINARY_PROBE_BYTES), "\0")) {
                if ($this->matches($blob->content, $matcher)) {
                    $results[] = [
                        'path' => $path,
                        'line' => 0,
                        'content' => '',
                    ];
                }

                continue;
            }

            if (!$this->matches($blob->content, $matcher)) {
                continue;
            }

            $this->appendTextMatches($path, $blob->content, $matcher, $results);
        }
    }

    /**
     * @param array{regex: bool, ignore_case: bool, pattern: string, compiled: string} $matcher
     */
    private function matches(string $content, array $matcher): bool
    {
        if ($matcher['regex']) {
            return preg_match($matcher['compiled'], $content) === 1;
        }

        if ($matcher['ignore_case']) {
            return stripos($content, $matcher['pattern']) !== false;
        }

        return str_contains($content, $matcher['pattern']);
    }

    /**
     * @param array{regex: bool, ignore_case: bool, pattern: string, compiled: string} $matcher
     * @param array<int, array{path: string, line: int, content: string}> $results
     */
    private function appendTextMatches(string $path, string $content, array $matcher, array &$results): void
    {
        $lineNumber = 1;
        $offset = 0;
        $length = strlen($content);

        while (true) {
            $nextNewline = strpos($content, "\n", $offset);

            if ($nextNewline === false) {
                $line = substr($content, $offset);
            } else {
                $line = substr($content, $offset, $nextNewline - $offset);
            }

            if ($this->matches($line, $matcher)) {
                $results[] = [
                    'path' => $path,
                    'line' => $lineNumber,
                    'content' => $line,
                ];
            }

            if ($nextNewline === false) {
                break;
            }

            $lineNumber++;
            $offset = $nextNewline + 1;

            if ($offset > $length) {
                break;
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
