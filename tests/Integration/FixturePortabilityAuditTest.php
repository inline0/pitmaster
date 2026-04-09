<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FixturePortabilityAuditTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    #[Test]
    public function committedOracleSnapshotsDoNotContainAbsolutePathsOrMachineLocalPorts(): void
    {
        foreach ($this->oracleFiles() as $path) {
            $contents = file_get_contents($path);
            $this->assertNotFalse($contents);

            if (str_contains($contents, "\0")) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression(
                '#/(private/)?tmp/|/Users/|/home/runner/|/var/folders/#',
                $contents,
                "{$path} contains a machine-local absolute path",
            );
            $this->assertDoesNotMatchRegularExpression(
                '#(?:127\.0\.0\.1|localhost):\d{2,5}#',
                $contents,
                "{$path} contains a machine-local port that should be runtime-only",
            );
            $this->assertStringNotContainsString(
                'PITMASTER_ROOT=',
                $contents,
                "{$path} leaked setup environment into a committed oracle snapshot",
            );
        }
    }

    #[Test]
    public function scenarioScriptsAvoidNonPortableShellFragments(): void
    {
        foreach ($this->scenarioScripts() as $path) {
            $contents = file_get_contents($path);
            $this->assertNotFalse($contents);

            $this->assertDoesNotMatchRegularExpression(
                '#\b(?:gsed|greadlink|ggrep)\b|sed -i \'\'|source /#',
                $contents,
                "{$path} contains a non-portable shell fragment",
            );
            $this->assertDoesNotMatchRegularExpression(
                '#/(private/)?tmp/#',
                $contents,
                "{$path} still depends on a temp-path fixture",
            );
        }
    }

    #[Test]
    public function vendoredUpstreamSetupScriptsReferenceOnlyRepoLocalFixtures(): void
    {
        foreach ($this->upstreamSetupScripts() as $path) {
            $contents = file_get_contents($path);
            $this->assertNotFalse($contents);

            preg_match_all('#\$\{PITMASTER_ROOT\}/fixtures/upstream/[^"\']+#', $contents, $matches);

            foreach ($matches[0] as $fixturePath) {
                $resolved = str_replace('${PITMASTER_ROOT}', $this->root, $fixturePath);
                $this->assertTrue(file_exists($resolved), "{$path} references missing vendored fixture {$resolved}");
            }
        }
    }

    #[Test]
    public function guidanceDocsDoNotInstructContributorsToUseTmpFixtureRoots(): void
    {
        foreach ([$this->root . '/README.md', $this->root . '/CLAUDE.md'] as $path) {
            $contents = file_get_contents($path);
            $this->assertNotFalse($contents);
            $this->assertDoesNotMatchRegularExpression(
                '#/(private/)?tmp/#',
                $contents,
                "{$path} still tells contributors to use tmp fixture roots",
            );
        }
    }

    /**
     * @return list<string>
     */
    private function oracleFiles(): array
    {
        $files = [];

        foreach (glob($this->root . '/scenarios/*/*/scenario.json') ?: [] as $scenarioPath) {
            $scenario = json_decode((string) file_get_contents($scenarioPath), true);

            if (!is_array($scenario)) {
                continue;
            }

            $baseDir = dirname($scenarioPath) . '/oracle';
            $outputs = array_merge(
                ['objects', 'refs', 'log', 'fsck'],
                array_keys($scenario['oracle_commands'] ?? []),
                $scenario['expectations']['exact_match'] ?? [],
                $scenario['expectations']['runtime_exact_match'] ?? [],
            );

            foreach (array_unique($outputs) as $name) {
                foreach (['json', 'txt'] as $extension) {
                    $path = $baseDir . '/' . $name . '.' . $extension;

                    if (is_file($path)) {
                        $files[] = $path;
                    }
                }
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }

    /**
     * @return list<string>
     */
    private function scenarioScripts(): array
    {
        $scripts = glob($this->root . '/scenarios/*/*/*.{sh,php}', GLOB_BRACE);
        sort($scripts);

        return $scripts === false ? [] : array_values($scripts);
    }

    /**
     * @return list<string>
     */
    private function upstreamSetupScripts(): array
    {
        $scripts = glob($this->root . '/scenarios/upstream/*/*/setup.sh');
        sort($scripts);

        return $scripts === false ? [] : array_values($scripts);
    }
}
