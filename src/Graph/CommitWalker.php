<?php

declare(strict_types=1);

namespace Pitmaster\Graph;

use Pitmaster\Object\Commit;
use Pitmaster\Object\ObjectId;
use Pitmaster\Storage\ObjectDatabase;

/**
 * Topological commit traversal (log).
 *
 * Walks the commit graph from a starting point, yielding commits in
 * reverse chronological order (newest first). Handles merge commits
 * by visiting all parents.
 */
final class CommitWalker
{
    public function __construct(private readonly ObjectDatabase $objects)
    {
    }

    /**
     * Walk commits starting from the given ID.
     *
     * @return array<int, Commit>
     */
    public function walk(ObjectId $from, int $limit = 50): array
    {
        return $this->walkAll([$from], $limit);
    }

    /**
     * Walk all commits from multiple starting points.
     *
     * @param array<int, ObjectId> $from
     * @return array<int, Commit>
     */
    public function walkAll(array $from, int $limit = 50): array
    {
        $commits = [];
        $visited = [];
        $queue = [];

        foreach ($from as $id) {
            $this->enqueue($queue, $id, $visited);
        }

        while ($queue !== [] && count($commits) < $limit) {
            $entry = array_shift($queue);
            $object = $entry['commit'];
            $commits[] = $object;

            foreach ($object->parents as $parentId) {
                $this->enqueue($queue, $parentId, $visited);
            }
        }

        return $commits;
    }

    /**
     * Insert by descending timestamp (newest first).
     *
     * @param array<int, array{timestamp: int, commit: Commit}> $queue
     * @param array<string, true> $visited
     * @param-out array<int, array{timestamp: int, commit: Commit}> $queue
     */
    private function enqueue(array &$queue, ObjectId $id, array &$visited): void
    {
        if (isset($visited[$id->hex])) {
            return;
        }

        $visited[$id->hex] = true;

        $object = $this->objects->read($id);

        if (!$object instanceof Commit) {
            return;
        }

        $timestamp = $object->committerTimestamp() ?? 0;
        $entry = ['timestamp' => $timestamp, 'commit' => $object];

        $count = count($queue);

        for ($i = 0; $i < $count; $i++) {
            if ($queue[$i]['timestamp'] < $timestamp) {
                array_splice($queue, $i, 0, [$entry]);

                return;
            }
        }

        $queue[] = $entry;
    }
}
