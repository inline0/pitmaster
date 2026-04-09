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

    #[Test]
    public function grepMatchesGitForRevisionSearchAcrossSparseCheckoutAndSubmodules(): void
    {
        $depDir = $this->tmpDir . '/dep';
        mkdir($depDir, 0777, true);
        exec(sprintf('cd %s && git init --initial-branch=main 2>&1', escapeshellarg($depDir)), $output, $exitCode);
        $this->assertSame(0, $exitCode);
        exec(sprintf('cd %s && git config user.email test@pitmaster.dev && git config user.name "Test User" 2>&1', escapeshellarg($depDir)), $output, $exitCode);
        $this->assertSame(0, $exitCode);
        file_put_contents($depDir . '/lib.txt', "needle in submodule\n");
        exec(sprintf('cd %s && git add lib.txt && git commit -m dep 2>&1', escapeshellarg($depDir)), $output, $exitCode);
        $this->assertSame(0, $exitCode);

        $this->writeFile('docs/guide.txt', "needle in docs\n");
        $this->writeFile('hidden/secret.txt', "needle in hidden\n");
        $this->git('-c protocol.file.allow=always submodule add ' . escapeshellarg($depDir) . ' vendor/lib');
        $this->git('add .');
        $this->git('commit -m base');
        $this->git('sparse-checkout init --cone');
        $this->git('sparse-checkout set docs');

        $this->repo = Pitmaster::open($this->tmpDir);
        $grep = new Grep($this->repo->objectDatabase());
        $actual = $grep->grep($this->repo->head()->tree, 'needle');

        usort($actual, static fn (array $left, array $right): int => [$left['path'], $left['line']] <=> [$right['path'], $right['line']]);

        $this->assertSame($this->parseGitRevisionGrep('needle', 'HEAD'), $actual);
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

    /**
     * @return array<int, array{path: string, line: int, content: string}>
     */
    private function parseGitRevisionGrep(string $pattern, string $revision, array $flags = ['-n']): array
    {
        exec(
            sprintf(
                'cd %s && git grep %s -- %s %s 2>/dev/null',
                escapeshellarg($this->tmpDir),
                implode(' ', $flags),
                escapeshellarg($pattern),
                escapeshellarg($revision),
            ),
            $output,
            $exitCode,
        );

        if (!in_array($exitCode, [0, 1], true)) {
            $this->fail("git grep {$revision} failed with exit code {$exitCode}");
        }

        $results = [];

        foreach ($output as $line) {
            $parts = array_pad(explode(':', $line, 4), 4, '');
            $results[] = [
                'path' => $parts[1],
                'line' => (int) $parts[2],
                'content' => $parts[3],
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
