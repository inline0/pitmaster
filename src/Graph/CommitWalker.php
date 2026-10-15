<?php

declare(strict_types=1);

namespace Pitmaster\Graph;

use Pitmaster\Object\Commit;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\Tag;
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
    public function walk(ObjectId $from, int $limit = 50, bool $oldest = false): array
    {
        return $this->walkAll([$from], $limit, $oldest);
    }

    /**
     * Walk all commits from multiple starting points.
     *
     * @param array<int, ObjectId> $from
     * @return array<int, Commit>
     */
    public function walkAll(array $from, int $limit = 50, bool $oldest = false): array
    {
        return self::select($this->iterate($from), $limit, $oldest);
    }

    /**
     * @param iterable<Commit> $commits
     * @return list<Commit>
     */
    public static function select(iterable $commits, int $limit, bool $oldest = false): array
    {
        if ($limit <= 0) {
            return [];
        }
        /** @var \SplQueue<Commit> $retained */
        $retained = new \SplQueue();
        foreach ($commits as $commit) {
            $retained->enqueue($commit);
            if ($retained->count() > $limit) {
                $retained->dequeue();
            }
            if (!$oldest && $retained->count() === $limit) {
                break;
            }
        }
        return iterator_to_array($retained, false);
    }

    /**
     * Stream history until the caller has enough matching commits.
     *
     * @param array<int, ObjectId> $from
     * @param (callable(Commit): array<int, ObjectId>)|null $selectParents
     * @return \Generator<int, Commit>
     */
    public function iterate(array $from, ?callable $selectParents = null): \Generator
    {
        $visited = [];
        $queue = [];

        foreach ($from as $id) {
            $this->enqueue($queue, $id, $visited);
        }

        while ($queue !== []) {
            $entry = array_shift($queue);
            $object = $entry['commit'];
            yield $object;

            foreach ($selectParents === null ? $object->parents : $selectParents($object) as $parentId) {
                $this->enqueue($queue, $parentId, $visited);
            }
        }
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

        if ($object instanceof Tag) {
            $this->enqueue($queue, $object->object, $visited);
            return;
        }

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
