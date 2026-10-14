<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pitmaster\Diff\DiffResult;
use Pitmaster\Pitmaster;
use Pitmaster\Tests\Support\Workspace;

final class OpenIssueDiffTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = Workspace::createDirectory('diff-issues-');
        $this->git('init --initial-branch=main');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name Tester');
        $this->git('config core.filemode true');
    }

    protected function tearDown(): void
    {
        Workspace::remove($this->root);
    }

    /** @return iterable<string, array{string}> */
    public static function cases(): iterable
    {
        foreach (['new', 'empty-new', 'delete', 'empty-delete', 'binary', 'binary-new', 'binary-delete', 'rename', 'edited-rename', 'binary-rename', 'unrelated', 'duplicate-lines', 'mode', 'mode-content', 'symlink', 'no-newline'] as $case) {
            yield $case => [$case];
        }
    }

    #[DataProvider('cases')]
    public function testStagedDiffMatchesGit(string $case): void
    {
        $base = match ($case) {
            'binary', 'binary-delete', 'binary-rename' => "\0\1",
            'duplicate-lines' => str_repeat("shared\n", 8) . "old\n",
            'edited-rename' => "long shared first line\nlong shared second line\nold\n",
            'empty-delete' => '',
            'no-newline' => 'old',
            default => "old\n",
        };
        file_put_contents($this->root . '/old.txt', $base);
        $this->git('add .');
        $this->git('commit -m base');

        if (in_array($case, ['new', 'empty-new', 'binary-new'], true)) {
            file_put_contents($this->root . '/new.txt', match ($case) {
                'empty-new' => '',
                'binary-new' => "\0\2",
                default => "new\n",
            });
        } elseif (in_array($case, ['delete', 'empty-delete', 'binary-delete'], true)) {
            unlink($this->root . '/old.txt');
        } elseif (in_array($case, ['rename', 'edited-rename', 'binary-rename', 'unrelated', 'duplicate-lines'], true)) {
            unlink($this->root . '/old.txt');
            file_put_contents($this->root . '/new.txt', match ($case) {
                'edited-rename' => "long shared first line\nlong shared second line\nnew\n",
                'unrelated' => "new\n",
                'duplicate-lines' => "shared\n" . str_repeat("different\n", 8),
                default => $base,
            });
        } elseif ($case === 'mode' || $case === 'mode-content') {
            chmod($this->root . '/old.txt', 0755);
            if ($case === 'mode-content') {
                file_put_contents($this->root . '/old.txt', "new\n");
            }
        } elseif ($case === 'symlink') {
            symlink('old.txt', $this->root . '/link');
        } else {
            file_put_contents($this->root . '/old.txt', $case === 'binary' ? "\0\2" : 'new');
        }

        $this->git('add -A');
        $repo = Pitmaster::open($this->root);
        $expected = $this->git('diff --cached --no-color --no-ext-diff');
        self::assertSame($expected, $this->format($repo->diffStaged()));
        self::assertEquals(array_values(array_filter($repo->diffStaged(), static fn (DiffResult $entry): bool => $entry->oldPath === 'old.txt' || $entry->newPath === 'old.txt')), $repo->diffStaged('old.txt'));
        $process = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/bin/pitmaster', 'diff', '--cached'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root);
        self::assertIsResource($process);
        self::assertSame($expected, stream_get_contents($pipes[1]));
        self::assertSame('', stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process));
        $this->git('commit -m changed');
        self::assertSame($expected, $this->format(Pitmaster::open($this->root)->show('HEAD')['diff']));
    }

    public function testUnbornIndexAndWorktreeModeDeletionMatchGit(): void
    {
        file_put_contents($this->root . '/one.txt', "one\n");
        file_put_contents($this->root . '/two.txt', "two\n");
        $this->git('add .');
        $repo = Pitmaster::open($this->root);
        self::assertSame($this->git('diff --cached --no-color'), $this->format($repo->diffStaged()));
        self::assertSame($this->git('diff --cached --no-color -- one.txt'), $this->format($repo->diffStaged('one.txt')));
        $this->git('commit -m base');
        chmod($this->root . '/one.txt', 0755);
        unlink($this->root . '/two.txt');
        self::assertSame($this->git('diff --no-color'), $this->format(Pitmaster::open($this->root)->diff()));
    }

    /** @param list<DiffResult> $results */
    private function format(array $results): string
    {
        return implode('', array_map(static fn (DiffResult $result): string => $result->format(), $results));
    }

    private function git(string $args): string
    {
        $process = proc_open('git ' . $args, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->root);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $error);
        return $output;
    }
}
