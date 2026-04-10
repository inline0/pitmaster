<?php

declare(strict_types=1);

namespace Pitmaster\Bench;

final class BenchmarkComparison
{
    /**
     * @param array<string, mixed> $baseline
     * @param array<string, mixed> $candidate
     * @return array<string, mixed>
     */
    public static function compare(array $baseline, array $candidate): array
    {
        $baselineResults = self::indexResults($baseline);
        $comparisons = [];
        $regressions = 0;
        $wins = 0;

        foreach (($candidate['results'] ?? []) as $result) {
            if (!is_array($result) || !isset($result['name'])) {
                continue;
            }

            $name = (string) $result['name'];
            $base = $baselineResults[$name] ?? null;

            if (!is_array($base)) {
                $comparisons[] = [
                    'name' => $name,
                    'status' => 'new',
                    'candidate' => $result,
                ];
                continue;
            }

            $baseMedian = (float) ($base['duration_ms']['median'] ?? 0.0);
            $candidateMedian = (float) ($result['duration_ms']['median'] ?? 0.0);
            $deltaPct = $baseMedian > 0.0 ? (($candidateMedian - $baseMedian) / $baseMedian) * 100 : 0.0;
            $status = $deltaPct > 0.0 ? 'regression' : ($deltaPct < 0.0 ? 'win' : 'unchanged');

            if ($status === 'regression') {
                $regressions++;
            } elseif ($status === 'win') {
                $wins++;
            }

            $comparisons[] = [
                'name' => $name,
                'status' => $status,
                'baseline' => $base,
                'candidate' => $result,
                'delta_pct' => round($deltaPct, 2),
            ];
        }

        return [
            'summary' => [
                'cases' => count($comparisons),
                'wins' => $wins,
                'regressions' => $regressions,
            ],
            'comparisons' => $comparisons,
        ];
    }

    /**
     * @param array<string, mixed> $comparison
     */
    public static function toText(array $comparison, bool $onlyRegressions = false): string
    {
        $lines = [];

        foreach ($comparison['comparisons'] ?? [] as $item) {
            if (!is_array($item) || !isset($item['name'], $item['status'])) {
                continue;
            }

            if ($onlyRegressions && $item['status'] !== 'regression') {
                continue;
            }

            if ($item['status'] === 'new') {
                $lines[] = sprintf('%-32s new case', $item['name']);
                continue;
            }

            $baseline = (float) ($item['baseline']['duration_ms']['median'] ?? 0.0);
            $candidate = (float) ($item['candidate']['duration_ms']['median'] ?? 0.0);
            $deltaPct = (float) ($item['delta_pct'] ?? 0.0);

            $lines[] = sprintf(
                '%-32s %8.3fms -> %8.3fms  %+7.2f%%  %s',
                $item['name'],
                $baseline,
                $candidate,
                $deltaPct,
                $item['status'],
            );
        }

        return implode("\n", $lines) . ($lines !== [] ? "\n" : '');
    }

    /**
     * @param array<string, mixed> $report
     * @return array<string, array<string, mixed>>
     */
    private static function indexResults(array $report): array
    {
        $indexed = [];

        foreach (($report['results'] ?? []) as $result) {
            if (is_array($result) && isset($result['name'])) {
                $indexed[(string) $result['name']] = $result;
            }
        }

        return $indexed;
    }
}
