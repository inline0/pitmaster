<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Repository;

final class LogShowParityTest extends TestCase
{
    private string $tmpDir;
    private Repository $repo;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-log-show-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');
        $this->seedHistory();
        $this->repo = Pitmaster::open($this->tmpDir);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function logMatchesGit(): void
    {
        $this->assertSame(
            $this->gitLines('log --format=%H -n 20'),
            array_map(static fn ($commit) => $commit->id->hex, $this->repo->log(20)),
        );
    }

    #[Test]
    public function logAllMatchesGit(): void
    {
        $this->assertSame(
            $this->gitLines('log --all --format=%H -n 20'),
            array_map(static fn ($commit) => $commit->id->hex, $this->repo->logAll(20)),
        );
    }

    #[Test]
    public function logPathMatchesGit(): void
    {
        $this->assertSame(
            $this->gitLines('log --format=%H -- docs/guide.txt'),
            array_map(static fn ($commit) => $commit->id->hex, $this->repo->logPath('docs/guide.txt', 20)),
        );
    }

    #[Test]
    public function logOnelineMatchesGit(): void
    {
        $this->assertSame(
            $this->gitLines('log --oneline --abbrev=7 -n 20'),
            $this->repo->logOneline(20),
        );
        $this->assertSame(
            $this->gitLines('log --all --oneline --abbrev=7 -n 20'),
            $this->repo->logOneline(20, true),
        );
        $this->assertSame(
            $this->gitLines('log --oneline --abbrev=7 -- docs/guide.txt'),
            $this->repo->logOneline(20, false, 'docs/guide.txt'),
        );
    }

    #[Test]
    public function pitmasterCliLogOptionsMatchGit(): void
    {
        $this->assertSame(
            $this->gitLines('log --oneline --abbrev=7 -n 20'),
            $this->pitmasterLines('log --oneline 20'),
        );
        $this->assertSame(
            $this->gitLines('log --all --oneline --abbrev=7 -n 20'),
            $this->pitmasterLines('log --all --oneline 20'),
        );
        $this->assertSame(
            $this->gitLines('log --oneline --abbrev=7 -- docs/guide.txt'),
            $this->pitmasterLines('log --oneline docs/guide.txt 20'),
        );
    }

    #[Test]
    public function showMatchesGitSubjectAndChangedPaths(): void
    {
        $result = $this->repo->show('HEAD');
        $paths = [];

        foreach ($result['diff'] as $diff) {
            $path = $diff->newPath ?? $diff->oldPath;

            if ($path !== null) {
                $paths[] = $path;
            }
        }

        sort($paths);

        $gitPaths = $this->gitLines('show --format= --name-only --no-renames HEAD');
        sort($gitPaths);

        $this->assertSame(trim($this->git('show --format=%H --no-patch HEAD')), $result['commit']->id->hex);
        $this->assertSame(trim($this->git('show --format=%s --no-patch HEAD')), trim($result['commit']->message));
        $this->assertSame($gitPaths, $paths);
    }

    #[Test]
    public function showAnnotatedTagPeelsToTheTaggedCommit(): void
    {
        $this->git('tag -a v1 -m "Release 1"');

        $result = $this->repo->show('v1');
        $paths = [];

        foreach ($result['diff'] as $diff) {
            $path = $diff->newPath ?? $diff->oldPath;

            if ($path !== null) {
                $paths[] = $path;
            }
        }

        sort($paths);

        $gitPaths = $this->gitLines('show --format= --name-only --no-renames $(git rev-list -n 1 v1)');
        sort($gitPaths);

        $this->assertArrayHasKey('tag', $result);
        $this->assertSame(trim($this->git('rev-list -n 1 v1')), $result['commit']->id->hex);
        $this->assertSame(trim($this->git('show --format=%s --no-patch $(git rev-list -n 1 v1)')), trim($result['commit']->message));
        $this->assertSame('v1', $result['tag']->name);
        $this->assertSame($gitPaths, $paths);
    }

    private function seedHistory(): void
    {
        mkdir($this->tmpDir . '/docs', 0777, true);
        file_put_contents($this->tmpDir . '/README.md', "initial\n");
        file_put_contents($this->tmpDir . '/docs/guide.txt', "guide v1\n");
        $this->git('add README.md docs/guide.txt');
        $this->git('commit -m "Initial commit"');

        $this->git('checkout -b feature');
        file_put_contents($this->tmpDir . '/feature.txt', "feature work\n");
        $this->git('add feature.txt');
        $this->git('commit -m "Feature branch work"');

        $this->git('checkout main');
        file_put_contents($this->tmpDir . '/docs/guide.txt', "guide v2\n");
        file_put_contents($this->tmpDir . '/README.md', "initial\nmain update\n");
        $this->git('add README.md docs/guide.txt');
        $this->git('commit -m "Main branch update"');
    }

    private function git(string $command): string
    {
        exec(
            sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $command),
            $output,
            $exitCode,
        );

        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->fail("git {$command} failed:\n{$result}");
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function gitLines(string $command): array
    {
        return array_values(array_filter(explode("\n", trim($this->git($command))), static fn (string $line): bool => $line !== ''));
    }

    /**
     * @return list<string>
     */
    private function pitmasterLines(string $command): array
    {
        $projectRoot = dirname(__DIR__, 2);

        exec(
            sprintf(
                'cd %s && %s %s %s 2>&1',
                escapeshellarg($this->tmpDir),
                escapeshellarg(PHP_BINARY),
                escapeshellarg($projectRoot . '/bin/pitmaster'),
                $command,
            ),
            $output,
            $exitCode,
        );

        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->fail("pitmaster {$command} failed:\n{$result}");
        }

        return array_values(array_filter(explode("\n", trim($result)), static fn (string $line): bool => $line !== ''));
    }
}
