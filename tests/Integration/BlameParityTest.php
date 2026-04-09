<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Graph\Blame;
use Pitmaster\Pitmaster;
use Pitmaster\Repository;

final class BlameParityTest extends TestCase
{
    private string $tmpDir;
    private Repository $repo;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-blame-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init --initial-branch=main');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function blameMatchesGitForModifiedAndAddedLines(): void
    {
        $this->commitAs(
            'Alice',
            'alice@example.com',
            '2024-01-01T00:00:00+0000',
            'base',
            "alpha\nbeta\ngamma\n",
        );
        $this->commitAs(
            'Bob',
            'bob@example.com',
            '2024-01-02T00:00:00+0000',
            'modify',
            "alpha\nbeta two\ngamma\n",
        );
        $this->commitAs(
            'Carol',
            'carol@example.com',
            '2024-01-03T00:00:00+0000',
            'append',
            "alpha\nbeta two\ngamma\ndelta\n",
        );

        $this->repo = Pitmaster::open($this->tmpDir);
        $blame = new Blame($this->repo->objectDatabase());

        $actual = array_map(
            static fn (array $entry): array => [
                'line' => $entry['line'],
                'hash' => $entry['hash'],
                'author' => $entry['author'],
                'content' => $entry['content'],
            ],
            $blame->blame($this->repo->head()->id, 'f.txt'),
        );

        $this->assertSame($this->parseGitBlame('f.txt'), $actual);
    }

    #[Test]
    public function blameMatchesGitForSingleLineReplacementAmongRepeatedLines(): void
    {
        $this->commitAs(
            'Alice',
            'alice@example.com',
            '2024-02-01T00:00:00+0000',
            'base',
            "repeat\nrepeat\nrepeat\n",
        );
        $this->commitAs(
            'Bob',
            'bob@example.com',
            '2024-02-02T00:00:00+0000',
            'replace',
            "repeat\nchanged\nrepeat\n",
        );

        $this->repo = Pitmaster::open($this->tmpDir);
        $blame = new Blame($this->repo->objectDatabase());

        $actual = array_map(
            static fn (array $entry): array => [
                'line' => $entry['line'],
                'hash' => $entry['hash'],
                'author' => $entry['author'],
                'content' => $entry['content'],
            ],
            $blame->blame($this->repo->head()->id, 'f.txt'),
        );

        $this->assertSame($this->parseGitBlame('f.txt'), $actual);
    }

    #[Test]
    public function blameMatchesGitForMovedLines(): void
    {
        $this->commitAs(
            'Alice',
            'alice@example.com',
            '2024-03-01T00:00:00+0000',
            'base',
            "one\ntwo\nthree\nfour\n",
        );
        $this->commitAs(
            'Bob',
            'bob@example.com',
            '2024-03-02T00:00:00+0000',
            'move',
            "three\nfour\none\ntwo\n",
        );

        $this->repo = Pitmaster::open($this->tmpDir);
        $blame = new Blame($this->repo->objectDatabase());

        $actual = array_map(
            static fn (array $entry): array => [
                'line' => $entry['line'],
                'hash' => $entry['hash'],
                'author' => $entry['author'],
                'content' => $entry['content'],
            ],
            $blame->blame($this->repo->head()->id, 'f.txt'),
        );

        $this->assertSame($this->parseGitBlame('f.txt'), $actual);
    }

