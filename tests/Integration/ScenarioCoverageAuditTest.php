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

        foreach (['status', 'diff'] as $category) {
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
    public function thinFirstPartyCategoriesHaveAtLeastTwoScenarios(): void
    {
        $root = dirname(__DIR__, 2);

        foreach (['worktree', 'rerere', 'config', 'codecs'] as $category) {
            $count = count(glob($root . "/scenarios/{$category}/*/scenario.json") ?: []);
            self::assertGreaterThanOrEqual(2, $count, "{$category} should have at least two first-party scenarios");
        }
    }
}
