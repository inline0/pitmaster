<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Exceptions\MergeConflictException;
use Pitmaster\Pitmaster;
use Pitmaster\Stash\Stash;

final class StashParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-stash-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function stashPushMatchesGitForNamedMessage(): void
    {
        $source = $this->tmpDir . '/source';
        $pitDir = $this->tmpDir . '/pit';
        $gitDir = $this->tmpDir . '/git';
        $this->createBaseRepo($source);
        $this->copyRepo($source, $pitDir);
        $this->copyRepo($source, $gitDir);

        file_put_contents($pitDir . '/a.txt', "modified\n");
        file_put_contents($gitDir . '/a.txt', "modified\n");

        $pitRepo = Pitmaster::open($pitDir);
        $stash = new Stash(
            $pitRepo->objectDatabase(),
            $pitRepo->refDatabase(),
            $pitRepo->gitDir(),
            $pitRepo->workDir(),
        );
        putenv('GIT_AUTHOR_DATE=@1700000000 +0000');
        putenv('GIT_COMMITTER_DATE=@1700000000 +0000');
        $stash->push('test stash');
        putenv('GIT_AUTHOR_DATE');
        putenv('GIT_COMMITTER_DATE');

        $this->gitWithEnv($gitDir, [
            'GIT_AUTHOR_DATE' => '@1700000000 +0000',
            'GIT_COMMITTER_DATE' => '@1700000000 +0000',
        ], 'stash push -m ' . escapeshellarg('test stash'));

        $this->assertSame(trim($this->gitIn($gitDir, 'stash list')), $this->formatList($stash));
        $this->assertSame(file_get_contents($gitDir . '/a.txt'), file_get_contents($pitDir . '/a.txt'));
        $this->assertSame(
            trim($this->gitIn($gitDir, "show -s --format='%s|%P' refs/stash")),
            trim($this->gitIn($pitDir, "show -s --format='%s|%P' refs/stash")),
        );
        $this->assertSame(
            $this->gitIn($gitDir, 'show refs/stash:a.txt'),
            $this->gitIn($pitDir, 'show refs/stash:a.txt'),
        );
    }

    #[Test]
    public function stashApplyMatchesGitForStagedAndUnstagedChanges(): void
    {
        $source = $this->tmpDir . '/source-apply';
        $pitDir = $this->tmpDir . '/pit-apply';
        $gitDir = $this->tmpDir . '/git-apply';
        $this->createBaseRepo($source);
        $this->copyRepo($source, $pitDir);
        $this->copyRepo($source, $gitDir);

        file_put_contents($pitDir . '/a.txt', "staged\n");
        file_put_contents($gitDir . '/a.txt', "staged\n");
        $this->gitIn($pitDir, 'add a.txt');
        $this->gitIn($gitDir, 'add a.txt');
        file_put_contents($pitDir . '/a.txt', "worktree\n");
        file_put_contents($gitDir . '/a.txt', "worktree\n");

        $pitRepo = Pitmaster::open($pitDir);
        $stash = new Stash(
            $pitRepo->objectDatabase(),
            $pitRepo->refDatabase(),
            $pitRepo->gitDir(),
            $pitRepo->workDir(),
        );
        putenv('GIT_AUTHOR_DATE=@1700000010 +0000');
        putenv('GIT_COMMITTER_DATE=@1700000010 +0000');
        $stash->push('complex stash');
        $stash->apply();
        putenv('GIT_AUTHOR_DATE');
        putenv('GIT_COMMITTER_DATE');

        $this->gitWithEnv($gitDir, [
            'GIT_AUTHOR_DATE' => '@1700000010 +0000',
            'GIT_COMMITTER_DATE' => '@1700000010 +0000',
        ], 'stash push -m ' . escapeshellarg('complex stash'));
        $this->gitIn($gitDir, 'stash apply');

        $this->assertSame(file_get_contents($gitDir . '/a.txt'), file_get_contents($pitDir . '/a.txt'));
        $this->assertSame(
            $this->gitIn($gitDir, 'status --porcelain=v2'),
            $this->gitIn($pitDir, 'status --porcelain=v2'),
        );
    }

    #[Test]
    public function stashPopKeepsEntryOnConflictLikeGit(): void
    {
        $source = $this->tmpDir . '/source-conflict';
        $pitDir = $this->tmpDir . '/pit-conflict';
        $gitDir = $this->tmpDir . '/git-conflict';
        $this->createBaseRepo($source);
        $this->copyRepo($source, $pitDir);
        $this->copyRepo($source, $gitDir);

        file_put_contents($pitDir . '/a.txt', "stashed\n");
        file_put_contents($gitDir . '/a.txt', "stashed\n");

        $pitRepo = Pitmaster::open($pitDir);
        $stash = new Stash(
            $pitRepo->objectDatabase(),
            $pitRepo->refDatabase(),
            $pitRepo->gitDir(),
            $pitRepo->workDir(),
        );
        putenv('GIT_AUTHOR_DATE=@1700000020 +0000');
        putenv('GIT_COMMITTER_DATE=@1700000020 +0000');
        $stash->push('conflict stash');
        putenv('GIT_AUTHOR_DATE');
        putenv('GIT_COMMITTER_DATE');

        $this->gitWithEnv($gitDir, [
            'GIT_AUTHOR_DATE' => '@1700000020 +0000',
            'GIT_COMMITTER_DATE' => '@1700000020 +0000',
        ], 'stash push -m ' . escapeshellarg('conflict stash'));

        file_put_contents($pitDir . '/a.txt', "current\n");
        $pitRepo->add('a.txt');
        $pitRepo->commit("Current\n");
        file_put_contents($gitDir . '/a.txt', "current\n");
        $this->gitIn($gitDir, 'add a.txt');
        $this->gitIn($gitDir, 'commit -m "Current"');

        try {
            $stash->pop();
            self::fail('Expected stash pop conflict');
        } catch (MergeConflictException) {
        }

        exec(sprintf('cd %s && git stash pop >/dev/null 2>&1', escapeshellarg($gitDir)), $output, $exitCode);
        $this->assertNotSame(0, $exitCode);

        $this->assertSame(file_get_contents($gitDir . '/a.txt'), file_get_contents($pitDir . '/a.txt'));
        $this->assertSame(trim($this->gitIn($gitDir, 'stash list')), $this->formatList($stash));
    }

    private function createBaseRepo(string $path): void
    {
        mkdir($path, 0777, true);
        $this->gitIn($path, 'init --initial-branch=main');
        $this->gitIn($path, 'config user.email test@pitmaster.dev');
        $this->gitIn($path, 'config user.name "Test User"');
        file_put_contents($path . '/a.txt', "original\n");
        $this->gitWithEnv($path, [
            'GIT_AUTHOR_DATE' => '@1699999990 +0000',
            'GIT_COMMITTER_DATE' => '@1699999990 +0000',
        ], 'add a.txt');
        $this->gitWithEnv($path, [
            'GIT_AUTHOR_DATE' => '@1699999990 +0000',
            'GIT_COMMITTER_DATE' => '@1699999990 +0000',
        ], 'commit -m initial');
    }

    private function copyRepo(string $source, string $target): void
    {
        exec(sprintf('cp -R %s %s', escapeshellarg($source), escapeshellarg($target)), $output, $exitCode);

        if ($exitCode !== 0) {
            $this->fail("Failed to copy repository from {$source} to {$target}");
        }
    }

    private function formatList(Stash $stash): string
    {
        $lines = [];

        foreach ($stash->listEntries() as $entry) {
            $lines[] = "stash@{{$entry['index']}}: {$entry['message']}";
        }

        return implode("\n", $lines);
    }

    private function gitIn(string $dir, string $command): string
    {
        exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command), $output, $exitCode);
        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->fail("git {$command} failed:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }

    /**
     * @param array<string, string> $env
     */
    private function gitWithEnv(string $dir, array $env, string $command): string
    {
        $prefix = [];

        foreach ($env as $name => $value) {
            $prefix[] = $name . '=' . escapeshellarg($value);
        }

        exec(sprintf(
            'cd %s && %s git %s 2>&1',
            escapeshellarg($dir),
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
