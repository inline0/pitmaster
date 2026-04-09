<?php

declare(strict_types=1);

namespace Pitmaster\Diff;

/**
 * Patience diff algorithm.
 *
 * Matches unique lines first, producing more structurally meaningful diffs.
 * Falls back to Myers for subsequences between unique matching lines.
 */
final class PatienceDiff
{
    /**
     * Diff two strings using patience algorithm.
     *
     * @return array<int, Hunk>
     */
    public static function diff(string $old, string $new, int $context = 3): array
    {
        if ($old === $new) {
            return [];
        }

        $ops = self::diffOps(
            MyersDiff::normalizeLines($old),
            MyersDiff::normalizeLines($new),
        );

        return MyersDiff::opsToHunks($ops, $context);
    }

    /**
     * Find lines that appear exactly once.
     *
     * @param array<int, string> $lines
     * @return array<string, int> line => index (only unique lines)
     */
    private static function findUnique(array $lines): array
    {
        $counts = [];
        $indices = [];

        foreach ($lines as $i => $line) {
            $counts[$line] = ($counts[$line] ?? 0) + 1;
            $indices[$line] = $i;
        }

        $unique = [];

        foreach ($counts as $line => $count) {
            if ($count === 1) {
                $unique[$line] = $indices[$line];
            }
        }

        return $unique;
    }

    /**
     * Find longest increasing subsequence by 'new' index.
     *
     * @param array<int, array{old: int, new: int, line: string}> $items
     * @return array<int, array{old: int, new: int, line: string}>
     */
    private static function longestIncreasingSubsequence(array $items): array
    {
        if ($items === []) {
            return [];
        }

        $n = count($items);
        $dp = array_fill(0, $n, 1);
        $prev = array_fill(0, $n, -1);

        for ($i = 1; $i < $n; $i++) {
            for ($j = 0; $j < $i; $j++) {
                if ($items[$j]['new'] < $items[$i]['new'] && $dp[$j] + 1 > $dp[$i]) {
                    $dp[$i] = $dp[$j] + 1;
                    $prev[$i] = $j;
                }
            }
        }

        // Find the end of the LIS
        $maxLen = max($dp);
        $maxIdx = array_search($maxLen, $dp, true);

        // Reconstruct
        $result = [];
        $idx = $maxIdx;

        while ($idx !== -1) {
            array_unshift($result, $items[$idx]);
            $idx = $prev[$idx];
        }

        return $result;
    }

    /**
     * @param array<int, string> $oldLines
     * @param array<int, string> $newLines
     * @return array<int, array{type: string, line: string}>
     */
    private static function diffOps(array $oldLines, array $newLines): array
    {
        if ($oldLines === $newLines) {
            return array_map(
                static fn (string $line): array => ['type' => 'equal', 'line' => $line],
                $oldLines,
            );
        }

        if ($oldLines === [] || $newLines === []) {
            return MyersDiff::opsFromLines($oldLines, $newLines);
        }

        $anchors = self::anchors($oldLines, $newLines);

        if ($anchors === []) {
            return MyersDiff::opsFromLines($oldLines, $newLines);
        }

        $ops = [];
        $oldStart = 0;
        $newStart = 0;

        foreach ($anchors as $anchor) {
            $oldIndex = $anchor['old'];
            $newIndex = $anchor['new'];
            $ops = array_merge(
                $ops,
                self::diffOps(
                    array_slice($oldLines, $oldStart, $oldIndex - $oldStart),
                    array_slice($newLines, $newStart, $newIndex - $newStart),
                ),
            );
            $ops[] = ['type' => 'equal', 'line' => $anchor['line']];
            $oldStart = $oldIndex + 1;
            $newStart = $newIndex + 1;
        }

        return array_merge(
            $ops,
            self::diffOps(
                array_slice($oldLines, $oldStart),
                array_slice($newLines, $newStart),
            ),
        );
    }

    /**
     * @param array<int, string> $oldLines
     * @param array<int, string> $newLines
     * @return array<int, array{old: int, new: int, line: string}>
     */
    public static function anchors(array $oldLines, array $newLines): array
    {
        $uniqueOld = self::findUnique($oldLines);
        $uniqueNew = self::findUnique($newLines);
        $commonUnique = [];

        foreach ($uniqueOld as $line => $oldIdx) {
            if (isset($uniqueNew[$line])) {
                $commonUnique[] = ['old' => $oldIdx, 'new' => $uniqueNew[$line], 'line' => $line];
            }
        }

        usort($commonUnique, fn ($a, $b) => $a['old'] <=> $b['old']);

        return self::longestIncreasingSubsequence($commonUnique);
    }
}
