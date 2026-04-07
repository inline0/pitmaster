<?php

declare(strict_types=1);

namespace Pitmaster\Diff;

/**
 * Myers diff algorithm (line-level).
 *
 * Produces a minimal edit script between two sequences of lines.
 * This is the same algorithm git uses by default.
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

        $oldLines = $old !== '' ? explode("\n", $old) : [];
        $newLines = $new !== '' ? explode("\n", $new) : [];

        $editScript = self::computeEditScript($oldLines, $newLines);

        return self::editScriptToHunks($editScript, $oldLines, $newLines, $context);
    }

    /**
     * Compute the edit script using Myers algorithm.
     *
     * Returns array of ['type' => 'equal'|'delete'|'insert', 'old' => int, 'new' => int]
     *
     * @param array<int, string> $a
     * @param array<int, string> $b
     * @return array<int, array{type: string, oldIdx: int, newIdx: int}>
     */
    private static function computeEditScript(array $a, array $b): array
    {
        $n = count($a);
        $m = count($b);
        $max = $n + $m;

        if ($max === 0) {
            return [];
        }

        // V array: maps diagonal k to farthest reaching x
        $v = array_fill(-$max, 2 * $max + 1, 0);
        $v[1] = 0;
        $trace = [];

        for ($d = 0; $d <= $max; $d++) {
            $trace[$d] = $v;

            for ($k = -$d; $k <= $d; $k += 2) {
                if ($k === -$d || ($k !== $d && $v[$k - 1] < $v[$k + 1])) {
                    $x = $v[$k + 1];
                } else {
                    $x = $v[$k - 1] + 1;
                }

                $y = $x - $k;

                // Follow diagonal (equal lines)
                while ($x < $n && $y < $m && $a[$x] === $b[$y]) {
                    $x++;
                    $y++;
                }

                $v[$k] = $x;

                if ($x >= $n && $y >= $m) {
                    return self::backtrace($trace, $a, $b, $d);
                }
            }
        }

        return [];
    }

    /**
     * Backtrace through the edit graph to produce operations.
     *
     * @param array<int, array<int, int>> $trace
     * @param array<int, string> $a
     * @param array<int, string> $b
     * @return array<int, array{type: string, oldIdx: int, newIdx: int}>
     */
    private static function backtrace(array $trace, array $a, array $b, int $d): array
    {
        $ops = [];
        $x = count($a);
        $y = count($b);

        for ($step = $d; $step > 0; $step--) {
            $v = $trace[$step - 1];
            $k = $x - $y;

            if ($k === -$step || ($k !== $step && $v[$k - 1] < $v[$k + 1])) {
                $prevK = $k + 1;
            } else {
                $prevK = $k - 1;
            }

            $prevX = $v[$prevK];
            $prevY = $prevX - $prevK;

            // Diagonal moves (equal)
            while ($x > $prevX + ($prevK !== $k ? 0 : 1) && $y > $prevY + ($prevK !== $k ? 1 : 0)) {
                $x--;
                $y--;
                array_unshift($ops, ['type' => 'equal', 'oldIdx' => $x, 'newIdx' => $y]);
            }

            if ($step > 0) {
                if ($prevK === $k + 1) {
                    // Insert
                    $y--;
                    array_unshift($ops, ['type' => 'insert', 'oldIdx' => $x, 'newIdx' => $y]);
                } else {
                    // Delete
                    $x--;
                    array_unshift($ops, ['type' => 'delete', 'oldIdx' => $x, 'newIdx' => $y]);
                }
            }
        }

        // Remaining diagonal
        while ($x > 0 && $y > 0) {
            $x--;
            $y--;
            array_unshift($ops, ['type' => 'equal', 'oldIdx' => $x, 'newIdx' => $y]);
        }

        return $ops;
    }

    /**
     * Convert edit script to hunks with context.
     *
     * @param array<int, array{type: string, oldIdx: int, newIdx: int}> $ops
     * @param array<int, string> $oldLines
     * @param array<int, string> $newLines
     * @return array<int, Hunk>
     */
    private static function editScriptToHunks(array $ops, array $oldLines, array $newLines, int $context): array
    {
        if ($ops === []) {
            // All added or all deleted
            if ($oldLines === [] && $newLines !== []) {
                $lines = array_map(fn (string $l) => "+{$l}", $newLines);

                return [new Hunk(0, 0, 1, count($newLines), $lines)];
            }

            if ($newLines === [] && $oldLines !== []) {
                $lines = array_map(fn (string $l) => "-{$l}", $oldLines);

                return [new Hunk(1, count($oldLines), 0, 0, $lines)];
            }

            return [];
        }

        // Find change regions and add context
        $changes = [];

        foreach ($ops as $i => $op) {
            if ($op['type'] !== 'equal') {
                $changes[] = $i;
            }
        }

        if ($changes === []) {
            return [];
        }

        // Group changes that are close together (within 2*context lines)
        $groups = [];
        $currentGroup = [$changes[0]];

        for ($i = 1; $i < count($changes); $i++) {
            if ($changes[$i] - $changes[$i - 1] <= 2 * $context + 1) {
                $currentGroup[] = $changes[$i];
            } else {
                $groups[] = $currentGroup;
                $currentGroup = [$changes[$i]];
            }
        }

        $groups[] = $currentGroup;

        // Build hunks from groups
        $hunks = [];

        foreach ($groups as $group) {
            $first = $group[0];
            $last = $group[count($group) - 1];

            $start = max(0, $first - $context);
            $end = min(count($ops) - 1, $last + $context);

            $hunkLines = [];
            $oldStart = null;
            $newStart = null;
            $oldCount = 0;
            $newCount = 0;

            for ($i = $start; $i <= $end; $i++) {
                $op = $ops[$i];

                if ($oldStart === null) {
                    $oldStart = $op['oldIdx'] + 1;
                    $newStart = $op['newIdx'] + 1;
                }

                if ($op['type'] === 'equal') {
                    $hunkLines[] = ' ' . $oldLines[$op['oldIdx']];
                    $oldCount++;
                    $newCount++;
                } elseif ($op['type'] === 'delete') {
                    $hunkLines[] = '-' . $oldLines[$op['oldIdx']];
                    $oldCount++;
                } elseif ($op['type'] === 'insert') {
                    $hunkLines[] = '+' . $newLines[$op['newIdx']];
                    $newCount++;
                }
            }

            $hunks[] = new Hunk(
                oldStart: $oldStart ?? 1,
                oldCount: $oldCount,
                newStart: $newStart ?? 1,
                newCount: $newCount,
                lines: $hunkLines,
            );
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
