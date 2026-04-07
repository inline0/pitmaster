<?php

declare(strict_types=1);

namespace Pitmaster\Diff;

/**
 * Myers diff algorithm (line-level).
 *
 * Produces a minimal edit script between two sequences of lines.
 * Uses the standard LCS-based approach for correctness.
 */
final class MyersDiff
{
    private const CONTEXT_LINES = 3;

    /**
     * Diff two strings, producing hunks.
     *
     * @return array<int, Hunk>
     */
    public static function diff(string $old, string $new, int $context = self::CONTEXT_LINES): array
    {
        if ($old === $new) {
            return [];
        }

        // Split into lines. Remove trailing empty element from trailing newline,
        // since git diff operates on lines terminated by \n, not separated by \n.
        $oldLines = $old !== '' ? explode("\n", $old) : [];
        $newLines = $new !== '' ? explode("\n", $new) : [];

        if ($oldLines !== [] && end($oldLines) === '') {
            array_pop($oldLines);
        }

        if ($newLines !== [] && end($newLines) === '') {
            array_pop($newLines);
        }

        $lcs = self::lcs($oldLines, $newLines);

        return self::lcsToHunks($oldLines, $newLines, $lcs, $context);
    }

    /**
     * Compute longest common subsequence using DP.
     *
     * @param array<int, string> $a
     * @param array<int, string> $b
     * @return array<int, array{oldIdx: int, newIdx: int}>
     */
    private static function lcs(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);

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

        // Backtrace to find the LCS indices
        $result = [];
        $i = $n;
        $j = $m;

        while ($i > 0 && $j > 0) {
            if ($a[$i - 1] === $b[$j - 1]) {
                array_unshift($result, ['oldIdx' => $i - 1, 'newIdx' => $j - 1]);
                $i--;
                $j--;
            } elseif ($dp[$i - 1][$j] >= $dp[$i][$j - 1]) {
                $i--;
            } else {
                $j--;
            }
        }

        return $result;
    }

    /**
     * Convert LCS to hunks by finding the gaps (edits).
     *
     * @param array<int, string> $oldLines
     * @param array<int, string> $newLines
     * @param array<int, array{oldIdx: int, newIdx: int}> $lcs
     * @return array<int, Hunk>
     */
    private static function lcsToHunks(
        array $oldLines,
        array $newLines,
        array $lcs,
        int $context,
    ): array {
        // Build unified ops from LCS
        $ops = [];
        $oi = 0;
        $ni = 0;
        $li = 0;

        while ($oi < count($oldLines) || $ni < count($newLines)) {
            if ($li < count($lcs) && $oi === $lcs[$li]['oldIdx'] && $ni === $lcs[$li]['newIdx']) {
                $ops[] = ['type' => 'equal', 'line' => $oldLines[$oi]];
                $oi++;
                $ni++;
                $li++;
            } elseif ($li < count($lcs)) {
                // Before next LCS match: emit deletes then inserts
                while ($oi < $lcs[$li]['oldIdx']) {
                    $ops[] = ['type' => 'delete', 'line' => $oldLines[$oi]];
                    $oi++;
                }

                while ($ni < $lcs[$li]['newIdx']) {
                    $ops[] = ['type' => 'insert', 'line' => $newLines[$ni]];
                    $ni++;
                }
            } else {
                // After last LCS match
                while ($oi < count($oldLines)) {
                    $ops[] = ['type' => 'delete', 'line' => $oldLines[$oi]];
                    $oi++;
                }

                while ($ni < count($newLines)) {
                    $ops[] = ['type' => 'insert', 'line' => $newLines[$ni]];
                    $ni++;
                }
            }
        }

        // Check if there are any actual changes
        $hasChanges = false;

        foreach ($ops as $op) {
            if ($op['type'] !== 'equal') {
                $hasChanges = true;
                break;
            }
        }

        if (!$hasChanges) {
            return [];
        }

        // Group changes into hunks with context
        $changeIndices = [];

        foreach ($ops as $i => $op) {
            if ($op['type'] !== 'equal') {
                $changeIndices[] = $i;
            }
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

        // Build hunks
        $hunks = [];

        foreach ($groups as $group) {
            $first = $group[0];
            $last = $group[count($group) - 1];

            $start = max(0, $first - $context);
            $end = min(count($ops) - 1, $last + $context);

            $hunkLines = [];
            $oldCount = 0;
            $newCount = 0;

            // Calculate starting line numbers
            $oldStart = 1;
            $newStart = 1;

            for ($i = 0; $i < $start; $i++) {
                if ($ops[$i]['type'] === 'equal' || $ops[$i]['type'] === 'delete') {
                    $oldStart++;
                }

                if ($ops[$i]['type'] === 'equal' || $ops[$i]['type'] === 'insert') {
                    $newStart++;
                }
            }

            for ($i = $start; $i <= $end; $i++) {
                $op = $ops[$i];

                if ($op['type'] === 'equal') {
                    $hunkLines[] = ' ' . $op['line'];
                    $oldCount++;
                    $newCount++;
                } elseif ($op['type'] === 'delete') {
                    $hunkLines[] = '-' . $op['line'];
                    $oldCount++;
                } elseif ($op['type'] === 'insert') {
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

    /**
     * Check if content appears to be binary (contains NUL bytes in first 8KB).
     */
    public static function isBinary(string $content): bool
    {
        $sample = substr($content, 0, 8192);

        return str_contains($sample, "\0");
    }
}