    #[Test]
    public function blameMatchesGitAcrossMergeHistory(): void
    {
        $this->commitAs(
            'Alice',
            'alice@example.com',
            '2024-04-01T00:00:00+0000',
            'base',
            "alpha\nbeta\ngamma\ndelta\nepsilon\n",
        );
        $this->git('checkout -b feature');
        $this->commitAs(
            'Bob',
            'bob@example.com',
            '2024-04-02T00:00:00+0000',
            'feature',
            "alpha feature\nbeta\ngamma\ndelta\nepsilon\n",
        );
        $this->git('checkout main');
        $this->commitAs(
            'Carol',
            'carol@example.com',
            '2024-04-03T00:00:00+0000',
            'main',
            "alpha\nbeta\ngamma\ndelta\nepsilon main\n",
        );
        $this->gitWithEnv([
            'GIT_AUTHOR_NAME' => 'Dave',
            'GIT_AUTHOR_EMAIL' => 'dave@example.com',
            'GIT_AUTHOR_DATE' => '2024-04-04T00:00:00+0000',
            'GIT_COMMITTER_NAME' => 'Dave',
            'GIT_COMMITTER_EMAIL' => 'dave@example.com',
            'GIT_COMMITTER_DATE' => '2024-04-04T00:00:00+0000',
        ], 'merge --no-ff feature -m merge');

        $this->repo = Pitmaster::open($this->tmpDir);
        $blame = new Blame($this->repo->objectDatabase());

        $actual = array_map(
            static fn (array $entry): array => [
                'line' => $entry['line'],
                'hash' => $entry['hash'],
                'author' => $entry['author'],
                'content' => $entry['content'],
            ],
            $blame->blame($this->repo->head()->id, 'f.txt'),
        );

        $this->assertSame($this->parseGitBlame('f.txt'), $actual);
    }

    private function commitAs(
        string $name,
        string $email,
        string $date,
        string $message,
        string $content,
    ): void {
        file_put_contents($this->tmpDir . '/f.txt', $content);
        $this->git('add f.txt');

        $this->gitWithEnv([
            'GIT_AUTHOR_NAME' => $name,
            'GIT_AUTHOR_EMAIL' => $email,
            'GIT_AUTHOR_DATE' => $date,
            'GIT_COMMITTER_NAME' => $name,
            'GIT_COMMITTER_EMAIL' => $email,
            'GIT_COMMITTER_DATE' => $date,
        ], 'commit -m ' . escapeshellarg($message));
    }

    /**
     * @return array<int, array{line: int, hash: string, author: string, content: string}>
     */
    private function parseGitBlame(string $path): array
    {
        $lines = preg_split('/\r?\n/', trim($this->git('blame --line-porcelain -- ' . escapeshellarg($path)))) ?: [];
        $result = [];
        $currentHash = null;
        $currentAuthor = null;
        $lineNumber = 1;

        foreach ($lines as $line) {
            if (preg_match('/^[0-9a-f]{40}\s/', $line) === 1) {
                $currentHash = strtok($line, ' ');
                $currentAuthor = null;
                continue;
            }

            if (str_starts_with($line, 'author ')) {
                $currentAuthor = substr($line, 7);
                continue;
            }

            if (str_starts_with($line, 'author-mail ')) {
                $currentAuthor .= ' ' . substr($line, 12);
                continue;
            }

            if (str_starts_with($line, 'author-time ')) {
                $currentAuthor .= ' ' . substr($line, 12);
                continue;
            }

            if (str_starts_with($line, 'author-tz ')) {
                $currentAuthor .= ' ' . substr($line, 10);
                continue;
            }

            if (str_starts_with($line, "\t")) {
                $result[] = [
                    'line' => $lineNumber++,
                    'hash' => $currentHash ?? '',
                    'author' => $currentAuthor ?? '',
                    'content' => substr($line, 1),
                ];
            }
        }

        return $result;
    }

    private function git(string $command): string
    {
        exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $command), $output, $exitCode);
        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->fail("git {$command} failed:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }

    /**
     * @param array<string, string> $env
     */
    private function gitWithEnv(array $env, string $command): string
    {
        $prefix = [];

        foreach ($env as $name => $value) {
            $prefix[] = $name . '=' . escapeshellarg($value);
        }

        exec(sprintf(
            'cd %s && %s git %s 2>&1',
            escapeshellarg($this->tmpDir),
            implode(' ', $prefix),
            $command,
        ), $output, $exitCode);
        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->fail("git {$command} failed:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }
}
