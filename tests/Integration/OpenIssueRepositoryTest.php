<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pitmaster\Merge\Rerere;
use Pitmaster\Pitmaster;
use Pitmaster\Stash\Stash;
use Pitmaster\Tests\Support\Workspace;

final class OpenIssueRepositoryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = Workspace::createDirectory('open-issues-');
    }

    protected function tearDown(): void
    {
        Workspace::remove($this->root);
    }

    public function testAlreadyMergedBranchKeepsHeadAndLocalChanges(): void
    {
        [$oracle, $actual] = $this->pair();
        foreach ([$oracle, $actual] as $dir) {
            $this->git($dir, 'branch feature HEAD~1');
            file_put_contents($dir . '/a.txt', "local edit\n");
        }
        $this->git($oracle, 'merge feature');
        $repo = Pitmaster::open($actual);
        $result = $repo->merge('feature');
        self::assertTrue($result->upToDate);
        self::assertSame($this->git($oracle, 'rev-parse HEAD'), $repo->head()->id->hex . "\n");
        self::assertSame(file_get_contents($oracle . '/a.txt'), file_get_contents($actual . '/a.txt'));
        self::assertSame($this->git($oracle, 'status --porcelain'), $this->git($actual, 'status --porcelain'));
        self::assertSame($this->git($oracle, 'reflog --format=%gs'), $this->git($actual, 'reflog --format=%gs'));
    }

    public function testPathHistoryWalksUntilItFindsMatchesIncludingDirectories(): void
    {
        [$oracle, $actual] = $this->pair();
        foreach (['docs/x.txt', 'docs'] as $path) {
            $commits = Pitmaster::open($actual)->logPath($path, 1);
            self::assertSame($this->git($oracle, 'log -n 1 --format=%H -- ' . escapeshellarg($path)), implode("\n", array_map(static fn ($commit) => $commit->id->hex, $commits)) . "\n");
        }
    }

    public function testExplicitMergeStrategyReportsConflictsLikeGit(): void
    {
        [$oracle, $actual] = $this->pair();
        foreach ([$oracle, $actual] as $dir) {
            $this->git($dir, 'checkout -b feature');
            file_put_contents($dir . '/a.txt', "feature change\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m feature');
            $this->git($dir, 'checkout main');
            file_put_contents($dir . '/a.txt', "main change\n");
            $this->git($dir, 'add a.txt');
            $this->git($dir, 'commit -m main');
        }
        $this->command('git merge -s recursive feature', $oracle, 1);
        $cli = dirname(__DIR__, 2) . '/bin/pitmaster';
        $this->command([PHP_BINARY, $cli, 'merge', '-s', 'recursive', 'feature'], $actual, 1);
        self::assertSame($this->git($oracle, 'status --porcelain'), $this->git($actual, 'status --porcelain'));
        self::assertSame(file_get_contents($oracle . '/a.txt'), file_get_contents($actual . '/a.txt'));
    }

    public function testInitializedRepositoryWritesReadableReflogs(): void
    {
        $oracle = $this->root . '/oracle';
        $actual = $this->root . '/actual';
        $this->git($this->root, 'init --initial-branch=main ' . escapeshellarg($oracle));
        Pitmaster::init($actual);
        $this->git($oracle, 'config core.logallrefupdates true');
        foreach ([$oracle, $actual] as $dir) {
            $this->identity($dir);
            file_put_contents($dir . '/a.txt', "a\n");
        }
        $this->git($oracle, 'add a.txt');
        $this->git($oracle, 'commit -m first');
        $repo = Pitmaster::open($actual);
        $repo->add('a.txt');
        $repo->commit('first');
        self::assertSame($this->git($oracle, 'reflog --format=%gs'), $this->git($actual, 'reflog --format=%gs'));
        self::assertSame($repo->head()->id->hex, $repo->resolve('HEAD@{0}')->hex);
        self::assertFileExists($actual . '/.git/logs/refs/heads/main');
    }

    public function testWritingAnIndexReadAsV4RemainsReadableByGit(): void
    {
        [$oracle, $actual] = $this->pair();
        foreach ([$oracle, $actual] as $dir) {
            $this->git($dir, 'update-index --index-version 4');
            file_put_contents($dir . '/a.txt', "staged change\n");
        }
        $this->git($oracle, 'add a.txt');
        $repo = Pitmaster::open($actual);
        self::assertSame(4, $repo->index()->version());
        $repo->add('a.txt');
        self::assertSame($this->git($oracle, 'ls-files --stage'), $this->git($actual, 'ls-files --stage'));
        self::assertSame($this->git($oracle, 'status --porcelain'), $this->git($actual, 'status --porcelain'));
    }

    public function testMultipleStashDropAndPopMatchGit(): void
    {
        [$oracle, $actual] = $this->pair();
        $stash = $this->stash($actual);
        foreach (['one', 'two', 'three'] as $message) {
            foreach ([$oracle, $actual] as $dir) {
                file_put_contents($dir . '/a.txt', $message . "\n");
            }
            $this->git($oracle, 'stash push -m ' . escapeshellarg($message));
            $stash->push($message);
        }
        $this->git($oracle, 'stash drop stash@{1}');
        $stash->drop(1);
        self::assertSame($this->git($oracle, 'stash list --format=%gs'), $this->git($actual, 'stash list --format=%gs'));
        $this->git($oracle, 'stash pop');
        $stash->pop();
        self::assertSame(file_get_contents($oracle . '/a.txt'), file_get_contents($actual . '/a.txt'));
        self::assertSame($this->git($oracle, 'stash list --format=%gs'), $this->git($actual, 'stash list --format=%gs'));
        self::assertSame(trim($this->git($actual, 'rev-parse refs/stash')), $stash->listEntries()[0]['hash']);
    }

    public function testLinkedHandleReportsTheMainWorktreeAndUsesSharedStashMetadata(): void
    {
        [$oracle, $actual] = $this->pair();
        foreach ([$oracle, $actual] as $dir) {
            $this->git($dir, 'worktree add -b linked ' . escapeshellarg($dir . '/linked'));
            file_put_contents($dir . '/linked/a.txt', "linked stash\n");
        }
        $this->git($oracle . '/linked', 'stash push -m linked');
        $stash = $this->stash($actual . '/linked');
        $stash->push('linked');
        $worktrees = Pitmaster::open($actual . '/linked')->worktrees();
        self::assertSame($actual, $worktrees[0]->path);
        self::assertSame($this->git($oracle, 'stash list --format=%gs'), $this->git($actual, 'stash list --format=%gs'));
        self::assertSame($this->git($oracle, 'log -n 1 --format=%an refs/stash'), $this->git($actual, 'log -n 1 --format=%an refs/stash'));
        self::assertCount(2, $worktrees);
    }

    public function testHooksInheritEnvironmentAndUseCommonOrConfiguredPaths(): void
    {
        [$oracle, $actual] = $this->pair();
        foreach (['common', 'relative', 'absolute'] as $mode) {
            foreach ([$oracle, $actual] as $dir) {
                $path = $mode === 'common' ? $dir . '/.git/hooks' : $dir . '/custom-hooks';
                if (!is_dir($path)) {
                    mkdir($path);
                }
                file_put_contents($path . '/post-commit', "#!/bin/sh\nprintf '%s\\n' \"\$PITMASTER_HOOK_TEST\" > hook-result\n");
                chmod($path . '/post-commit', 0755);
                if ($mode !== 'common') {
                    $this->git($dir, 'config core.hooksPath ' . escapeshellarg($mode === 'relative' ? '../custom-hooks' : $path));
                }
                if ($mode === 'common') {
                    $this->git($dir, 'worktree add -b hook-test ' . escapeshellarg($dir . '/hook-linked'));
                }
                file_put_contents($dir . '/hook-linked/a.txt', $mode . "\n");
            }
            $previous = getenv('PITMASTER_HOOK_TEST');
            putenv('PITMASTER_HOOK_TEST=from-parent');
            try {
                $this->git($oracle . '/hook-linked', 'add a.txt');
                $this->git($oracle . '/hook-linked', 'commit -m ' . $mode);
                $repo = Pitmaster::open($actual . '/hook-linked');
                $repo->add('a.txt');
                $repo->commit($mode);
                self::assertSame(file_get_contents($oracle . '/hook-linked/hook-result'), file_get_contents($actual . '/hook-linked/hook-result'));
            } finally {
                putenv($previous === false ? 'PITMASTER_HOOK_TEST' : 'PITMASTER_HOOK_TEST=' . $previous);
            }
        }
    }

    public function testRerereReadsGitConfigurationAndBooleanSpellings(): void
    {
        [, $actual] = $this->pair();
        foreach (['true', 'yes', 'on', '1', 'false', 'off', '0'] as $value) {
            $this->git($actual, 'config rerere.enabled ' . $value);
            self::assertSame(trim($this->git($actual, 'config --bool rerere.enabled')) === 'true', (new Rerere($actual . '/.git'))->isEnabled());
        }
    }

    /** @return array{string, string} */
    private function pair(): array
    {
        $oracle = $this->root . '/oracle';
        $actual = $this->root . '/actual';
        $this->git($this->root, 'init --initial-branch=main ' . escapeshellarg($oracle));
        $this->identity($oracle);
        mkdir($oracle . '/docs');
        file_put_contents($oracle . '/a.txt', "a\n");
        file_put_contents($oracle . '/docs/x.txt', "x\n");
        $this->git($oracle, 'add .');
        $this->git($oracle, 'commit -m initial');
        for ($i = 0; $i < 8; $i++) {
            file_put_contents($oracle . '/y.txt', $i . "\n");
            $this->git($oracle, 'add y.txt');
            $this->git($oracle, 'commit -m y' . $i);
        }
        $this->command(['cp', '-R', $oracle, $actual], $this->root);
        return [$oracle, $actual];
    }

    private function identity(string $dir): void
    {
        $this->git($dir, 'config user.name Test');
        $this->git($dir, 'config user.email test@example.com');
    }

    private function stash(string $dir): Stash
    {
        $repo = Pitmaster::open($dir);
        return new Stash($repo->objectDatabase(), $repo->refDatabase(), $repo->gitDir(), $dir);
    }

    private function git(string $dir, string $arguments): string
    {
        return $this->command('git ' . $arguments, $dir);
    }

    /** @param string|list<string> $command */
    private function command(string|array $command, string $dir, int $expectedExit = 0): string
    {
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $dir);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame($expectedExit, proc_close($process), $error);
        return $output;
    }
}
