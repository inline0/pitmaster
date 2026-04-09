<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Tests\Support\Workspace;

final class Sha256RepositoryParityTest extends TestCase
{
    /** @var array<int, string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            Workspace::remove($path);
        }
    }

    #[Test]
    public function opensGitCreatedSha256RepositoryAndMatchesGitState(): void
    {
        $repoDir = $this->createDirectory('sha256-open-');
        $this->git($repoDir, 'init --initial-branch=main --object-format=sha256');
        $this->git($repoDir, 'config user.email test@pitmaster.dev');
        $this->git($repoDir, 'config user.name "Test User"');
        file_put_contents($repoDir . '/tracked.txt', "tracked\n");
        $this->git($repoDir, 'add tracked.txt');
        $this->git($repoDir, 'commit -m initial');
        $this->git($repoDir, 'branch feature');
        $this->git($repoDir, 'tag -a v1 -m "Release 1"');

        $repo = Pitmaster::open($repoDir);
        $head = trim($this->git($repoDir, 'rev-parse HEAD'));
        $status = $this->git($repoDir, 'status --porcelain=v2');

        $this->assertSame('main', $repo->branch());
        $this->assertSame(['feature', 'main'], $repo->branches());
        $this->assertSame(['v1'], $repo->tags());
        $this->assertSame($head, $repo->head()->id->hex);
        $this->assertSame(trim($this->git($repoDir, 'rev-parse refs/tags/v1')), $repo->resolve('refs/tags/v1')->hex);
        $this->assertSame($status, $repo->statusPorcelainV2());
        $this->assertSame(1, $repo->index()->count());

        foreach ($repo->listObjects() as $hash) {
            $this->assertSame(64, strlen($hash));
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
        }
    }

    #[Test]
    public function initMatchesGitForSha256RepoShapeAndDefaults(): void
    {
        $gitDir = $this->createDirectory('sha256-init-git-');
        $pitDir = $this->createDirectory('sha256-init-pit-');

        $this->git($gitDir, 'init --initial-branch=main --object-format=sha256');
        Pitmaster::init($pitDir, 'sha256');

        $this->assertTrue(Pitmaster::isRepository($pitDir));
        $this->assertSame(
            file_get_contents($gitDir . '/.git/HEAD'),
            file_get_contents($pitDir . '/.git/HEAD'),
        );
        $this->assertSame(
            file_get_contents($gitDir . '/.git/description'),
            file_get_contents($pitDir . '/.git/description'),
        );
        $this->assertSame(
            file_get_contents($gitDir . '/.git/info/exclude'),
            file_get_contents($pitDir . '/.git/info/exclude'),
        );
        $this->assertSame($this->configSnapshot($gitDir), $this->configSnapshot($pitDir));
        $this->assertSame($this->layoutSnapshot($gitDir), $this->layoutSnapshot($pitDir));
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->git($gitDir, 'fsck --full'), $this->git($pitDir, 'fsck --full'));
    }

    #[Test]
    public function pitmasterCanCommitAndTagInSha256RepositoryThatGitAccepts(): void
    {
        $repoDir = $this->createDirectory('sha256-write-');

        $this->withIdentityEnv(function () use ($repoDir): void {
            $repo = Pitmaster::init($repoDir, 'sha256');
            file_put_contents($repoDir . '/tracked.txt', "tracked\n");
            $repo->add('tracked.txt');
            $commitId = $repo->commit('Initial commit');
            $tagId = $repo->createTag('v1', "Release 1\n");

            $this->assertSame(64, strlen($commitId->hex));
            $this->assertSame(64, strlen($tagId->hex));
            $this->assertSame($commitId->hex, trim($this->git($repoDir, 'rev-parse HEAD')));
            $this->assertSame($tagId->hex, trim($this->git($repoDir, 'rev-parse refs/tags/v1')));
            $this->assertSame("commit\n", $this->git($repoDir, 'cat-file -t HEAD'));
            $this->assertSame("tag\n", $this->git($repoDir, 'cat-file -t refs/tags/v1'));
            $this->assertSame('', $this->git($repoDir, 'fsck --full'));
            $this->assertSame('', $this->git($repoDir, 'status --porcelain=v2'));
        });
    }

    private function withIdentityEnv(callable $callback): void
    {
        $vars = [
            'PITMASTER_AUTHOR_NAME' => 'Test User',
            'PITMASTER_AUTHOR_EMAIL' => 'test@pitmaster.dev',
            'PITMASTER_AUTHOR_DATE' => '2024-01-15T00:00:10+0000',
            'PITMASTER_COMMITTER_NAME' => 'Test User',
            'PITMASTER_COMMITTER_EMAIL' => 'test@pitmaster.dev',
            'PITMASTER_COMMITTER_DATE' => '2024-01-15T00:00:10+0000',
        ];

        $previous = [];

        foreach ($vars as $name => $value) {
            $previous[$name] = getenv($name);
            putenv("{$name}={$value}");
        }

        try {
            $callback();
        } finally {
            foreach ($previous as $name => $value) {
                if ($value === false) {
                    putenv($name);
                    continue;
                }

                putenv("{$name}={$value}");
            }
        }
    }

    private function configSnapshot(string $dir): string
    {
        $lines = [];

        foreach (
            [
            'core.repositoryformatversion',
            'core.filemode',
            'core.bare',
            'core.ignorecase',
            'core.precomposeunicode',
            'extensions.objectformat',
            ] as $key
        ) {
            $value = trim($this->gitAllowFailure($dir, 'config --local --get ' . escapeshellarg($key)));

            if ($value !== '') {
                $lines[] = "{$key}={$value}";
            }
        }

        return implode("\n", $lines) . ($lines !== [] ? "\n" : '');
    }

    private function layoutSnapshot(string $dir): string
    {
        $lines = [];

        foreach (
            [
            '.git/HEAD',
            '.git/config',
            '.git/description',
            '.git/hooks',
            '.git/info',
            '.git/info/exclude',
            '.git/objects',
            '.git/objects/info',
            '.git/objects/pack',
            '.git/refs',
            '.git/refs/heads',
            '.git/refs/tags',
            ] as $relativePath
        ) {
            $fullPath = $dir . '/' . $relativePath;
            $type = is_dir($fullPath) ? 'dir' : (is_file($fullPath) ? 'file' : 'missing');
            $lines[] = "{$type} {$relativePath}";
        }

        return implode("\n", $lines) . "\n";
    }

    private function git(string $dir, string $command): string
    {
        exec(
            sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command),
            $output,
            $exitCode,
        );

        if ($exitCode !== 0) {
            throw new \RuntimeException("Git command failed: git {$command}\n" . implode("\n", $output));
        }

        return implode("\n", $output) . ($output !== [] ? "\n" : '');
    }

    private function gitAllowFailure(string $dir, string $command): string
    {
        exec(
            sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command),
            $output,
            $exitCode,
        );

        if ($exitCode !== 0) {
            return '';
        }

        return implode("\n", $output) . ($output !== [] ? "\n" : '');
    }

    private function createDirectory(string $prefix): string
    {
        $path = Workspace::createDirectory($prefix);
        $this->paths[] = $path;

        return $path;
    }
}
