<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Oracle;

use Pitmaster\Support\Json;

/**
 * Compares oracle output against actual (Pitmaster) output.
 *
 * Supports different matching modes: exact, semantic, fsck_clean.
 */
final class ScenarioComparator
{
    /**
     * Compare oracle vs actual for a scenario.
     *
     * @return array<string, mixed>
     */
    public function compare(Scenario $scenario, array $oracleOutputs = [], array $actualOutputs = []): array
    {
        $report = [];
        $oracleDir = $scenario->oracleDir();
        $actualDir = $scenario->actualDir();
        $runtimeExact = array_fill_keys($scenario->runtimeExactMatch(), true);
        $runtimeExactMeta = array_fill_keys($scenario->runtimeExactMetaMatch(), true);

        if (isset($runtimeExact['objects'], $oracleOutputs['objects'], $actualOutputs['objects'])) {
            $oracleObjects = $oracleOutputs['objects'];
            $actualObjects = $actualOutputs['objects'];
            $report['objects_match'] = $this->compareObjects($oracleObjects, $actualObjects);
            $report['objects_detail'] = $this->objectsDiff($oracleObjects, $actualObjects);
        } elseif (is_file($oracleDir . '/objects.json') && is_file($actualDir . '/objects.json')) {
            $oracleObjects = Json::decodeFile($oracleDir . '/objects.json');
            $actualObjects = Json::decodeFile($actualDir . '/objects.json');
            $report['objects_match'] = $this->compareObjects($oracleObjects, $actualObjects);
            $report['objects_detail'] = $this->objectsDiff($oracleObjects, $actualObjects);
        }

        if (isset($runtimeExact['refs'], $oracleOutputs['refs'], $actualOutputs['refs'])) {
            $oracleRefs = $oracleOutputs['refs'];
            $actualRefs = $actualOutputs['refs'];
            $report['refs_match'] = $this->compareRefs($oracleRefs, $actualRefs);
            $report['refs_detail'] = $this->refsDiff($oracleRefs, $actualRefs);
        } elseif (is_file($oracleDir . '/refs.json') && is_file($actualDir . '/refs.json')) {
            $oracleRefs = Json::decodeFile($oracleDir . '/refs.json');
            $actualRefs = Json::decodeFile($actualDir . '/refs.json');
            $report['refs_match'] = $this->compareRefs($oracleRefs, $actualRefs);
            $report['refs_detail'] = $this->refsDiff($oracleRefs, $actualRefs);
        }

        if (isset($runtimeExact['log'], $oracleOutputs['log'], $actualOutputs['log'])) {
            $oracleLog = $oracleOutputs['log'];
            $actualLog = $actualOutputs['log'];
            $report['log_match'] = $this->compareLog($oracleLog, $actualLog);
            $report['log_detail'] = $this->logDiff($oracleLog, $actualLog);
        } elseif (is_file($oracleDir . '/log.json') && is_file($actualDir . '/log.json')) {
            $oracleLog = Json::decodeFile($oracleDir . '/log.json');
            $actualLog = Json::decodeFile($actualDir . '/log.json');
            $report['log_match'] = $this->compareLog($oracleLog, $actualLog);
            $report['log_detail'] = $this->logDiff($oracleLog, $actualLog);
        }

        foreach ($scenario->operations() as $operation) {
            if (isset($runtimeExact[$operation], $oracleOutputs[$operation], $actualOutputs[$operation])) {
                $report[$operation . '_match'] = $oracleOutputs[$operation] === $actualOutputs[$operation];
            } else {
                $oraclePath = $oracleDir . '/' . $operation . '.txt';
                $actualPath = $actualDir . '/' . $operation . '.txt';

                if (is_file($oraclePath) && is_file($actualPath)) {
                    $oracleContent = file_get_contents($oraclePath);
                    $actualContent = file_get_contents($actualPath);
                    $report[$operation . '_match'] = $oracleContent === $actualContent;
                }
            }

            if (isset($runtimeExactMeta[$operation], $oracleOutputs[$operation . '_meta'], $actualOutputs[$operation . '_meta'])) {
                $report[$operation . '_meta_match'] = $oracleOutputs[$operation . '_meta'] === $actualOutputs[$operation . '_meta'];
            } else {
                $oracleMetaPath = $oracleDir . '/' . $operation . '.meta.json';
                $actualMetaPath = $actualDir . '/' . $operation . '.meta.json';

                if (is_file($oracleMetaPath) && is_file($actualMetaPath)) {
                    $oracleMeta = Json::decodeFile($oracleMetaPath);
                    $actualMeta = Json::decodeFile($actualMetaPath);
                    $report[$operation . '_meta_match'] = $oracleMeta === $actualMeta;
                }
            }
        }

        if (isset($runtimeExact['fsck'], $oracleOutputs['fsck'], $actualOutputs['fsck'])) {
            $oracleFsck = (string) $oracleOutputs['fsck'];
            $actualFsck = (string) $actualOutputs['fsck'];
            $report['fsck_match'] = $oracleFsck === $actualFsck;
            $fsck = trim($oracleFsck);
            $report['fsck_clean'] = $fsck === '' || str_contains($fsck, 'Checking object');
        } elseif (is_file($oracleDir . '/fsck.txt') && is_file($actualDir . '/fsck.txt')) {
            $oracleFsck = (string) file_get_contents($oracleDir . '/fsck.txt');
            $actualFsck = (string) file_get_contents($actualDir . '/fsck.txt');
            $report['fsck_match'] = $oracleFsck === $actualFsck;
            $fsck = trim($oracleFsck);
            $report['fsck_clean'] = $fsck === '' || str_contains($fsck, 'Checking object');
        }

        // Evaluate against expectations
        $evaluation = $scenario->evaluateReport($report);
        $report['expectation'] = $evaluation;

        return $report;
    }

