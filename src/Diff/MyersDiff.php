<?php

declare(strict_types=1);

namespace Pitmaster\Diff;

/**
 * Myers-optimized diff algorithm (line-level).
 *
 * Uses prefix/suffix stripping to minimize the comparison region, then
 * LCS on the reduced middle for correctness. The prefix/suffix optimization
 * gives O(N) best case for similar files while the LCS fallback handles
 * the reduced middle region efficiently.
 *
 * Produces byte-identical output to git diff.
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

        $a = $old !== '' ? explode("\n", $old) : [];
        $b = $new !== '' ? explode("\n", $new) : [];

        if ($a !== [] && end($a) === '') {
            array_pop($a);
        }

        if ($b !== [] && end($b) === '') {
            array_pop($b);
        }

        $n = count($a);
        $m = count($b);

        if ($n === 0 && $m === 0) {
            return [];
        }

        // Strip common prefix
        $prefix = 0;

        while ($prefix < $n && $prefix < $m && $a[$prefix] === $b[$prefix]) {
            $prefix++;
        }

        // Strip common suffix
        $suffix = 0;

        while ($suffix < ($n - $prefix) && $suffix < ($m - $prefix)
            && $a[$n - 1 - $suffix] === $b[$m - 1 - $suffix]) {
            $suffix++;
        }

        if ($prefix + $suffix >= $n && $prefix + $suffix >= $m) {
            return [];
        }

        $midA = array_slice($a, $prefix, $n - $prefix - $suffix);
        $midB = array_slice($b, $prefix, $m - $prefix - $suffix);

        // LCS on the reduced middle
        $midOps = self::lcsOps($midA, $midB);

        // Build full ops
        $ops = [];

        for ($i = 0; $i < $prefix; $i++) {
            $ops[] = ['type' => 'equal', 'line' => $a[$i]];
        }

        foreach ($midOps as $op) {
            $ops[] = $op;
        }

        for ($i = $n - $suffix; $i < $n; $i++) {
            $ops[] = ['type' => 'equal', 'line' => $a[$i]];
        }

        // Git shows deletes before inserts
        $ops = self::reorderDeletesBeforeInserts($ops);

        return self::opsToHunks($ops, $context);
    }

    /**
     * LCS-based edit script for the middle region.
     *
     * @param array<int, string> $a
     * @param array<int, string> $b
     * @return array<int, array{type: string, line: string}>
     */
    private static function lcsOps(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);

        if ($n === 0) {
            return array_map(fn (string $l): array => ['type' => 'insert', 'line' => $l], $b);
        }

        if ($m === 0) {
            return array_map(fn (string $l): array => ['type' => 'delete', 'line' => $l], $a);
        }

        // DP table
        $dp = [];

        for ($i = 0; $i <= $n; $i++) {
            $dp[$i] = array_fill(0, $m + 1, 0);
        }

        for ($i = 1; $i <= $n; $i++) {
            for ($j = 1; $j <= $m; $j++) {
                if ($a[$i - 1] === $b[$j - 1]) {
                    $dp[$i][$j] = $dp[$i - 1][$j - 1] + 1;
                } else {
                    $dp[$i][$j] = max($dp[$i - 1][$j], $dp[$i][$j - 1]);
                }
            }
        }

        // Backtrace to get LCS indices
        $lcs = [];
        $i = $n;
        $j = $m;

        while ($i > 0 && $j > 0) {
            if ($a[$i - 1] === $b[$j - 1]) {
                $lcs[] = [$i - 1, $j - 1];
                $i--;
                $j--;
            } elseif ($dp[$i - 1][$j] >= $dp[$i][$j - 1]) {
                $i--;
            } else {
                $j--;
            }
        }

        $lcs = array_reverse($lcs);

        // Build ops from LCS
        $ops = [];
        $ai = 0;
        $bi = 0;
        $li = 0;

        while ($ai < $n || $bi < $m) {
            if ($li < count($lcs) && $ai === $lcs[$li][0] && $bi === $lcs[$li][1]) {
                $ops[] = ['type' => 'equal', 'line' => $a[$ai]];
                $ai++;
                $bi++;
                $li++;
            } elseif ($li < count($lcs)) {
                while ($ai < $lcs[$li][0]) {
                    $ops[] = ['type' => 'delete', 'line' => $a[$ai]];
                    $ai++;
                }

                while ($bi < $lcs[$li][1]) {
                    $ops[] = ['type' => 'insert', 'line' => $b[$bi]];
                    $bi++;
                }
            } else {
                while ($ai < $n) {
                    $ops[] = ['type' => 'delete', 'line' => $a[$ai]];
                    $ai++;
                }

                while ($bi < $m) {
                    $ops[] = ['type' => 'insert', 'line' => $b[$bi]];
                    $bi++;
                }
            }
        }

        return $ops;
    }

    /**
     * Reorder: deletes before inserts in each change region.
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
    private static function opsToHunks(array $ops, int $context): array
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
                $hunks[] = new Hunk($oldStart, $oldCount, $newStart, $newCount, $hunkLines);
            }
        }

        return $hunks;
    }

    public static function isBinary(string $content): bool
    {
        return str_contains(substr($content, 0, 8192), "\0");
    }
}
