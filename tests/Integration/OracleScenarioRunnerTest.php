<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Tests\Oracle\ScenarioRepository;
use Pitmaster\Tests\Oracle\ScenarioRunner;
use Pitmaster\Tests\Support\Workspace;

final class OracleScenarioRunnerTest extends TestCase
{
    private string $tmpRoot;

    protected function setUp(): void
    {
        $this->tmpRoot = Workspace::createDirectory('pitmaster-oracle-runner-');
        mkdir($this->tmpRoot . '/scenarios/refs/lightweight-tag-create', 0777, true);
    }

    protected function tearDown(): void
    {
        Workspace::remove($this->tmpRoot);
    }

    #[Test]
    public function mutationScenarioRunsAgainstSeparateOracleAndActualRepos(): void
    {
        $scenarioDir = $this->tmpRoot . '/scenarios/refs/lightweight-tag-create';
        $autoload = addslashes(dirname(__DIR__, 2) . '/vendor/autoload.php');

        file_put_contents($scenarioDir . '/scenario.json', json_encode([
            'name' => 'lightweight-tag-create',
            'category' => 'refs',
            'description' => 'Create a lightweight tag from HEAD',
            'operations' => [],
            'oracle_commands' => new \stdClass(),
            'expectations' => [
                'exact_match' => ['objects', 'refs', 'log'],
                'fsck_clean' => true,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        file_put_contents($scenarioDir . '/setup.sh', <<<'SH'
#!/bin/bash
set -e
git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"
echo "hello" > app.txt
git add app.txt
git commit -m "Initial commit"
SH);

        file_put_contents($scenarioDir . '/oracle.sh', <<<'SH'
#!/bin/bash
set -e
git tag v1.0
SH);

        file_put_contents($scenarioDir . '/actual.php', <<<PHP
<?php
declare(strict_types=1);
require '{$autoload}';
use Pitmaster\\Pitmaster;
\$repo = Pitmaster::open(getcwd());
\$repo->createLightweightTag('v1.0');
PHP);

        $scenario = (new ScenarioRepository($this->tmpRoot))->get('refs/lightweight-tag-create');
        $result = (new ScenarioRunner())->run($scenario, true);

        $this->assertTrue($result['pass']);
        $this->assertTrue($result['comparison']['objects_match']);
        $this->assertTrue($result['comparison']['refs_match']);
        $this->assertTrue($result['comparison']['log_match']);
        $this->assertTrue($result['comparison']['fsck_clean']);
    }

    #[Test]
    public function operationScenarioComparesActualCommandOutputAgainstGit(): void
    {
        $scenarioDir = $this->tmpRoot . '/scenarios/status/status-porcelain-v2';
        mkdir($scenarioDir, 0777, true);
        $root = dirname(__DIR__, 2);

        file_put_contents($scenarioDir . '/scenario.json', json_encode([
            'name' => 'status-porcelain-v2',
            'category' => 'status',
            'description' => 'Compare porcelain v2 output',
            'operations' => ['status'],
            'oracle_commands' => [
                'status' => 'git status --porcelain=v2',
            ],
            'actual_commands' => [
                'status' => 'php "' . $root . '/bin/pitmaster" status --porcelain=v2',
            ],
            'expectations' => [
                'exact_match' => ['status', 'objects', 'refs', 'log', 'fsck'],
                'fsck_clean' => true,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        file_put_contents($scenarioDir . '/setup.sh', <<<'SH'
#!/bin/bash
set -e
git init .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"
echo "base staged" > staged.txt
echo "base modified" > modified.txt
git add staged.txt modified.txt
git commit -m "Initial commit"
echo "new staged" > staged.txt
git add staged.txt
echo "new modified" > modified.txt
echo "untracked" > untracked.txt
SH);

        $scenario = (new ScenarioRepository($this->tmpRoot))->get('status/status-porcelain-v2');
        $result = (new ScenarioRunner())->run($scenario, true);

        $this->assertTrue($result['pass']);
        $this->assertTrue($result['comparison']['status_match']);
        $this->assertTrue($result['comparison']['fsck_match']);
    }

    #[Test]
    public function runtimeExactMatchUsesFreshOracleWithoutRewritingCommittedSnapshots(): void
    {
        $scenarioDir = $this->tmpRoot . '/scenarios/index/runtime-exact-match';
        mkdir($scenarioDir . '/oracle', 0777, true);

        file_put_contents($scenarioDir . '/scenario.json', json_encode([
            'name' => 'runtime-exact-match',
            'category' => 'index',
            'description' => 'Compare volatile output against a fresh oracle capture',
            'operations' => ['head'],
            'oracle_commands' => [
                'head' => 'git rev-parse HEAD',
            ],
            'actual_commands' => [
                'head' => 'git rev-parse HEAD',
            ],
            'expectations' => [
                'runtime_exact_match' => ['head'],
                'fsck_clean' => true,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        file_put_contents($scenarioDir . '/setup.sh', <<<'SH'
#!/bin/bash
set -e
git init -b main .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-11T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-11T00:00:00+0000"
echo "hello" > app.txt
git add app.txt
git commit -m "Initial commit" >/dev/null
SH);

        file_put_contents($scenarioDir . '/oracle/objects.json', "[]\n");
        file_put_contents($scenarioDir . '/oracle/head.txt', "stale-head\n");

        $scenario = (new ScenarioRepository($this->tmpRoot))->get('index/runtime-exact-match');
        $result = (new ScenarioRunner())->run($scenario, false);

        $this->assertTrue(
            $result['pass'],
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'runtime-exact-match',
        );
        $this->assertTrue($result['comparison']['head_match']);
        $this->assertSame("stale-head\n", file_get_contents($scenarioDir . '/oracle/head.txt'));
    }

    #[Test]
    public function exactMetaMatchComparesExitCodeStdoutAndStderr(): void
    {
        $scenarioDir = $this->tmpRoot . '/scenarios/errors/meta-parity';
        mkdir($scenarioDir, 0777, true);

        file_put_contents($scenarioDir . '/scenario.json', json_encode([
            'name' => 'meta-parity',
            'category' => 'errors',
            'description' => 'Compare command metadata exactly',
            'operations' => ['failure'],
            'oracle_commands' => [
                'failure' => 'bash -lc "printf \'out\\n\'; printf \'err\\n\' >&2; exit 7"',
            ],
            'actual_commands' => [
                'failure' => 'bash -lc "printf \'out\\n\'; printf \'err\\n\' >&2; exit 7"',
            ],
            'expectations' => [
                'exact_match' => ['failure'],
                'exact_meta_match' => ['failure'],
                'fsck_clean' => true,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        file_put_contents($scenarioDir . '/setup.sh', <<<'SH'
#!/bin/bash
set -e
git init -b main .
git config user.email "test@pitmaster.dev"
git config user.name "Test User"
echo "hello" > app.txt
git add app.txt
git commit -m "Initial commit" >/dev/null
SH);

        $scenario = (new ScenarioRepository($this->tmpRoot))->get('errors/meta-parity');
        $result = (new ScenarioRunner())->run($scenario, true);

        $this->assertTrue(
            $result['pass'],
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'meta-parity',
        );
        $this->assertTrue($result['comparison']['failure_match']);
        $this->assertTrue($result['comparison']['failure_meta_match']);
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function vendoredUpstreamScenarioProvider(): array
    {
        return [
            ['upstream/dulwich/a'],
            ['upstream/libgit2/mailmap'],
            ['upstream/go-git/repo-ab06771a6711'],
            ['upstream/jgit/pack-546ff360fe34'],
            ['upstream/isomorphic-git/test-tag'],
        ];
    }

    #[Test]
    #[DataProvider('vendoredUpstreamScenarioProvider')]
    public function vendoredUpstreamScenarioRunsFromRepoLocalFixtures(string $scenarioName): void
    {
        $scenario = (new ScenarioRepository(dirname(__DIR__, 2)))->get($scenarioName);
        $result = (new ScenarioRunner())->run($scenario, false);

        $this->assertTrue(
            $result['pass'],
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: $scenarioName,
        );
    }

    #[Test]
    public function scenarioRunnerNormalizesNestedRepoSetups(): void
    {
        $scenarioDir = $this->tmpRoot . '/scenarios/upstream/nested-repo';
        mkdir($scenarioDir, 0777, true);

        file_put_contents($scenarioDir . '/scenario.json', json_encode([
            'name' => 'nested-repo',
            'category' => 'upstream',
            'description' => 'Promote a nested repo to the scenario root',
            'operations' => [],
            'oracle_commands' => new \stdClass(),
            'expectations' => [
                'exact_match' => ['objects', 'refs', 'log'],
                'fsck_clean' => true,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

        file_put_contents($scenarioDir . '/setup.sh', <<<'SH'
#!/bin/bash
set -e
mkdir repo
git init repo >/dev/null
git -C repo config user.email "test@pitmaster.dev"
git -C repo config user.name "Test User"
export GIT_AUTHOR_DATE="2024-01-11T00:00:00+0000"
export GIT_COMMITTER_DATE="2024-01-11T00:00:00+0000"
echo "nested" > repo/app.txt
git -C repo add app.txt
git -C repo commit -m "Nested repo commit" >/dev/null
SH);

        $scenario = (new ScenarioRepository($this->tmpRoot))->get('upstream/nested-repo');
        $result = (new ScenarioRunner())->run($scenario, true);

        $this->assertTrue(
            $result['pass'],
            json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: 'nested-repo',
        );
    }
}
