<?php

declare(strict_types=1);

namespace Pitmaster\Bench;

final class BenchmarkThresholds
{
    /**
     * @return array<string, array<string, int|float>>
     */
    public static function load(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true);

        if (!is_array($decoded)) {
            throw new \RuntimeException("Invalid benchmark threshold JSON: {$path}");
        }

        $thresholds = [];

        foreach ($decoded as $name => $values) {
            if (!is_string($name) || !is_array($values)) {
                continue;
            }

            $thresholds[$name] = array_filter(
                $values,
                static fn (mixed $value): bool => is_int($value) || is_float($value),
            );
        }

        return $thresholds;
    }

    /**
     * @param array<string, mixed> $report
     * @param array<string, array<string, int|float>> $thresholds
     * @return list<string>
     */
    public static function violations(array $report, array $thresholds): array
    {
        $violations = [];

        foreach (($report['results'] ?? []) as $result) {
            if (!is_array($result) || !isset($result['name'])) {
                continue;
            }

            $name = (string) $result['name'];
            $limits = $thresholds[$name] ?? null;

            if (!is_array($limits)) {
                continue;
            }

            $median = (float) ($result['duration_ms']['median'] ?? 0.0);
            $peakMemory = (float) ($result['peak_memory_bytes']['median'] ?? 0.0);

            if (isset($limits['max_median_ms']) && $median > (float) $limits['max_median_ms']) {
                $violations[] = sprintf(
                    '%s median %.3fms exceeded max %.3fms',
                    $name,
                    $median,
                    (float) $limits['max_median_ms'],
                );
            }

            if (isset($limits['max_peak_memory_bytes']) && $peakMemory > (float) $limits['max_peak_memory_bytes']) {
                $violations[] = sprintf(
                    '%s peak memory %.0f exceeded max %.0f',
                    $name,
                    $peakMemory,
                    (float) $limits['max_peak_memory_bytes'],
                );
            }
        }

        return $violations;
    }
}
