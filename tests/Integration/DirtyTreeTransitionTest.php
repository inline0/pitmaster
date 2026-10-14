<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pitmaster\Merge\MergeResult;
use Pitmaster\Pitmaster;
use Pitmaster\Tests\Support\Workspace;

final class DirtyTreeTransitionTest extends TestCase
{
    private string $root;

    /** @var array<string, string|false> */
    private array $previousEnv = [];

    protected function setUp(): void
    {
        $this->root = Workspace::createDirectory('dirty-transition-');
        foreach (['GIT_AUTHOR_DATE', 'GIT_COMMITTER_DATE'] as $name) {
            $this->previousEnv[$name] = getenv($name);
            putenv($name . '=2024-01-02T00:00:00+0000');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->previousEnv as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }
        Workspace::remove($this->root);
    }

    /** @return iterable<string, array{string, bool, bool}> */
    public static function cases(): iterable
    {
        foreach (['checkout', 'merge-ff', 'merge-commit', 'merge-conflict', 'cherry-pick', 'revert', 'rebase', 'merge-ours'] as $operation) {
            foreach ([false, true] as $staged) {
                foreach ([false, true] as $affected) {
                    yield $operation . ($staged ? '-staged' : '-unstaged') . ($affected ? '-affected' : '-unrelated') => [$operation, $staged, $affected];
                }
            }
        }
    }

    #[DataProvider('cases')]
    public function testDirtyTransitionsMatchGit(string $operation, bool $staged, bool $affected): void
    {
        $oracle = $this->root . '/oracle';
        $actual = $this->root . '/actual';
        $target = '';
        foreach ([$oracle, $actual] as $dir) {
            mkdir($dir);
            $this->git($dir, 'init -q --initial-branch=main');
            $this->git($dir, 'config user.name Test');
            $this->git($dir, 'config user.email test@example.test');
            $this->git($dir, 'config merge.conflictStyle merge');
            $this->git($dir, 'config core.filemode true');
            foreach (['a', 'b', 'c'] as $name) {
                file_put_contents($dir . '/' . $name, $name . "\n");
            }
            $this->git($dir, 'add .');
            $this->git($dir, 'commit -qm base');
            $this->git($dir, 'checkout -qb feature');
            file_put_contents($dir . '/a', "feature\n");
            $this->git($dir, 'add a');
            $this->git($dir, 'commit -qm feature');
            $target = trim($this->git($dir, 'rev-parse HEAD'));
            $this->git($dir, 'checkout -q main');
            if (in_array($operation, ['merge-commit', 'merge-conflict', 'rebase', 'merge-ours'], true)) {
                $path = $operation === 'merge-conflict' ? 'a' : 'c';
                file_put_contents($dir . '/' . $path, "main\n");
                $this->git($dir, 'add ' . $path);
                $this->git($dir, 'commit -qm main');
            }
            if ($operation === 'revert') {
                $this->git($dir, 'merge --ff-only feature');
            }
            $path = $affected ? 'a' : 'b';
            file_put_contents($dir . '/' . $path, "local changes\n");
            if ($staged) {
                $this->git($dir, 'add ' . $path);
            }
        }
        $args = match ($operation) {
            'checkout' => 'checkout feature',
            'merge-ff', 'merge-commit', 'merge-conflict' => 'merge feature',
            'merge-ours' => 'merge -s ours feature',
            'cherry-pick' => 'cherry-pick ' . $target,
            'revert' => 'revert --no-edit ' . $target,
            'rebase' => 'rebase feature',
        };
        [$gitCode] = $this->command($oracle, 'git ' . $args);
        $repo = Pitmaster::open($actual);
        $success = true;
        try {
            $result = match ($operation) {
                'checkout' => $repo->checkout('feature'),
                'merge-ff', 'merge-commit', 'merge-conflict' => $repo->merge('feature'),
                'merge-ours' => $repo->merge('feature', 'ours'),
                'cherry-pick' => $repo->cherryPick($target),
                'revert' => $repo->revert($target),
                'rebase' => $repo->rebase('feature'),
            };
            if ($result instanceof MergeResult) {
                $success = $result->clean;
            }
        } catch (\RuntimeException) {
            $success = false;
        }
        self::assertSame($gitCode === 0, $success, $operation);
        foreach (['a', 'b', 'c'] as $path) {
            self::assertSame(file_get_contents($oracle . '/' . $path), file_get_contents($actual . '/' . $path), $path);
        }
        self::assertSame($this->git($oracle, 'status --porcelain'), $this->git($actual, 'status --porcelain'));
        self::assertSame($this->git($oracle, 'rev-parse HEAD^{tree}'), $this->git($actual, 'rev-parse HEAD^{tree}'));
        self::assertSame($this->git($oracle, 'symbolic-ref -q HEAD'), $this->git($actual, 'symbolic-ref -q HEAD'));
    }

