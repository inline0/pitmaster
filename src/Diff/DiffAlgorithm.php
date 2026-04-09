<?php

declare(strict_types=1);

namespace Pitmaster\Diff;

final class DiffAlgorithm
{
    /**
     * @return array<int, Hunk>
     */
    public static function diff(
        string $old,
        string $new,
        string $algorithm = 'myers',
        int $context = 3,
    ): array {
        return match ($algorithm) {
            'myers', 'default' => MyersDiff::diff($old, $new, $context),
            'minimal' => MinimalDiff::diff($old, $new, $context),
            'patience' => PatienceDiff::diff($old, $new, $context),
            'histogram' => HistogramDiff::diff($old, $new, $context),
            default => throw new \InvalidArgumentException("Unknown diff algorithm: {$algorithm}"),
        };
    }
}
