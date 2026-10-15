<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Tests\Support\Workspace;

final class BranchInUseParityTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = Workspace::createDirectory('branch-in-use-');
        $version = $this->command(['git', '--version'])['output'];
        preg_match('/(\d+\.\d+\.\d+)/', $version, $matches);
        if (version_compare($matches[1] ?? '0', '2.56.0', '<')) {
            self::markTestSkipped('Bisect branch protection requires Git 2.56.');
        }
    }

    protected function tearDown(): void
    {
        Workspace::remove($this->root);
    }

    public static function cases(): array
    {
        return [
            'main checkout' => ['checkout', 'main'],
            'main bisect' => ['bisect', 'main'],
            'linked checkout' => ['linked', 'feature'],
            'linked bisect' => ['linked-bisect', 'feature'],
            'unrelated during bisect' => ['bisect', 'feature'],
            'bare symbolic HEAD' => ['bare', 'main'],
        ];
    }

    #[DataProvider('cases')]
    public function testBranchDeletionProtectsEveryWorktreeAndBisect(string $mode, string $branch): void
    {
        $native = $this->prepare('native', $mode);
        $actual = $this->prepare('actual', $mode);
        $expected = $this->command(['git', '-C', $native, 'branch', '-D', $branch]);
        $error = null;
        try {
            Pitmaster::open($actual)->deleteBranch($branch);
        } catch (\RuntimeException $caught) {
            $error = $caught;
        }
        self::assertSame($expected['exit'] === 0, $error === null, $expected['output']);
        $expectedRef = $this->command(['git', '-C', $native, 'show-ref', '--verify', 'refs/heads/' . $branch]);
        $actualRef = $this->command(['git', '-C', $actual, 'show-ref', '--verify', 'refs/heads/' . $branch]);
        self::assertSame($expectedRef['exit'] === 0, $actualRef['exit'] === 0);
        if ($expectedRef['exit'] === 0) {
            self::assertSame($expectedRef['output'], $actualRef['output']);
        }
        self::assertSame(
            $this->command(['git', '-C', $native, 'rev-parse', '--symbolic-full-name', 'HEAD'])['output'],
            $this->command(['git', '-C', $actual, 'rev-parse', '--symbolic-full-name', 'HEAD'])['output'],
        );
    }

    private function prepare(string $name, string $mode): string
    {
        $path = $this->root . '/' . $name;
        $this->git(['init', '--initial-branch=main', $path]);
        $this->git(['-C', $path, 'config', 'user.name', 'Test']);
        $this->git(['-C', $path, 'config', 'user.email', 'test@example.com']);
        for ($i = 1; $i <= 5; $i++) {
            file_put_contents($path . '/file.txt', "{$i}\n");
            $this->git(['-C', $path, 'add', 'file.txt']);
            $this->git(['-C', $path, 'commit', '-m', 'Commit ' . $i], [
                'GIT_AUTHOR_DATE' => "2024-03-01T00:00:0{$i}+0000",
                'GIT_COMMITTER_DATE' => "2024-03-01T00:00:0{$i}+0000",
            ]);
        }
        $this->git(['-C', $path, 'branch', 'feature']);
        if ($mode === 'bare') {
            $bare = $path . '.git';
            $this->git(['clone', '--bare', $path, $bare]);
            return $bare;
        }
        if (str_starts_with($mode, 'linked')) {
            $linked = $path . '-linked';
            $this->git(['-C', $path, 'worktree', 'add', $linked, 'feature']);
            if ($mode === 'linked-bisect') {
                $this->git(['-C', $linked, 'bisect', 'start', 'feature', 'HEAD~3']);
            }
        } elseif ($mode === 'bisect') {
            $this->git(['-C', $path, 'bisect', 'start', 'main', 'HEAD~3']);
        }
        return $path;
    }

    private function git(array $arguments, array $environment = []): void
    {
        $result = $this->command(['git', ...$arguments], $environment);
        self::assertSame(0, $result['exit'], $result['output']);
    }

    private function command(array $arguments, array $environment = []): array
    {
        $log = $this->root . '/command.log';
        $process = proc_open($arguments, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']], $pipes, null, array_merge(getenv(), $environment));
        self::assertIsResource($process);
        $exit = proc_close($process);
        return ['exit' => $exit, 'output' => (string) file_get_contents($log)];
    }
}
