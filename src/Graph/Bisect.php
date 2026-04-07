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
    public function start(ObjectId $bad, ObjectId $good): ObjectId
    {
        $bisectDir = $this->gitDir . '/refs/bisect';

        if (!is_dir($bisectDir)) {
            mkdir($bisectDir, 0777, true);
        }

        file_put_contents($this->gitDir . '/BISECT_START', $bad->hex . "\n");
        file_put_contents($bisectDir . '/bad', $bad->hex . "\n");
        file_put_contents($bisectDir . '/good-' . substr($good->hex, 0, 12), $good->hex . "\n");

        $log = "# bad: {$bad->hex}\n# good: {$good->hex}\n";
        file_put_contents($this->gitDir . '/BISECT_LOG', $log);

        return $this->findMidpoint($bad, $good);
    }

    /**
     * Mark a commit as good and get the next commit to test.
     */
    public function good(ObjectId $commitId): ?ObjectId
    {
        $bisectDir = $this->gitDir . '/refs/bisect';
        file_put_contents($bisectDir . '/good-' . substr($commitId->hex, 0, 12), $commitId->hex . "\n");

        $this->appendLog("good {$commitId->hex}");

        $bad = $this->readBad();
        $goods = $this->readGoods();

        if ($bad === null) {
            return null;
        }

        // Find midpoint between bad and closest good
        $closestGood = $this->findClosestGood($bad, $goods);

        if ($closestGood === null) {
            return null;
        }

        return $this->findMidpoint($bad, $closestGood);
    }

    /**
     * Mark a commit as bad and get the next commit to test.
     */
    public function bad(ObjectId $commitId): ?ObjectId
    {
        $bisectDir = $this->gitDir . '/refs/bisect';
        file_put_contents($bisectDir . '/bad', $commitId->hex . "\n");

        $this->appendLog("bad {$commitId->hex}");

        $goods = $this->readGoods();

        if ($goods === []) {
            return null;
        }

        $closestGood = $this->findClosestGood($commitId, $goods);

        if ($closestGood === null) {
            return null;
        }

        return $this->findMidpoint($commitId, $closestGood);
    }

    /**
     * Reset bisect state.
     */
    public function reset(): void
    {
        @unlink($this->gitDir . '/BISECT_START');
        @unlink($this->gitDir . '/BISECT_LOG');

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
     * Find the midpoint commit between bad and good.
     */
    private function findMidpoint(ObjectId $bad, ObjectId $good): ObjectId
    {
        $walker = new CommitWalker($this->objects);
        $commits = $walker->walk($bad, 1000);

        // Find the path from bad to good
        $path = [];
        $foundGood = false;

        foreach ($commits as $commit) {
            $path[] = $commit;

            if ($commit->id->equals($good)) {
                $foundGood = true;
                break;
            }
        }

        if (!$foundGood || count($path) <= 2) {
            return $path[0]->id ?? $bad;
        }

        // Return the midpoint
        $mid = (int) (count($path) / 2);

        return $path[$mid]->id;
    }

    private function readBad(): ?ObjectId
    {
        $path = $this->gitDir . '/refs/bisect/bad';

        if (!is_file($path)) {
            return null;
        }

        $hex = trim((string) file_get_contents($path));

        return strlen($hex) === 40 ? ObjectId::fromHex($hex) : null;
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

            if (strlen($hex) === 40 && ctype_xdigit($hex)) {
                $goods[] = ObjectId::fromHex($hex);
            }
        }

        return $goods;
    }

    /**
     * @param array<int, ObjectId> $goods
     */
    private function findClosestGood(ObjectId $bad, array $goods): ?ObjectId
    {
        if ($goods === []) {
            return null;
        }

        // Simple: return the first good commit
        return $goods[0];
    }

    private function appendLog(string $line): void
    {
        file_put_contents($this->gitDir . '/BISECT_LOG', $line . "\n", FILE_APPEND);
    }
}
