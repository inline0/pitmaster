<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Oracle;

use Pitmaster\Support\Json;
use Pitmaster\Tests\Support\Workspace;

/**
 * Orchestrates the full pipeline: setup -> branch into oracle/actual -> compare.
 *
 * Setup runs once, producing an initial repo directory. Oracle and actual
 * each receive a copy of that exact repo state before running their own
 * scenario scripts and capture logic.
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
        $baseRepo = Workspace::createDirectory('pitmaster-scenario-base-');
        $oracleRepo = Workspace::createDirectory('pitmaster-scenario-oracle-');
        $actualRepo = Workspace::createDirectory('pitmaster-scenario-actual-');

        try {
            $this->runSetup($scenario, $baseRepo);
            $this->normalizePreparedRepo($baseRepo);
            $this->copyRepo($baseRepo, $oracleRepo);
            $this->copyRepo($baseRepo, $actualRepo);

            $oracleNeedsCapture = $refreshOracle || !$this->hasOracleOutput($scenario);
            $runtimeOracleNeeded = $scenario->runtimeExactMatch() !== [];

            if ($oracleNeedsCapture) {
                $oracleResult = $this->oracle->captureFromRepo($scenario, $oracleRepo, true);
            } elseif ($runtimeOracleNeeded) {
                $oracleResult = $this->oracle->captureFromRepo($scenario, $oracleRepo, true, false);
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

            $actualResult = $this->actual->captureFromRepo($scenario, $actualRepo, true);

            if (!$actualResult['success']) {
                return [
                    'pass' => false,
                    'oracle' => $oracleResult,
                    'actual' => $actualResult,
                    'comparison' => ['expectation' => ['pass' => false, 'failures' => ['actual-capture-failed']]],
                ];
            }

            $comparison = $this->comparator->compare(
                $scenario,
                $oracleResult['outputs'] ?? [],
                $actualResult['outputs'] ?? [],
            );
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
            Workspace::remove($baseRepo);
            Workspace::remove($oracleRepo);
            Workspace::remove($actualRepo);
        }
    }

    private function runSetup(Scenario $scenario, string $tempDir): void
    {
        $setupScript = $scenario->setupScriptPath();

        if (!is_file($setupScript)) {
            throw new \RuntimeException("Setup script not found: {$setupScript}");
        }

        $command = sprintf(
            'cd %s && PITMASTER_ROOT=%s bash %s 2>&1',
            escapeshellarg($tempDir),
            escapeshellarg($scenario->rootPath),
            escapeshellarg($setupScript),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException(
                "Setup script failed (exit {$exitCode}): " . implode("\n", $output)
            );
        }
    }

    private function copyRepo(string $sourceDir, string $targetDir): void
    {
        $command = sprintf(
            'cp -R %s/. %s',
            escapeshellarg($sourceDir),
            escapeshellarg($targetDir),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException("Failed to copy prepared repo from {$sourceDir} to {$targetDir}");
        }
    }

    private function hasOracleOutput(Scenario $scenario): bool
    {
        $oracleDir = $scenario->oracleDir();

        return is_dir($oracleDir) && is_file($oracleDir . '/objects.json');
    }

    private function normalizePreparedRepo(string $repoDir): void
    {
        if ($this->isGitRepository($repoDir)) {
            return;
        }

        foreach (@scandir($repoDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $candidate = $repoDir . '/' . $entry;

            if (!is_dir($candidate) || !$this->isGitRepository($candidate)) {
                continue;
            }

            $this->copyRepo($candidate, $repoDir);

            return;
        }
    }

    private function isGitRepository(string $path): bool
    {
        return is_dir($path . '/.git') || is_file($path . '/.git') || is_file($path . '/HEAD');
    }
}
