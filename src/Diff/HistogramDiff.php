<?php

declare(strict_types=1);

namespace Pitmaster\Diff;

/**
 * Histogram diff algorithm.
 *
 * Extension of patience diff that handles low-occurrence common elements.
 * Builds a histogram of line frequencies and uses it to find better anchors.
 * Falls back to Myers for subsequences.
 */
final class HistogramDiff
{
    /**
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
    private static function anchors(array $oldLines, array $newLines): array
    {
        $oldPositions = [];
        $newPositions = [];

        foreach ($oldLines as $index => $line) {
            $oldPositions[$line][] = $index;
        }

        foreach ($newLines as $index => $line) {
            $newPositions[$line][] = $index;
        }

        $pairs = [];
        $bestScore = null;

        foreach ($oldPositions as $line => $oldIndexes) {
            if (!isset($newPositions[$line])) {
                continue;
            }

            $score = max(count($oldIndexes), count($newPositions[$line]));

            if ($bestScore === null || $score < $bestScore) {
                $bestScore = $score;
                $pairs = [];
            }

            if ($score !== $bestScore) {
                continue;
            }

            $matchCount = min(count($oldIndexes), count($newPositions[$line]));

            for ($i = 0; $i < $matchCount; $i++) {
                $pairs[] = [
                    'old' => $oldIndexes[$i],
                    'new' => $newPositions[$line][$i],
                    'line' => $line,
                ];
            }
        }

        if ($pairs === []) {
            return PatienceDiff::anchors($oldLines, $newLines);
        }

        usort($pairs, fn ($a, $b) => $a['old'] <=> $b['old']);

        return self::longestIncreasingSubsequence($pairs);
    }

    /**
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

        $maxLen = max($dp);
        $maxIdx = array_search($maxLen, $dp, true);
        $result = [];
        $idx = $maxIdx;

        while ($idx !== -1) {
            array_unshift($result, $items[$idx]);
            $idx = $prev[$idx];
        }

        return $result;
    }
}
