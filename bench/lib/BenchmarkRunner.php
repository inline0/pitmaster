<?php

declare(strict_types=1);

namespace Pitmaster\Bench;

final class BenchmarkRunner
{
    /**
     * @param list<BenchmarkCase> $cases
     * @return array<string, mixed>
     */
    public function run(BenchmarkRuntime $runtime, array $cases, ?int $runsOverride = null, ?int $warmupsOverride = null): array
    {
        $results = [];
        $suiteStart = hrtime(true);
        $caseCount = count($cases);

        foreach ($cases as $index => $case) {
            $runs = $runsOverride ?? $case->defaultRuns;
            $warmups = $warmupsOverride ?? $case->defaultWarmups;
            $this->writeProgress(sprintf(
                "[%d/%d] %s (warmups=%d runs=%d)\n",
                $index + 1,
                $caseCount,
                $case->name,
                $warmups,
                $runs,
            ));
            $suiteContext = $case->setUp($runtime);

            try {
                for ($i = 0; $i < $warmups; $i++) {
                    $iterationContext = $case->prepareIteration($runtime, $suiteContext, $i);

                    try {
                        $case->run($runtime, $suiteContext, $iterationContext);
                    } finally {
                        $case->cleanupIteration($runtime, $suiteContext, $iterationContext);
                    }
                }

                $durations = [];
                $memoryPeaks = [];

                for ($i = 0; $i < $runs; $i++) {
                    gc_collect_cycles();

                    if (function_exists('memory_reset_peak_usage')) {
                        memory_reset_peak_usage();
                    }

                    $baselineMemory = memory_get_usage(true);
                    $iterationContext = $case->prepareIteration($runtime, $suiteContext, $i);
                    $start = hrtime(true);

                    try {
                        $case->run($runtime, $suiteContext, $iterationContext);
                    } finally {
                        $elapsed = (hrtime(true) - $start) / 1_000_000;
                        $durations[] = round($elapsed, 3);
                        $peak = memory_get_peak_usage(true);
                        $memoryPeaks[] = max(0, $peak - $baselineMemory);
                        $case->cleanupIteration($runtime, $suiteContext, $iterationContext);
                    }
                }

                $results[] = [
                    'name' => $case->name,
                    'description' => $case->description,
                    'groups' => $case->groups,
                    'metadata' => $case->metadata,
                    'runs' => $runs,
                    'warmups' => $warmups,
                    'duration_ms' => BenchmarkStats::summarize($durations),
                    'peak_memory_bytes' => BenchmarkStats::summarize($memoryPeaks),
                ];
                $this->writeProgress(sprintf(
                    "  done %s median=%.3fms peak=%d bytes\n",
                    $case->name,
                    (float) $results[array_key_last($results)]['duration_ms']['median'],
                    (int) $results[array_key_last($results)]['peak_memory_bytes']['median'],
                ));
            } finally {
                $case->tearDown($runtime, $suiteContext);
            }
        }

        return [
            'environment' => BenchmarkEnvironment::capture(),
            'summary' => [
                'cases' => count($results),
                'total_duration_ms' => round((hrtime(true) - $suiteStart) / 1_000_000, 3),
            ],
            'results' => $results,
        ];
    }

    private function writeProgress(string $message): void
    {
        $enabled = getenv('PITMASTER_BENCH_PROGRESS');

        if ($enabled === false || $enabled === '' || $enabled === '0') {
            return;
        }

        fwrite(STDERR, $message);
    }
}
