<?php

declare(strict_types=1);

namespace Pitmaster\Graph;

use Pitmaster\Object\Commit;
use Pitmaster\Object\ObjectId;
use Pitmaster\Storage\ObjectDatabase;

/**
 * Git bisect: binary search for the commit that introduced a bug.
 *
 * Maintains state in .git/BISECT_START, .git/BISECT_LOG, and
 * refs/bisect/good-* and refs/bisect/bad.
 */
final class Bisect
{
    public function __construct(
        private readonly ObjectDatabase $objects,
        private readonly string $gitDir,
    ) {
    }

    /**
     * Start a bisect session.
     */
    public function start(ObjectId $bad, ObjectId $good, string $startRef = 'HEAD', ?callable $subjectResolver = null): ObjectId
    {
        $bisectDir = $this->gitDir . '/refs/bisect';

        if (!is_dir($bisectDir)) {
            mkdir($bisectDir, 0777, true);
        }

        file_put_contents($this->gitDir . '/BISECT_START', $startRef . "\n");
        file_put_contents($this->gitDir . '/BISECT_TERMS', "bad\ngood\n");
        file_put_contents($this->gitDir . '/BISECT_ANCESTORS_OK', '');
        file_put_contents($this->gitDir . '/BISECT_NAMES', "\n");
        file_put_contents($bisectDir . '/bad', $bad->hex . "\n");
        file_put_contents($bisectDir . '/good-' . $good->hex, $good->hex . "\n");

        $midpoint = $this->nextCandidate($bad, [$good]) ?? $bad;
        file_put_contents($this->gitDir . '/BISECT_EXPECTED_REV', $midpoint->hex . "\n");

        $log = sprintf(
            "# bad: [%s] %s\n# good: [%s] %s\ngit bisect start '%s' '%s'\n",
            $bad->hex,
            $this->describeCommit($bad, $subjectResolver),
            $good->hex,
            $this->describeCommit($good, $subjectResolver),
            $bad->hex,
            $good->hex,
        );
        file_put_contents($this->gitDir . '/BISECT_LOG', $log);

        return $midpoint;
    }

    /**
     * Mark a commit as good and get the next commit to test.
     */
    public function good(ObjectId $commitId, ?callable $subjectResolver = null): ?ObjectId
    {
        $bisectDir = $this->gitDir . '/refs/bisect';
        file_put_contents($bisectDir . '/good-' . $commitId->hex, $commitId->hex . "\n");

        $this->appendCommandLog('good', $commitId, $subjectResolver);

        $bad = $this->readBad();
        $goods = $this->readGoods();

        if ($bad === null) {
            return null;
        }

        $next = $this->nextCandidate($bad, $goods);

        if ($next !== null) {
            file_put_contents($this->gitDir . '/BISECT_EXPECTED_REV', $next->hex . "\n");
        } else {
            @unlink($this->gitDir . '/BISECT_EXPECTED_REV');
        }

        return $next;
    }

    /**
     * Mark a commit as bad and get the next commit to test.
     */
    public function bad(ObjectId $commitId, ?callable $subjectResolver = null): ?ObjectId
    {
        $bisectDir = $this->gitDir . '/refs/bisect';
        file_put_contents($bisectDir . '/bad', $commitId->hex . "\n");

        $this->appendCommandLog('bad', $commitId, $subjectResolver);

        $goods = $this->readGoods();

        if ($goods === []) {
            return null;
        }

        $next = $this->nextCandidate($commitId, $goods);

        if ($next !== null) {
            file_put_contents($this->gitDir . '/BISECT_EXPECTED_REV', $next->hex . "\n");
        } else {
            @unlink($this->gitDir . '/BISECT_EXPECTED_REV');
        }

        return $next;
    }

