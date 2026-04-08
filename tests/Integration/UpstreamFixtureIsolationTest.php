<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UpstreamFixtureIsolationTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 2);
    }

    #[Test]
    public function upstreamSetupScriptsDoNotReferenceSystemTmpFixtures(): void
    {
        foreach ($this->setupScripts() as $script) {
            $contents = file_get_contents($script);

            $this->assertNotFalse($contents);
            $this->assertDoesNotMatchRegularExpression(
                '#/(private/)?tmp/#',
                $contents,
                "{$script} still references an external tmp fixture path",
            );
        }
    }

    #[Test]
    public function upstreamSetupScriptsReferenceVendoredFixturesThatExist(): void
    {
        foreach ($this->setupScripts() as $script) {
            $contents = file_get_contents($script);

            $this->assertNotFalse($contents);
            preg_match_all('#\$\{PITMASTER_ROOT\}/fixtures/upstream/[^"\']+#', $contents, $matches);

            foreach ($matches[0] as $fixturePath) {
                $resolved = str_replace('${PITMASTER_ROOT}', $this->root, $fixturePath);
                $this->assertTrue(
                    file_exists($resolved),
                    "{$script} references missing vendored fixture {$resolved}",
                );
            }
        }
    }

    #[Test]
    public function acquisitionScriptsDoNotDependOnSystemTmpFixtures(): void
    {
        foreach ($this->acquisitionScripts() as $script) {
            $contents = file_get_contents($script);

            $this->assertNotFalse($contents);
            $this->assertDoesNotMatchRegularExpression(
                '#/(private/)?tmp/#',
                $contents,
                "{$script} still depends on an external tmp fixture root",
            );
        }
    }

    /**
     * @return array<int, string>
     */
    private function setupScripts(): array
    {
        $scripts = glob($this->root . '/scenarios/upstream/*/*/setup.sh');

        sort($scripts);

        return $scripts === false ? [] : $scripts;
    }

    /**
     * @return array<int, string>
     */
    private function acquisitionScripts(): array
    {
        return [
            $this->root . '/bin/acquire-dulwich-fixtures',
            $this->root . '/bin/acquire-fixtures',
            $this->root . '/bin/acquire-go-git-fixtures',
            $this->root . '/bin/acquire-git-test-suite',
            $this->root . '/bin/acquire-gitpython-fixtures',
            $this->root . '/bin/acquire-isomorphic-git-fixtures',
            $this->root . '/bin/acquire-jgit-fixtures',
        ];
    }
}