    /**
     * Semantic comparison of object lists.
     * Compares by hash, type, and size. Content comparison is type-aware.
     */
    private function compareObjects(array $oracle, array $actual): bool
    {
        $oracleByHash = $this->indexByHash($oracle);
        $actualByHash = $this->indexByHash($actual);

        if (count($oracleByHash) !== count($actualByHash)) {
            return false;
        }

        foreach ($oracleByHash as $hash => $oracleObj) {
            if (!isset($actualByHash[$hash])) {
                return false;
            }

            $actualObj = $actualByHash[$hash];

            if ($oracleObj['type'] !== $actualObj['type']) {
                return false;
            }

            if ((int) $oracleObj['size'] !== (int) $actualObj['size']) {
                return false;
            }
        }

        return true;
    }

    private function objectsDiff(array $oracle, array $actual): array
    {
        $oracleByHash = $this->indexByHash($oracle);
        $actualByHash = $this->indexByHash($actual);

        $missingInActual = array_diff_key($oracleByHash, $actualByHash);
        $extraInActual = array_diff_key($actualByHash, $oracleByHash);
        $mismatches = [];

        foreach ($oracleByHash as $hash => $oracleObj) {
            if (!isset($actualByHash[$hash])) {
                continue;
            }

            $actualObj = $actualByHash[$hash];

            if ($oracleObj['type'] !== $actualObj['type'] || (int) $oracleObj['size'] !== (int) $actualObj['size']) {
                $mismatches[$hash] = [
                    'oracle' => ['type' => $oracleObj['type'], 'size' => $oracleObj['size']],
                    'actual' => ['type' => $actualObj['type'], 'size' => $actualObj['size']],
                ];
            }
        }

        return [
            'missing_in_actual' => array_keys($missingInActual),
            'extra_in_actual' => array_keys($extraInActual),
            'mismatches' => $mismatches,
        ];
    }

    private function compareRefs(array $oracle, array $actual): bool
    {
        $oracleMap = $this->refsToMap($oracle);
        $actualMap = $this->refsToMap($actual);

        return $oracleMap === $actualMap;
    }

    private function refsDiff(array $oracle, array $actual): array
    {
        $oracleMap = $this->refsToMap($oracle);
        $actualMap = $this->refsToMap($actual);

        return [
            'missing_in_actual' => array_diff_key($oracleMap, $actualMap),
            'extra_in_actual' => array_diff_key($actualMap, $oracleMap),
            'value_mismatches' => array_filter(
                array_intersect_key($oracleMap, $actualMap),
                static fn (string $hash, string $name): bool => $hash !== ($actualMap[$name] ?? ''),
                ARRAY_FILTER_USE_BOTH,
            ),
        ];
    }

    private function compareLog(array $oracle, array $actual): bool
    {
        if (count($oracle) !== count($actual)) {
            return false;
        }

        $oracleByHash = $this->indexByHash($oracle);
        $actualByHash = $this->indexByHash($actual);

        foreach ($oracleByHash as $hash => $oracleCommit) {
            if (!isset($actualByHash[$hash])) {
                return false;
            }

            $actualCommit = $actualByHash[$hash];

            if ($oracleCommit['tree'] !== $actualCommit['tree']) {
                return false;
            }

            $oracleParents = $oracleCommit['parents'] ?? [];
            $actualParents = $actualCommit['parents'] ?? [];

            sort($oracleParents);
            sort($actualParents);

            if ($oracleParents !== $actualParents) {
                return false;
            }
        }

        return true;
    }

    private function logDiff(array $oracle, array $actual): array
    {
        $oracleByHash = $this->indexByHash($oracle);
        $actualByHash = $this->indexByHash($actual);

        return [
            'oracle_count' => count($oracle),
            'actual_count' => count($actual),
            'missing_in_actual' => array_keys(array_diff_key($oracleByHash, $actualByHash)),
            'extra_in_actual' => array_keys(array_diff_key($actualByHash, $oracleByHash)),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function indexByHash(array $items): array
    {
        $indexed = [];

        foreach ($items as $item) {
            if (isset($item['hash'])) {
                $indexed[$item['hash']] = $item;
            }
        }

        return $indexed;
    }

    /**
     * @return array<string, string>
     */
    private function refsToMap(array $refs): array
    {
        $map = [];

        foreach ($refs as $ref) {
            if (isset($ref['name'], $ref['hash'])) {
                $map[$ref['name']] = $ref['hash'];
            }
        }

        ksort($map);

        return $map;
    }
}
