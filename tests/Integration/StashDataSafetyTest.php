<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Stash\Stash;
use Pitmaster\Tests\Support\Workspace;

final class StashDataSafetyTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = Workspace::createDirectory('stash-data-safety-');
    }

    protected function tearDown(): void
    {
        Workspace::remove($this->root);
    }

    /** @return iterable<string, array{string, bool, bool}> */
    public static function cases(): iterable
    {
        foreach (['apply', 'pop'] as $action) {
            foreach ([false, true] as $staged) {
                foreach ([false, true] as $affected) {
                    yield $action . ($staged ? '-staged' : '-unstaged') . ($affected ? '-affected' : '-unrelated') => [$action, $staged, $affected];
                }
            }
        }
    }

    #[DataProvider('cases')]
    public function testApplyAndPopPreserveLocalChangesOrFailBeforeWriting(string $action, bool $staged, bool $affected): void
    {
        $oracle = $this->root . '/oracle';
        $actual = $this->root . '/actual';
        foreach ([$oracle, $actual] as $dir) {
            mkdir($dir);
            $this->git($dir, 'init -q --initial-branch=main');
            $this->git($dir, 'config user.name Test');
            $this->git($dir, 'config user.email test@example.test');
            foreach (['a', 'b', 'c'] as $path) {
                file_put_contents($dir . '/' . $path, $path . "\n");
            }
            $this->git($dir, 'add .');
            $this->git($dir, 'commit -qm base');
            file_put_contents($dir . '/a', "stashed a\n");
            file_put_contents($dir . '/c', "stashed c\n");
            $this->git($dir, 'stash push -qm saved');
            $local = $affected ? 'c' : 'b';
            file_put_contents($dir . '/' . $local, "local changes\n");
            if ($staged) {
                $this->git($dir, 'add ' . $local);
            }
        }
        [$code] = $this->command($oracle, 'git stash ' . $action);
        $before = [];
        foreach (['a', 'b', 'c'] as $path) {
            $before[$path] = file_get_contents($actual . '/' . $path);
        }
        $beforeIndex = file_get_contents($actual . '/.git/index');
        $beforeStatus = $this->git($actual, 'status --porcelain');
        $beforeStashes = $this->git($actual, 'stash list --format=%gs');
        $repo = Pitmaster::open($actual);
        $stash = new Stash($repo->objectDatabase(), $repo->refDatabase(), $repo->gitDir(), $actual);
        $success = true;
        try {
            $action === 'pop' ? $stash->pop() : $stash->apply();
        } catch (\RuntimeException) {
            $success = false;
        }
        self::assertSame($code === 0, $success);
        if ($staged && $affected) {
            foreach ($before as $path => $content) {
                self::assertSame($content, file_get_contents($actual . '/' . $path), $path);
            }
            self::assertSame($beforeIndex, file_get_contents($actual . '/.git/index'));
            self::assertSame($beforeStatus, $this->git($actual, 'status --porcelain'));
            self::assertSame($beforeStashes, $this->git($actual, 'stash list --format=%gs'));
            return;
        }
        foreach (['a', 'b', 'c'] as $path) {
            self::assertSame(file_get_contents($oracle . '/' . $path), file_get_contents($actual . '/' . $path), $path);
        }
        self::assertSame($this->git($oracle, 'status --porcelain'), $this->git($actual, 'status --porcelain'));
        self::assertSame($this->git($oracle, 'stash list --format=%gs'), $this->git($actual, 'stash list --format=%gs'));
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
