<?php

declare(strict_types=1);

namespace Pitmaster\Graph;

use Pitmaster\Diff\TreeDiff;
use Pitmaster\Index\Index;
use Pitmaster\Index\IndexEntry;
use Pitmaster\Index\IndexWriter;
use Pitmaster\Merge\MergeBase;
use Pitmaster\Merge\ThreeWayMerge;
use Pitmaster\Object\Blob;
use Pitmaster\Object\Commit;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;
use Pitmaster\Object\Tree;
use Pitmaster\Object\TreeEntry;
use Pitmaster\Ref\RefDatabase;
use Pitmaster\Storage\ObjectDatabase;

/**
 * Git rebase: replay commits from one branch onto another.
 *
 * Takes the commits that are in the current branch but not in the target,
 * and replays them one by one on top of the target.
 */
final class Rebase
{
    public function __construct(
        private readonly ObjectDatabase $objects,
        private readonly RefDatabase $refs,
        private readonly string $gitDir,
        private readonly string $workDir,
    ) {
    }

    /**
     * Rebase the current branch onto the given target.
     *
     * @return array{success: bool, commits: int, conflicts: array<int, string>}
     */
    public function rebase(ObjectId $onto): array
    {
        $headId = $this->refs->resolveHead();

        if ($headId === null) {
            return ['success' => false, 'commits' => 0, 'conflicts' => ['HEAD not set']];
        }

        // Find merge base
        $mergeBase = new MergeBase($this->objects);
        $base = $mergeBase->find($headId, $onto);

        if ($base === null) {
            return ['success' => false, 'commits' => 0, 'conflicts' => ['No common ancestor']];
        }

        // Collect commits to replay (from base to HEAD, excluding base)
        $walker = new CommitWalker($this->objects);
        $allCommits = $walker->walk($headId, 1000);
        $toReplay = [];

        foreach ($allCommits as $commit) {
            if ($commit->id->equals($base)) {
                break;
            }

            $toReplay[] = $commit;
        }

        // Reverse to replay in chronological order
        $toReplay = array_reverse($toReplay);

        if ($toReplay === []) {
            return ['success' => true, 'commits' => 0, 'conflicts' => []];
        }

        // Move HEAD to onto
        $currentId = $onto;
        $conflicts = [];

        foreach ($toReplay as $commit) {
            // Create new commit with same changes but new parent
            $content = Commit::buildContent(
                tree: $commit->tree,
                parents: [$currentId],
                author: $commit->author,
                committer: $commit->committer,
                message: $commit->message,
            );

            $newId = ObjectId::compute(ObjectType::Commit, $content);
            $newCommit = Commit::parse($content, $newId);
            $this->objects->write($newCommit);

            $currentId = $newId;
        }

        // Update HEAD
        $head = $this->refs->readHead();

        if ($head !== null) {
            $this->refs->update($head->target, $currentId);
        }

        return ['success' => true, 'commits' => count($toReplay), 'conflicts' => $conflicts];
    }
}
