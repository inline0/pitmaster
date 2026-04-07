<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Oracle;

use Pitmaster\Support\Json;
use RuntimeException;

/**
 * Orchestrates the full pipeline: setup -> oracle -> actual -> compare.
 *
 * Setup runs once, producing a repo directory. Oracle captures git's view,
 * actual captures Pitmaster's view. Both read the same repo state.
 */
final class ScenarioRunner
{
    private readonly OracleCapture $oracle;
    private readonly ActualCapture $actual;
    private readonly ScenarioComparator $comparator;

    public function __construct()
    {
        $this->oracle = new OracleCapture();
        $this->actual = new ActualCapture();
        $this->comparator = new ScenarioComparator();
    }

    /**
     * Run the full pipeline for a scenario.
     *
     * @return array{
     *   pass: bool,
     *   oracle: array{success: bool, errors: array<int, string>},
     *   actual: array{success: bool, errors: array<int, string>},
     *   comparison: array<string, mixed>
     * }
     */
    public function run(Scenario $scenario, bool $refreshOracle = false): array
    {
        // Create repo once, share between oracle and actual
        $tempDir = sys_get_temp_dir() . '/pitmaster-scenario-' . bin2hex(random_bytes(8));
        mkdir($tempDir, 0777, true);

        try {
            // Run setup
            $this->runSetup($scenario, $tempDir);

            // Step 1: Oracle capture
            $oracleNeedsCapture = $refreshOracle || !$this->hasOracleOutput($scenario);

            if ($oracleNeedsCapture) {
                $oracleResult = $this->oracle->captureFromRepo($scenario, $tempDir);
            } else {
                $oracleResult = ['success' => true, 'outputs' => [], 'errors' => []];
            }

            if (!$oracleResult['success']) {
                return [
                    'pass' => false,
                    'oracle' => $oracleResult,
                    'actual' => ['success' => false, 'outputs' => [], 'errors' => ['skipped: oracle failed']],
                    'comparison' => ['expectation' => ['pass' => false, 'failures' => ['oracle-capture-failed']]],
                ];
            }

            // Step 2: Actual capture (same repo)
            $actualResult = $this->actual->captureFromRepo($scenario, $tempDir);

            if (!$actualResult['success']) {
                return [
                    'pass' => false,
                    'oracle' => $oracleResult,
                    'actual' => $actualResult,
                    'comparison' => ['expectation' => ['pass' => false, 'failures' => ['actual-capture-failed']]],
                ];
            }

            // Step 3: Compare
            $comparison = $this->comparator->compare($scenario);

            // Write report
            $reportsDir = $scenario->reportsDir();

            if (!is_dir($reportsDir)) {
                mkdir($reportsDir, 0777, true);
            }

            Json::encodeFile($scenario->reportPath(), $comparison);

            $pass = ($comparison['expectation']['pass'] ?? false) === true;

            return [
                'pass' => $pass,
                'oracle' => $oracleResult,
                'actual' => $actualResult,
                'comparison' => $comparison,
            ];
        } finally {
            exec(sprintf('rm -rf %s', escapeshellarg($tempDir)));
        }
    }

    private function runSetup(Scenario $scenario, string $tempDir): void
    {
        $setupScript = $scenario->setupScriptPath();

        if (!is_file($setupScript)) {
            throw new RuntimeException("Setup script not found: {$setupScript}");
        }

        $command = sprintf(
            'cd %s && bash %s 2>&1',
            escapeshellarg($tempDir),
            escapeshellarg($setupScript),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                "Setup script failed (exit {$exitCode}): " . implode("\n", $output)
            );
        }
    }

    private function hasOracleOutput(Scenario $scenario): bool
    {
        $oracleDir = $scenario->oracleDir();

        return is_dir($oracleDir) && is_file($oracleDir . '/objects.json');
    }
}
