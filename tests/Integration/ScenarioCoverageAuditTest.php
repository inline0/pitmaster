<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScenarioCoverageAuditTest extends TestCase
{
    #[Test]
    public function localStatusAndDiffScenariosExposeExactMatchOperationOutputs(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['status', 'diff', 'branch', 'grep'] as $category) {
            foreach (glob($root . "/scenarios/{$category}/*/scenario.json") ?: [] as $path) {
                $definition = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
                $exact = (array) ($definition['expectations']['exact_match'] ?? []);
                $actualCommands = (array) ($definition['actual_commands'] ?? []);

                foreach ((array) ($definition['operations'] ?? []) as $operation) {
                    self::assertArrayHasKey(
                        $operation,
                        $actualCommands,
                        "{$path} is missing an actual command for {$operation}",
                    );
                    self::assertContains(
                        $operation,
                        $exact,
                        "{$path} does not exact-match {$operation}",
                    );
                }
            }
        }
    }

    #[Test]
    public function reflogAndNegativeCliScenariosExposeExactMetadata(): void
    {
        $root = dirname(__DIR__, 2);
        $exactScenarios = [
            ['refs/reflog-cli-parity', ['head_reflog', 'branch_reflog']],
        ];
        $metaScenarios = [
            ['merge/merge-continue-no-state', ['merge_continue']],
            ['rebase/rebase-continue-no-state', ['rebase_continue']],
            ['cherry-pick/cherry-pick-continue-no-state', ['cherry_pick_continue']],
            ['revert/revert-continue-no-state', ['revert_continue']],
        ];

        foreach ($exactScenarios as [$scenarioName, $operations]) {
            $path = $root . '/scenarios/' . $scenarioName . '/scenario.json';
            $definition = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $exact = (array) ($definition['expectations']['exact_match'] ?? []);

            foreach ($operations as $operation) {
                self::assertContains(
                    $operation,
                    $exact,
                    "{$path} does not exact-match {$operation}",
                );
            }
        }

        foreach ($metaScenarios as [$scenarioName, $metaOperations]) {
            $path = $root . '/scenarios/' . $scenarioName . '/scenario.json';
            $definition = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
            $exactMeta = (array) ($definition['expectations']['exact_meta_match'] ?? []);

            foreach ($metaOperations as $operation) {
                self::assertContains(
                    $operation,
                    $exactMeta,
                    "{$path} does not exact-meta-match {$operation}",
                );
            }
        }
    }

    #[Test]
    public function thinFirstPartyCategoriesHaveAtLeastTwoScenarios(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['worktree', 'rerere', 'config', 'codecs'] as $category) {
            $count = count(glob($root . "/scenarios/{$category}/*/scenario.json") ?: []);
            self::assertGreaterThanOrEqual(2, $count, "{$category} should have at least two first-party scenarios");
        }
    }
}