    public function testCheckoutCarriesStagedAdditionsAndDeletions(): void
    {
        [$oracle, $actual] = $this->smallPair();
        foreach ([$oracle, $actual] as $dir) {
            file_put_contents($dir . '/new', "staged addition\n");
            unlink($dir . '/b');
            $this->git($dir, 'add -A');
        }
        $this->git($oracle, 'checkout feature');
        Pitmaster::open($actual)->checkout('feature');
        self::assertSame($this->git($oracle, 'status --porcelain'), $this->git($actual, 'status --porcelain'));
        self::assertSame($this->git($oracle, 'ls-files --stage'), $this->git($actual, 'ls-files --stage'));
        self::assertSame(file_get_contents($oracle . '/new'), file_get_contents($actual . '/new'));
        self::assertFileDoesNotExist($actual . '/b');
    }

    public function testMergeAbortKeepsUnrelatedLocalEdits(): void
    {
        [$oracle, $actual] = $this->smallPair();
        foreach ([$oracle, $actual] as $dir) {
            file_put_contents($dir . '/a', "main\n");
            $this->git($dir, 'add a');
            $this->git($dir, 'commit -qm main');
            file_put_contents($dir . '/b', "local changes\n");
        }
        [$code] = $this->command($oracle, 'git merge feature');
        self::assertSame(1, $code);
        $repo = Pitmaster::open($actual);
        self::assertFalse($repo->merge('feature')->clean);
        $this->git($oracle, 'merge --abort');
        $repo->mergeAbort();
        self::assertSame($this->git($oracle, 'status --porcelain'), $this->git($actual, 'status --porcelain'));
        self::assertSame($this->git($oracle, 'ls-files --stage'), $this->git($actual, 'ls-files --stage'));
        foreach (['a', 'b'] as $path) {
            self::assertSame(file_get_contents($oracle . '/' . $path), file_get_contents($actual . '/' . $path));
        }
    }

    public function testOctopusMergePreservesUnrelatedUnstagedEdits(): void
    {
        [$oracle, $actual] = $this->smallPair();
        foreach ([$oracle, $actual] as $dir) {
            $this->git($dir, 'checkout -qb second');
            file_put_contents($dir . '/c', "second branch\n");
            $this->git($dir, 'add c');
            $this->git($dir, 'commit -qm second');
            $this->git($dir, 'checkout main');
            file_put_contents($dir . '/b', "local changes\n");
        }
        $this->git($oracle, 'merge feature second');
        self::assertTrue(Pitmaster::open($actual)->mergeOctopus(['feature', 'second'])->clean);
        self::assertSame($this->git($oracle, 'status --porcelain'), $this->git($actual, 'status --porcelain'));
        self::assertSame($this->git($oracle, 'rev-parse HEAD^{tree}'), $this->git($actual, 'rev-parse HEAD^{tree}'));
        self::assertSame(file_get_contents($oracle . '/b'), file_get_contents($actual . '/b'));
    }

