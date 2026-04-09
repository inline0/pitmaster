<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScenarioDeterminismTest extends TestCase
{
    #[Test]
    public function exactMatchScenariosWithGitCommitsUseFixedCommitDates(): void
    {
        $root = dirname(__DIR__, 2);
        $scenarioFiles = glob($root . '/scenarios/*/*/scenario.json');

        foreach ($scenarioFiles === false ? [] : $scenarioFiles as $scenarioFile) {
            if (str_contains($scenarioFile, '/upstream/')) {
                continue;
            }

            $definition = json_decode((string) file_get_contents($scenarioFile), true, flags: JSON_THROW_ON_ERROR);
            $exactMatch = $definition['expectations']['exact_match'] ?? [];

            if ($exactMatch === []) {
                continue;
            }

            $setupPath = dirname($scenarioFile) . '/setup.sh';

            if (!is_file($setupPath)) {
                continue;
            }

            $setup = (string) file_get_contents($setupPath);

            if (preg_match('/\bgit\b[^\n]*\bcommit\b/', $setup) !== 1) {
                continue;
            }

            $this->assertStringContainsString(
                'GIT_AUTHOR_DATE',
                $setup,
                "{$setupPath} creates exact-match commits without a fixed author date",
            );
            $this->assertStringContainsString(
                'GIT_COMMITTER_DATE',
                $setup,
                "{$setupPath} creates exact-match commits without a fixed committer date",
            );
        }
    }

    #[Test]
    public function exactMatchScenariosWithAnnotatedTagsUseFixedTaggerDates(): void
    {
        $root = dirname(__DIR__, 2);
        $scenarioFiles = glob($root . '/scenarios/*/*/scenario.json');

        foreach ($scenarioFiles === false ? [] : $scenarioFiles as $scenarioFile) {
            if (str_contains($scenarioFile, '/upstream/')) {
                continue;
            }

            $definition = json_decode((string) file_get_contents($scenarioFile), true, flags: JSON_THROW_ON_ERROR);
            $exactMatch = $definition['expectations']['exact_match'] ?? [];

            if ($exactMatch === []) {
                continue;
            }

            $setupPath = dirname($scenarioFile) . '/setup.sh';

            if (!is_file($setupPath)) {
                continue;
            }

            $setup = (string) file_get_contents($setupPath);

            if (preg_match('/\bgit\b[^\n]*\btag\b[^\n]*(?:\s-a\b|\s--annotate\b)/', $setup) !== 1) {
                continue;
            }

            $this->assertStringContainsString(
                'GIT_COMMITTER_DATE',
                $setup,
                "{$setupPath} creates exact-match annotated tags without a fixed tagger date",
            );
        }
    }

    #[Test]
    public function localScenariosDoNotExactMatchWholeIndexBlobs(): void
    {
        $root = dirname(__DIR__, 2);
        $scenarioFiles = glob($root . '/scenarios/*/*/scenario.json');

        foreach ($scenarioFiles === false ? [] : $scenarioFiles as $scenarioFile) {
            if (str_contains($scenarioFile, '/upstream/')) {
                continue;
            }

            $definition = json_decode((string) file_get_contents($scenarioFile), true, flags: JSON_THROW_ON_ERROR);
            $exactMatch = $definition['expectations']['exact_match'] ?? [];

            $this->assertNotContains(
                'index_hex',
                $exactMatch,
                "{$scenarioFile} exact-matches the raw index blob instead of a stable semantic output",
            );
        }
    }
}