    /**
     * Reset bisect state.
     */
    public function reset(): void
    {
        @unlink($this->gitDir . '/BISECT_START');
        @unlink($this->gitDir . '/BISECT_LOG');
        @unlink($this->gitDir . '/BISECT_EXPECTED_REV');
        @unlink($this->gitDir . '/BISECT_ANCESTORS_OK');
        @unlink($this->gitDir . '/BISECT_NAMES');
        @unlink($this->gitDir . '/BISECT_TERMS');

        $bisectDir = $this->gitDir . '/refs/bisect';

        if (is_dir($bisectDir)) {
            foreach (scandir($bisectDir) as $file) {
                if ($file !== '.' && $file !== '..') {
                    unlink($bisectDir . '/' . $file);
                }
            }

            rmdir($bisectDir);
        }
    }

    /**
     * Check if a bisect session is active.
     */
    public function isActive(): bool
    {
        return is_file($this->gitDir . '/BISECT_START');
    }

    /**
     * @param array<int, ObjectId> $goods
     */
    private function nextCandidate(ObjectId $bad, array $goods): ?ObjectId
    {
        $walker = new CommitWalker($this->objects);
        $checker = new AncestryChecker($this->objects);
        $suspects = [];

        foreach ($walker->walk($bad, 10000) as $commit) {
            if ($this->isKnownGood($commit->id, $goods, $checker)) {
                continue;
            }

            $suspects[] = $commit;
        }

        if ($suspects === []) {
            return null;
        }

        if (count($suspects) === 1) {
            return $suspects[0]->id;
        }

        return $this->findBisectionCandidate(array_reverse($suspects));
    }

    /**
     * @param array<int, Commit> $commits
     */
    private function findBisectionCandidate(array $commits): ?ObjectId
    {
        $interesting = [];
        $weights = [];
        $counted = 0;
        $total = count($commits);

        foreach ($commits as $commit) {
            $interesting[$commit->id->hex] = true;
        }

        foreach ($commits as $commit) {
            $parentCount = $this->countInterestingParents($commit, $interesting);

            if ($parentCount === 0) {
                $weights[$commit->id->hex] = 1;
                $counted++;

                continue;
            }

            $weights[$commit->id->hex] = $parentCount === 1 ? -1 : -2;
        }

        foreach ($commits as $commit) {
            if ($weights[$commit->id->hex] !== -2) {
                continue;
            }

            $weights[$commit->id->hex] = $this->countReachableInteresting($commit, $interesting);
            $counted++;

            if ($this->approxHalfway($weights[$commit->id->hex], $total)) {
                return $commit->id;
            }
        }

        while ($counted < $total) {
            foreach ($commits as $commit) {
                if (($weights[$commit->id->hex] ?? -1) >= 0) {
                    continue;
                }

                $knownParentWeight = $this->knownInterestingParentWeight($commit, $interesting, $weights);

                if ($knownParentWeight === null) {
                    continue;
                }

                $weights[$commit->id->hex] = $knownParentWeight + 1;
                $counted++;

                if ($this->approxHalfway($weights[$commit->id->hex], $total)) {
                    return $commit->id;
                }
            }
        }

        return $this->bestBisection($commits, $weights)?->id;
    }

    /**
     * @param array<string, true> $interesting
     */
    private function countInterestingParents(Commit $commit, array $interesting): int
    {
        $count = 0;

        foreach ($commit->parents as $parentId) {
            if (isset($interesting[$parentId->hex])) {
                $count++;
            }
        }

        return $count;
    }

    private function approxHalfway(int $weight, int $total): bool
    {
        $diff = (2 * $weight) - $total;

        return $diff === -1 || $diff === 0 || $diff === 1;
    }

    /**
     * @param array<int, Commit> $commits
     * @param array<string, int> $weights
     */
    private function bestBisection(array $commits, array $weights): ?Commit
    {
        $total = count($commits);
        $best = null;
        $bestDistance = -1;

        foreach ($commits as $commit) {
            $distance = $weights[$commit->id->hex] ?? 0;

            if ($total - $distance < $distance) {
                $distance = $total - $distance;
            }

            if ($distance > $bestDistance) {
                $best = $commit;
                $bestDistance = $distance;
            }
        }

        return $best;
    }