    public function testBisectCandidateAndResetCarryUnrelatedEdits(): void
    {
        [$oracle, $actual] = $this->smallPair();
        foreach ([$oracle, $actual] as $dir) {
            foreach (['second', 'third'] as $message) {
                file_put_contents($dir . '/a', $message . "\n");
                $this->git($dir, 'add a');
                $this->git($dir, 'commit -qm ' . $message);
            }
            file_put_contents($dir . '/b', "local changes\n");
        }
        $this->git($oracle, 'bisect start HEAD HEAD~2');
        $repo = Pitmaster::open($actual);
        $repo->bisectStart('HEAD', 'HEAD~2');
        self::assertSame($this->git($oracle, 'rev-parse HEAD'), $this->git($actual, 'rev-parse HEAD'));
        self::assertSame($this->git($oracle, 'status --porcelain'), $this->git($actual, 'status --porcelain'));
        self::assertSame(file_get_contents($oracle . '/b'), file_get_contents($actual . '/b'));
        $this->git($oracle, 'bisect reset');
        $repo->bisectReset();
        self::assertSame($this->git($oracle, 'rev-parse HEAD'), $this->git($actual, 'rev-parse HEAD'));
        self::assertSame($this->git($oracle, 'status --porcelain'), $this->git($actual, 'status --porcelain'));
        self::assertSame(file_get_contents($oracle . '/b'), file_get_contents($actual . '/b'));
    }

    public function testCheckoutProtectsLocalExecutableChangesAndKeepsSymlinkEdits(): void
    {
        [$oracle, $actual] = $this->smallPair();
        foreach ([$oracle, $actual] as $dir) {
            $this->git($dir, 'config core.filemode true');
            chmod($dir . '/a', 0755);
        }
        [$code] = $this->command($oracle, 'git checkout feature');
        self::assertNotSame(0, $code);
        try {
            Pitmaster::open($actual)->checkout('feature');
            self::fail('Checkout overwrote a changing path with a local executable bit');
        } catch (\RuntimeException $error) {
            self::assertStringContainsString('would be overwritten', $error->getMessage());
        }
        self::assertSame($this->git($oracle, 'status --porcelain'), $this->git($actual, 'status --porcelain'));
        foreach ([$oracle, $actual] as $dir) {
            chmod($dir . '/a', 0644);
            symlink('a', $dir . '/link');
            $this->git($dir, 'add link');
            $this->git($dir, 'commit -qm symlink');
            $this->git($dir, 'merge feature');
            $this->git($dir, 'checkout -b next');
            file_put_contents($dir . '/a', "next branch\n");
            $this->git($dir, 'add a');
            $this->git($dir, 'commit -qm next');
            $this->git($dir, 'checkout main');
            unlink($dir . '/link');
            symlink('b', $dir . '/link');
        }
        $this->git($oracle, 'checkout next');
        Pitmaster::open($actual)->checkout('next');
        self::assertSame(readlink($oracle . '/link'), readlink($actual . '/link'));
        self::assertSame($this->git($oracle, 'status --porcelain'), $this->git($actual, 'status --porcelain'));
    }

    /** @return array{string, string} */
    private function smallPair(): array
    {
        $dirs = [$this->root . '/oracle', $this->root . '/actual'];
        foreach ($dirs as $dir) {
            mkdir($dir);
            $this->git($dir, 'init -q --initial-branch=main');
            $this->git($dir, 'config user.name Test');
            $this->git($dir, 'config user.email test@example.test');
            $this->git($dir, 'config merge.conflictStyle merge');
            foreach (['a', 'b'] as $path) {
                file_put_contents($dir . '/' . $path, $path . "\n");
            }
            $this->git($dir, 'add .');
            $this->git($dir, 'commit -qm base');
            $this->git($dir, 'checkout -qb feature');
            file_put_contents($dir . '/a', "feature\n");
            $this->git($dir, 'add a');
            $this->git($dir, 'commit -qm feature');
            $this->git($dir, 'checkout -q main');
        }
        return $dirs;
    }

    private function git(string $dir, string $args): string
    {
        [$code, $output, $error] = $this->command($dir, 'git ' . $args);
        self::assertSame(0, $code, $error);
        return $output;
    }

    /** @return array{int, string, string} */
    private function command(string $dir, string $command): array
    {
        $process = proc_open($command, [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $dir);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $output, $error];
    }
}
