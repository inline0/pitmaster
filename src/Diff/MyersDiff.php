<?php

declare(strict_types=1);

namespace Pitmaster\Diff;

/**
 * Myers O(ND) diff algorithm (line-level).
 *
 * Implementation of Eugene W. Myers' "An O(ND) Difference Algorithm and Its
 * Variations" (Algorithmica 1986). This is the same algorithm git uses.
 *
 * O(ND) time and space where D is the edit distance. For similar files
 * (small D), this is dramatically faster than O(NM) LCS.
 */
final class MyersDiff
{
    private const CONTEXT_LINES = 3;

    /**
     * @return array<int, Hunk>
     */
    public static function diff(string $old, string $new, int $context = self::CONTEXT_LINES): array
    {
        if ($old === $new) {
            return [];
        }

        return self::diffLines(self::normalizeLines($old), self::normalizeLines($new), $context);
    }

    /**
     * @param array<int, string> $a
     * @param array<int, string> $b
     * @return array<int, Hunk>
     */
    public static function diffLines(array $a, array $b, int $context = self::CONTEXT_LINES): array
    {
        if ($a === $b) {
            return [];
        }

        $n = count($a);
        $m = count($b);

        if ($n === 0 && $m === 0) {
            return [];
        }

        if ($n === 0) {
            $ops = array_map(fn (string $l): array => ['type' => 'insert', 'line' => $l], $b);

            return self::opsToHunks($ops, $context);
        }

        if ($m === 0) {
            $ops = array_map(fn (string $l): array => ['type' => 'delete', 'line' => $l], $a);

            return self::opsToHunks($ops, $context);
        }

        $ops = self::opsFromLines($a, $b);

        return self::opsToHunks($ops, $context);
    }

    /**
     * @return array<int, string>
     */
    public static function normalizeLines(string $content): array
    {
        $lines = $content !== '' ? explode("\n", $content) : [];

        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }

    /**
     * @param array<int, string> $a
     * @param array<int, string> $b
     * @return array<int, array{type: string, line: string}>
     */
    public static function opsFromLines(array $a, array $b): array
    {
        return self::reorderDeletesBeforeInserts(self::myers($a, $b, count($a), count($b)));
    }

    /**
     * Myers O(ND) shortest edit script.
     *
     * @param array<int, string> $a Old lines
     * @param array<int, string> $b New lines
     * @return array<int, array{type: string, line: string}>
     */
    private static function myers(array $a, array $b, int $n, int $m): array
    {
        $max = $n + $m;
        $off = $max + 1;
        $v = array_fill(0, 2 * $max + 2, 0);
        $v[$off + 1] = 0;
        $trace = [];

        // Forward pass: find shortest edit distance d
        for ($d = 0; $d <= $max; $d++) {
            // Save V BEFORE this round modifies it.
            // trace[d] = V state that includes modifications from rounds 0..d-1.
            $trace[$d] = $v;

            for ($k = -$d; $k <= $d; $k += 2) {
                if ($k === -$d || ($k !== $d && $v[$off + $k - 1] < $v[$off + $k + 1])) {
                    $x = $v[$off + $k + 1]; // insert (from diagonal k+1)
                } else {
                    $x = $v[$off + $k - 1] + 1; // delete (from diagonal k-1)
                }

                $y = $x - $k;

                // Follow snake (diagonal = matching lines)
                while ($x < $n && $y < $m && $a[$x] === $b[$y]) {
                    $x++;
                    $y++;
                }

                $v[$off + $k] = $x;

                if ($x >= $n && $y >= $m) {
                    return self::backtrace($trace, $off, $d, $a, $b, $n, $m);
                }
            }
        }

        return [];
    }