    /**
     * @param array<string, true> $interesting
     */
    private function countReachableInteresting(Commit $commit, array $interesting): int
    {
        $visited = [];

        return $this->countReachableInterestingRecursive($commit, $interesting, $visited);
    }

    /**
     * @param array<string, true> $interesting
     * @param array<string, true> $visited
     */
    private function countReachableInterestingRecursive(Commit $commit, array $interesting, array &$visited): int
    {
        if (isset($visited[$commit->id->hex]) || !isset($interesting[$commit->id->hex])) {
            return 0;
        }

        $visited[$commit->id->hex] = true;
        $count = 1;

        foreach ($commit->parents as $parentId) {
            if (!isset($interesting[$parentId->hex])) {
                continue;
            }

            $parent = $this->objects->read($parentId);

            if (!$parent instanceof Commit) {
                continue;
            }

            $count += $this->countReachableInterestingRecursive($parent, $interesting, $visited);
        }

        return $count;
    }

    /**
     * @param array<string, true> $interesting
     * @param array<string, int> $weights
     */
    private function knownInterestingParentWeight(Commit $commit, array $interesting, array $weights): ?int
    {
        foreach ($commit->parents as $parentId) {
            if (!isset($interesting[$parentId->hex])) {
                continue;
            }

            $weight = $weights[$parentId->hex] ?? -1;

            if ($weight >= 0) {
                return $weight;
            }
        }

        return null;
    }

    private function readBad(): ?ObjectId
    {
        $path = $this->gitDir . '/refs/bisect/bad';

        if (!is_file($path)) {
            return null;
        }

        $hex = trim((string) file_get_contents($path));

        return ObjectId::looksLikeHex($hex) ? ObjectId::fromHex($hex) : null;
    }

    /**
     * @return array<int, ObjectId>
     */
    private function readGoods(): array
    {
        $bisectDir = $this->gitDir . '/refs/bisect';
        $goods = [];

        if (!is_dir($bisectDir)) {
            return $goods;
        }

        foreach (scandir($bisectDir) as $file) {
            if (!str_starts_with($file, 'good-')) {
                continue;
            }

            $hex = trim((string) file_get_contents($bisectDir . '/' . $file));

            if (ObjectId::looksLikeHex($hex)) {
                $goods[] = ObjectId::fromHex($hex);
            }
        }

        return $goods;
    }

    /**
     * @param array<int, ObjectId> $goods
     */
    private function isKnownGood(ObjectId $commitId, array $goods, AncestryChecker $checker): bool
    {
        foreach ($goods as $good) {
            if ($commitId->equals($good) || $checker->isAncestor($commitId, $good)) {
                return true;
            }
        }

        return false;
    }

    private function appendCommandLog(string $command, ObjectId $commitId, ?callable $subjectResolver): void
    {
        $lines = sprintf(
            "# %s: [%s] %s\ngit bisect %s %s\n",
            $command,
            $commitId->hex,
            $this->describeCommit($commitId, $subjectResolver),
            $command,
            $commitId->hex,
        );

        file_put_contents($this->gitDir . '/BISECT_LOG', $lines, FILE_APPEND);
    }

    private function describeCommit(ObjectId $commitId, ?callable $subjectResolver): string
    {
        if ($subjectResolver !== null) {
            $subject = $subjectResolver($commitId);

            if (is_string($subject) && $subject !== '') {
                return $subject;
            }
        }

        $object = $this->objects->read($commitId);

        if (!$object instanceof Commit) {
            return $commitId->hex;
        }

        $lines = preg_split("/\r\n|\n|\r/", trim($object->message));

        return trim((string) ($lines[0] ?? $commitId->hex));
    }
}
