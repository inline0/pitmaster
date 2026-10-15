<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use Pitmaster\Object\Commit;
use Pitmaster\Pitmaster;
use Pitmaster\Tests\Support\Workspace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OldestHistoryParityTest extends TestCase
{
    private string $directory;
    private int $timestamp = 1704067200;

    protected function setUp(): void
    {
        $this->directory = Workspace::createDirectory('oldest-history-');
        $version = $this->git(['--version']);
        if (preg_match('/git version (\d+\.\d+)/', $version, $match) !== 1 || version_compare($match[1], '2.55', '<')) {
            self::markTestSkipped('Oldest commit limiting requires Git 2.55 or newer');
        }
        $this->git(['init', '-b', 'main']);
        mkdir($this->directory . '/docs');
        file_put_contents($this->directory . '/app.txt', "base\n");
        file_put_contents($this->directory . '/docs/guide.txt', "base\n");
        $this->commit('base');
        file_put_contents($this->directory . '/app.txt', "main one\n");
        $this->commit('main one');
        $this->git(['checkout', '-b', 'side', 'HEAD~1']);
        file_put_contents($this->directory . '/docs/guide.txt', "side docs\n");
        $this->commit('side docs');
        $this->git(['checkout', 'main']);
        file_put_contents($this->directory . '/app.txt', "main two\n");
        $this->commit('main two');
        $this->git(['merge', '--no-ff', '-m', 'merge side', 'side']);
        $this->timestamp++;
        $this->git(['checkout', 'side']);
        file_put_contents($this->directory . '/app.txt', "side app\n");
        $this->commit('side app');
        $this->git(['checkout', 'main']);
        file_put_contents($this->directory . '/app.txt', "main final\n");
        $this->commit('main final');
        $this->git(['checkout', '--orphan', 'archived']);
        $this->git(['rm', '-rf', '.']);
        file_put_contents($this->directory . '/app.txt', "archived\n");
        $this->timestamp = 1672531200;
        $this->commit('tag only history');
        $this->git(['tag', '-a', 'archive', '-m', 'archive']);
        $this->git(['checkout', 'main']);
        $this->git(['branch', '-D', 'archived']);
    }

    protected function tearDown(): void
    {
        Workspace::remove($this->directory);
    }

    /** @return iterable<string, array{int, bool, string|null}> */
    public static function selections(): iterable
    {
        foreach ([0, 1, 2, 20] as $limit) {
            foreach ([false, true] as $all) {
                foreach ([null, 'app.txt', 'docs'] as $path) {
                    yield $limit . '/' . ($all ? 'all' : 'head') . '/' . ($path ?? 'no-filter') => [$limit, $all, $path];
                }
            }
        }
    }

    #[DataProvider('selections')]
    public function testOldestAndNewestSelectionsMatchGit(int $limit, bool $all, ?string $path): void
    {
        $repo = Pitmaster::open($this->directory);
        $options = $all ? ['--all'] : [];
        $filter = $path === null ? [] : ['--', $path];
        $expected = $this->git(['log', '--format=%H', '--max-count-oldest=' . $limit, ...$options, ...$filter]);
        $commits = $path !== null
            ? $repo->logPath($path, $limit, oldest: true, all: $all)
            : ($all ? $repo->logAll($limit, oldest: true) : $repo->log($limit, oldest: true));
        $hashes = array_map(static fn (Commit $commit): string => $commit->id->hex, $commits);
        self::assertSame($expected, $hashes === [] ? '' : implode("\n", $hashes) . "\n");
        $oneline = $this->git(['log', '--oneline', '--max-count-oldest=' . $limit, ...$options, ...$filter]);
        $actual = $repo->logOneline($limit, $all, $path, oldest: true);
        self::assertSame($oneline, $actual === [] ? '' : implode("\n", $actual) . "\n");
        $cli = $this->executeProcess([
            PHP_BINARY, dirname(__DIR__, 2) . '/bin/pitmaster', 'log', '--oneline',
            '--max-count-oldest=' . $limit, ...$options, ...$filter,
        ]);
        self::assertSame($oneline, $cli['stdout']);
        $newest = $this->git(['log', '--oneline', '--max-count=' . $limit, ...$options, ...$filter]);
        $actual = $repo->logOneline($limit, $all, $path);
        self::assertSame($newest, $actual === [] ? '' : implode("\n", $actual) . "\n");
    }

    public function testOldestLimitUsesTheRequestedStartingPoint(): void
    {
        $repo = Pitmaster::open($this->directory);
        $expected = trim($this->git(['log', '--format=%H', '--max-count-oldest=1', 'side']));
        self::assertSame($expected, $repo->log(1, $repo->resolve('side'), oldest: true)[0]->id->hex);
    }

    public function testConflictingLongLimitOptionsMatchGit(): void
    {
        foreach ([['--max-count=2', '--max-count-oldest=1'], ['--max-count-oldest=1', '--max-count=2']] as $options) {
            $expected = $this->executeProcess(['git', 'log', ...$options], 128);
            $actual = $this->executeProcess([PHP_BINARY, dirname(__DIR__, 2) . '/bin/pitmaster', 'log', ...$options], 128);
            self::assertSame($expected['stderr'], $actual['stderr']);
        }
    }

    private function commit(string $message): void
    {
        $this->git(['add', '.']);
        $this->git(['commit', '-m', $message]);
        $this->timestamp++;
    }

    /** @param list<string> $arguments */
    private function git(array $arguments): string
    {
        return $this->executeProcess(['git', '-c', 'user.name=Test', '-c', 'user.email=test@example.com', ...$arguments])['stdout'];
    }

    /** @param list<string> $command
     * @return array{stdout: string, stderr: string}
     */
    private function executeProcess(array $command, int $expectedExit = 0): array
    {
        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->directory,
            array_merge(getenv(), [
                'GIT_CONFIG_NOSYSTEM' => '1', 'GIT_CONFIG_GLOBAL' => '/dev/null',
                'GIT_AUTHOR_DATE' => '@' . $this->timestamp . ' +0000',
                'GIT_COMMITTER_DATE' => '@' . $this->timestamp . ' +0000',
            ]),
        );
        self::assertIsResource($process);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame($expectedExit, proc_close($process), (string) $stderr);
        return ['stdout' => (string) $stdout, 'stderr' => (string) $stderr];
    }
}
