<?php

declare(strict_types=1);

namespace Pitmaster\Diff;

/**
 * Minimal diff: produces the smallest possible edit script.
 *
 * Uses Myers with full backtracking to ensure minimality.
 * In practice, Myers already produces minimal results, so this
 * delegates directly.
 */
final class MinimalDiff
{
    /**
     * @return array<int, Hunk>
     */
    public static function diff(string $old, string $new, int $context = 3): array
    {
        return MyersDiff::diffLines(
            MyersDiff::normalizeLines($old),
            MyersDiff::normalizeLines($new),
            $context,
        );
    }
}