    /**
     * Backtrace through V snapshots to reconstruct the edit script.
     *
     * Key insight: trace[d] = V saved at the START of round d = V after
     * rounds 0..d-1 finished. For backtrace at step s, we need V after
     * round s-1 finished = trace[s] (NOT trace[s-1]).
     *
     * @param array<int, array<int, int>> $trace
     * @param array<int, string> $a
     * @param array<int, string> $b
     * @return array<int, array{type: string, line: string}>
     */
    private static function backtrace(
        array $trace,
        int $off,
        int $d,
        array $a,
        array $b,
        int $n,
        int $m,
    ): array {
        $edits = [];
        $x = $n;
        $y = $m;

        for ($step = $d; $step > 0; $step--) {
            // V after round step-1 finished = trace[step]
            $v = $trace[$step];
            $k = $x - $y;

            // Which diagonal did we come from?
            if ($k === -$step || ($k !== $step && $v[$off + $k - 1] < $v[$off + $k + 1])) {
                $prevK = $k + 1;
                $isInsert = true;
            } else {
                $prevK = $k - 1;
                $isInsert = false;
            }

            $prevX = $v[$off + $prevK];
            $prevY = $prevX - $prevK;

            // Position after the edit
            $afterX = $isInsert ? $prevX : $prevX + 1;
            $afterY = $isInsert ? $prevY + 1 : $prevY;

            // Snake: equal lines from (afterX,afterY) to (x,y)
            while ($x > $afterX && $y > $afterY) {
                $x--;
                $y--;
                $edits[] = ['type' => 'equal', 'line' => $a[$x]];
            }

            // The edit step
            if ($isInsert) {
                $y--;
                $edits[] = ['type' => 'insert', 'line' => $b[$y]];
            } else {
                $x--;
                $edits[] = ['type' => 'delete', 'line' => $a[$x]];
            }
        }

        // Initial snake from (0,0) to first edit
        while ($x > 0 && $y > 0) {
            $x--;
            $y--;
            $edits[] = ['type' => 'equal', 'line' => $a[$x]];
        }

        return array_reverse($edits);
    }

    /**
     * Git convention: deletes before inserts in each change region.
     *
     * @param array<int, array{type: string, line: string}> $ops
     * @return array<int, array{type: string, line: string}>
     */
    private static function reorderDeletesBeforeInserts(array $ops): array
    {
        $result = [];
        $i = 0;
        $len = count($ops);

        while ($i < $len) {
            if ($ops[$i]['type'] === 'equal') {
                $result[] = $ops[$i];
                $i++;
                continue;
            }

            $deletes = [];
            $inserts = [];

            while ($i < $len && $ops[$i]['type'] !== 'equal') {
                if ($ops[$i]['type'] === 'delete') {
                    $deletes[] = $ops[$i];
                } else {
                    $inserts[] = $ops[$i];
                }

                $i++;
            }

            foreach ($deletes as $op) {
                $result[] = $op;
            }

            foreach ($inserts as $op) {
                $result[] = $op;
            }
        }

        return $result;
    }

    /**
     * @param array<int, array{type: string, line: string}> $ops
     * @return array<int, Hunk>
     */
    public static function opsToHunks(array $ops, int $context): array
    {
        $changeIndices = [];

        foreach ($ops as $i => $op) {
            if ($op['type'] !== 'equal') {
                $changeIndices[] = $i;
            }
        }

        if ($changeIndices === []) {
            return [];
        }

        $groups = [];
        $currentGroup = [$changeIndices[0]];

        for ($i = 1; $i < count($changeIndices); $i++) {
            if ($changeIndices[$i] - $changeIndices[$i - 1] <= 2 * $context + 1) {
                $currentGroup[] = $changeIndices[$i];
            } else {
                $groups[] = $currentGroup;
                $currentGroup = [$changeIndices[$i]];
            }
        }

        $groups[] = $currentGroup;
        $hunks = [];

        foreach ($groups as $group) {
            $first = $group[0];
            $last = $group[count($group) - 1];
            $start = max(0, $first - $context);
            $end = min(count($ops) - 1, $last + $context);

            $oldStart = 1;
            $newStart = 1;

            for ($i = 0; $i < $start; $i++) {
                if ($ops[$i]['type'] !== 'insert') {
                    $oldStart++;
                }

                if ($ops[$i]['type'] !== 'delete') {
                    $newStart++;
                }
            }

            $hunkLines = [];
            $oldCount = 0;
            $newCount = 0;

            for ($i = $start; $i <= $end; $i++) {
                $op = $ops[$i];

                if ($op['type'] === 'equal') {
                    $hunkLines[] = ' ' . $op['line'];
                    $oldCount++;
                    $newCount++;
                } elseif ($op['type'] === 'delete') {
                    $hunkLines[] = '-' . $op['line'];
                    $oldCount++;
                } else {
                    $hunkLines[] = '+' . $op['line'];
                    $newCount++;
                }
            }

            if ($hunkLines !== []) {
                $section = null;

                if ($start > 0 && isset($ops[$start - 1]) && $ops[$start - 1]['type'] === 'equal') {
                    $candidate = $ops[$start - 1]['line'];
                    $section = $candidate !== '' ? $candidate : null;
                }

                $hunks[] = new Hunk($oldStart, $oldCount, $newStart, $newCount, $hunkLines, $section);
            }
        }

        return $hunks;
    }

    public static function isBinary(string $content): bool
    {
        return str_contains(substr($content, 0, 8192), "\0");
    }
}
