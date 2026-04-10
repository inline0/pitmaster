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
        $commits = [];
        $visited = [];
        $queue = new \SplPriorityQueue();

        $this->enqueue($queue, $from, $visited);

        while (!$queue->isEmpty() && count($commits) < $limit) {
            ['id' => $id, 'commit' => $object] = $queue->extract();

            $commits[] = $object;

            foreach ($object->parents as $parentId) {
                $this->enqueue($queue, $parentId, $visited);
            }
        }

        return $commits;
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
        $queue = new \SplPriorityQueue();

        foreach ($from as $id) {
            $this->enqueue($queue, $id, $visited);
        }

        while (!$queue->isEmpty() && count($commits) < $limit) {
            ['id' => $id, 'commit' => $object] = $queue->extract();

            $commits[] = $object;

            foreach ($object->parents as $parentId) {
                $this->enqueue($queue, $parentId, $visited);
            }
        }

        return $commits;
    }

    /**
     * @param array<string, true> $visited
     */
    private function enqueue(\SplPriorityQueue $queue, ObjectId $id, array &$visited): void
    {
        if (isset($visited[$id->hex])) {
            return;
        }

        $visited[$id->hex] = true;

        $object = $this->objects->read($id);

        if ($object instanceof Commit) {
            $timestamp = $object->committerTimestamp() ?? 0;
            $queue->insert(['id' => $id, 'commit' => $object], $timestamp);
        }
    }
}
