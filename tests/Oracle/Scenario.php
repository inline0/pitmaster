<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Oracle;

use Pitmaster\Support\Json;

/**
 * A test scenario definition (mirrors php-browser's Fixture class).
 *
 * Each scenario lives in scenarios/<category>/<name>/ with a scenario.json.
 */
final readonly class Scenario
{
    public function __construct(
        public string $name,
        public string $rootPath,
        public array $definition,
    ) {
    }

    public function category(): string
    {
        return (string) ($this->definition['category'] ?? '');
    }

    public function description(): string
    {
        return (string) ($this->definition['description'] ?? '');
    }

    /**
     * Operations this scenario tests (e.g., ["diff", "status"]).
     *
     * @return array<int, string>
     */
    public function operations(): array
    {
        return (array) ($this->definition['operations'] ?? []);
    }

    /**
     * Git commands to capture for oracle.
     *
     * @return array<string, string>
     */
    public function oracleCommands(): array
    {
        return (array) ($this->definition['oracle_commands'] ?? []);
    }

    /**
     * Commands to run against Pitmaster-produced state for exact output parity.
     *
     * @return array<string, string>
     */
    public function actualCommands(): array
    {
        return (array) ($this->definition['actual_commands'] ?? []);
    }

    /**
     * Expectation rules.
     *
     * @return array<string, mixed>
     */
    public function expectations(): array
    {
        return (array) ($this->definition['expectations'] ?? []);
    }

    /**
     * Which outputs require exact match.
     *
     * @return array<int, string>
     */
    public function exactMatch(): array
    {
        return array_values(array_unique(array_merge(
            (array) ($this->expectations()['exact_match'] ?? []),
            $this->runtimeExactMatch(),
        )));
    }

    /**
     * Outputs that should be compared against a fresh oracle capture on each run
     * instead of the committed oracle snapshot.
     *
     * @return array<int, string>
     */
    public function runtimeExactMatch(): array
    {
        return (array) ($this->expectations()['runtime_exact_match'] ?? []);
    }

    /**
     * Whether fsck must be clean.
     */
    public function fsckClean(): bool
    {
        return (bool) ($this->expectations()['fsck_clean'] ?? true);
    }

    public function scenarioDir(): string
    {
        return $this->rootPath . '/scenarios/' . $this->name;
    }

    public function scenarioJsonPath(): string
    {
        return $this->scenarioDir() . '/scenario.json';
    }

    public function setupScriptPath(): string
    {
        return $this->scenarioDir() . '/setup.sh';
    }

    public function oracleScriptPath(): string
    {
        return $this->scenarioDir() . '/oracle.sh';
    }

    public function actualScriptPath(): string
    {
        return $this->scenarioDir() . '/actual.php';
    }

    public function oracleDir(): string
    {
        return $this->scenarioDir() . '/oracle';
    }

    public function actualDir(): string
    {
        return $this->scenarioDir() . '/actual';
    }

    public function reportsDir(): string
    {
        return $this->scenarioDir() . '/reports';
    }

    public function reportPath(): string
    {
        return $this->reportsDir() . '/comparison.json';
    }

    /**
     * Evaluate a comparison report against expectations.
     *
     * @param array<string, mixed> $report
     * @return array{pass: bool, failures: array<int, string>}
     */
    public function evaluateReport(array $report): array
    {
        $failures = [];

        foreach ($this->exactMatch() as $output) {
            $key = $output . '_match';

            if (!isset($report[$key]) || $report[$key] !== true) {
                $failures[] = "exact-match-failed:{$output}";
            }
        }

        if ($this->fsckClean() && isset($report['fsck_clean']) && $report['fsck_clean'] !== true) {
            $failures[] = 'fsck-not-clean';
        }

        return [
            'pass' => $failures === [],
            'failures' => $failures,
        ];
    }
}
