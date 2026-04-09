<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Graph\Grep;
use Pitmaster\Pitmaster;
use Pitmaster\Repository;

final class GrepParityTest extends TestCase
{
    private string $tmpDir;
    private Repository $repo;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-grep-' . bin2hex(random_bytes(4));
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
    public function grepMatchesGitForNestedFilesAndBinarySkipping(): void
    {
        $this->writeFile('README.md', "hello root\nbye\n");
        $this->writeFile('src/feature.txt', "first line\nhello nested\n");
        $this->writeFile('docs/guide.txt', "nothing here\n");
        $this->writeFile('bin/data.bin', "hello\0binary\n");
        $this->git('add .');
        $this->git('commit -m initial');

        $this->repo = Pitmaster::open($this->tmpDir);
        $grep = new Grep($this->repo->objectDatabase());
        $actual = $grep->grep($this->repo->head()->tree, 'hello');

        usort($actual, static fn (array $left, array $right): int => [$left['path'], $left['line']] <=> [$right['path'], $right['line']]);

        $this->assertSame($this->parseGitGrep('hello'), $actual);
    }

    #[Test]
    public function grepMatchesGitForRegexAndIgnoreCaseOptions(): void
    {
        $this->writeFile('README.md', "Hello root\nbye\n");
        $this->writeFile('src/feature.txt', "first line\nHELLO nested\n");
        $this->writeFile('docs/guide.txt', "helper text\n");
        $this->git('add .');
        $this->git('commit -m initial');

        $this->repo = Pitmaster::open($this->tmpDir);
        $grep = new Grep($this->repo->objectDatabase());
        $actual = $grep->grep($this->repo->head()->tree, 'h.llo', '', [
            'regex' => true,
            'ignore_case' => true,
        ]);

        usort($actual, static fn (array $left, array $right): int => [$left['path'], $left['line']] <=> [$right['path'], $right['line']]);

        $this->assertSame($this->parseGitGrep('h.llo', ['-n', '-i', '-E']), $actual);
    }

    #[Test]
    public function grepReturnsNoMatchesLikeGit(): void
    {
        $this->writeFile('src/feature.txt', "first line\nsecond line\n");
        $this->git('add .');
        $this->git('commit -m initial');

        $this->repo = Pitmaster::open($this->tmpDir);
        $grep = new Grep($this->repo->objectDatabase());

        $this->assertSame([], $grep->grep($this->repo->head()->tree, 'missing'));
        $this->assertSame([], $this->parseGitGrep('missing'));
    }

    /**
     * @return array<int, array{path: string, line: int, content: string}>
     */
    private function parseGitGrep(string $pattern, array $flags = ['-n']): array
    {
        exec(
            sprintf(
                'cd %s && git grep %s -- %s 2>/dev/null',
                escapeshellarg($this->tmpDir),
                implode(' ', $flags),
                escapeshellarg($pattern),
            ),
            $output,
            $exitCode,
        );

        if (!in_array($exitCode, [0, 1], true)) {
            $this->fail("git grep failed with exit code {$exitCode}");
        }

        $results = [];

        foreach ($output as $line) {
            if (preg_match('/^Binary file (.+) matches$/', $line, $matches) === 1) {
                $results[] = [
                    'path' => $matches[1],
                    'line' => 0,
                    'content' => '',
                ];
                continue;
            }

            [$path, $lineNumber, $content] = array_pad(explode(':', $line, 3), 3, '');
            $results[] = [
                'path' => $path,
                'line' => (int) $lineNumber,
                'content' => $content,
            ];
        }

        usort($results, static fn (array $left, array $right): int => [$left['path'], $left['line']] <=> [$right['path'], $right['line']]);

        return $results;
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

    private function writeFile(string $path, string $content): void
    {
        $fullPath = $this->tmpDir . '/' . $path;
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($fullPath, $content);
    }
}
