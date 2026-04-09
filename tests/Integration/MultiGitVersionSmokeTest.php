<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Tests\Support\Workspace;

final class MultiGitVersionSmokeTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            Workspace::remove($path);
        }
    }

    #[Test]
    public function representativeOracleScenariosCanRunAgainstEachConfiguredGitBinary(): void
    {
        $binaries = $this->gitBinaries();

        if (count($binaries) < 2) {
            self::markTestSkipped('Set PITMASTER_TEST_GIT_BINARIES to two or more git binaries to enable multi-version smoke tests');
        }

        $scenarios = [
            'repository/init-basic',
            'refs/lightweight-tag-create',
            'status/status-porcelain-v2',
        ];

        foreach ($binaries as $binary) {
            $shimDir = Workspace::createDirectory('git-shim-');
            $this->paths[] = $shimDir;
            symlink($binary, $shimDir . '/git');
            $version = trim((string) shell_exec(escapeshellarg($binary) . ' --version'));

            foreach ($scenarios as $scenario) {
                exec(
                    sprintf(
                        'cd %s && PATH=%s:$PATH ./bin/test-scenario %s 2>&1',
                        escapeshellarg(dirname(__DIR__, 2)),
                        escapeshellarg($shimDir),
                        escapeshellarg($scenario),
                    ),
                    $output,
                    $exitCode,
                );

                self::assertSame(
                    0,
                    $exitCode,
                    "{$version} failed scenario {$scenario}:\n" . implode("\n", $output),
                );
            }
        }
    }

    /**
     * @return list<string>
     */
    private function gitBinaries(): array
    {
        $configured = getenv('PITMASTER_TEST_GIT_BINARIES');

        if (!is_string($configured) || trim($configured) === '') {
            return [];
        }

        $resolved = [];

        foreach (preg_split('/[,:;]/', $configured) ?: [] as $candidate) {
            $candidate = trim($candidate);

            if ($candidate === '') {
                continue;
            }

            $path = realpath($candidate) ?: trim((string) shell_exec('command -v ' . escapeshellarg($candidate)));

            if ($path !== '' && is_executable($path) && !in_array($path, $resolved, true)) {
                $resolved[] = $path;
            }
        }

        return $resolved;
    }
}
