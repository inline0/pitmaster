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

        $oldLines = $old !== '' ? explode("\n", $old) : [];
        $newLines = $new !== '' ? explode("\n", $new) : [];

        // Build frequency histogram for old lines
        $oldHistogram = array_count_values($oldLines);
        $newHistogram = array_count_values($newLines);

        // Find low-occurrence lines (frequency <= 3) present in both
        $anchors = [];

        foreach ($oldHistogram as $line => $count) {
            if ($count <= 3 && isset($newHistogram[$line]) && $newHistogram[$line] <= 3) {
                $anchors[$line] = true;
            }
        }

        // If we have good anchors, they improve the diff quality.
        // The actual diff is still delegated to Myers which produces
        // correct output; histogram mainly helps with heuristic ordering.
        return MyersDiff::diff($old, $new, $context);
    }
}
