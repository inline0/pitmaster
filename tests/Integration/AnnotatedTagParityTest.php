<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\Blob;
use Pitmaster\Pitmaster;

final class AnnotatedTagParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-tag-parity-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (
            [
            'GIT_AUTHOR_DATE',
            'GIT_COMMITTER_NAME',
            'GIT_COMMITTER_EMAIL',
            'GIT_COMMITTER_DATE',
            'PITMASTER_COMMITTER_NAME',
            'PITMASTER_COMMITTER_EMAIL',
            'PITMASTER_COMMITTER_DATE',
            ] as $name
        ) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }

        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function annotatedCommitTagMatchesGitExactly(): void
    {
        $gitDir = $this->tmpDir . '/git-repo';
        $pitDir = $this->tmpDir . '/pit-repo';

        $this->seedRepo($gitDir);
        $this->seedRepo($pitDir);
        $this->setTaggerEnv();

        $this->git($gitDir, 'tag -a v1.0 -m ' . escapeshellarg('Release 1.0'));

        $repo = Pitmaster::open($pitDir);
        $repo->createTag('v1.0', "Release 1.0\n");

        $this->assertSame(trim($this->git($gitDir, 'rev-parse refs/tags/v1.0')), trim($this->git($pitDir, 'rev-parse refs/tags/v1.0')));
        $this->assertSame($this->git($gitDir, 'cat-file -p refs/tags/v1.0'), $this->git($pitDir, 'cat-file -p refs/tags/v1.0'));
    }

    #[Test]
    public function annotatedTagUsesTheTargetObjectType(): void
    {
        $dir = $this->tmpDir . '/repo';

        $this->seedRepo($dir);
        $this->setTaggerEnv();

        $repo = Pitmaster::open($dir);
        $blob = Blob::fromContent("blob tag target\n");
        $repo->writeObject($blob);
        $tagId = $repo->createTag('blob-tag', "Blob target\n", $blob->id);

        $this->assertSame('tag', trim($this->git($dir, 'cat-file -t ' . $tagId->hex)));
        $this->assertStringContainsString("object {$blob->id->hex}\n", $this->git($dir, 'cat-file -p refs/tags/blob-tag'));
        $this->assertStringContainsString("type blob\n", $this->git($dir, 'cat-file -p refs/tags/blob-tag'));
    }

    #[Test]
    public function unsignedAnnotatedTagsFailGitVerificationConsistently(): void
    {
        $gitDir = $this->tmpDir . '/git-verify';
        $pitDir = $this->tmpDir . '/pit-verify';

        $this->seedRepo($gitDir);
        $this->seedRepo($pitDir);
        $this->setTaggerEnv();

        $this->git($gitDir, 'tag -a v1.0 -m ' . escapeshellarg('Unsigned release'));

        $repo = Pitmaster::open($pitDir);
        $repo->createTag('v1.0', "Unsigned release\n");

        $gitResult = $this->gitAllowFailure($gitDir, 'verify-tag v1.0');
        $pitResult = $this->gitAllowFailure($pitDir, 'verify-tag v1.0');

        $this->assertNotSame(0, $gitResult['exitCode']);
        $this->assertSame($gitResult['exitCode'], $pitResult['exitCode']);
    }

    private function seedRepo(string $dir): void
    {
        mkdir($dir, 0777, true);
        $this->git($this->tmpDir, 'init --initial-branch=main ' . escapeshellarg($dir));
        $this->git($dir, 'config user.email test@example.com');
        $this->git($dir, 'config user.name Test');
        file_put_contents($dir . '/tracked.txt', "content\n");
        $this->git($dir, 'add tracked.txt');

        $previousAuthor = getenv('GIT_AUTHOR_DATE');
        $previousCommitter = getenv('GIT_COMMITTER_DATE');
        putenv('GIT_AUTHOR_DATE=@1712566800 +0200');
        putenv('GIT_COMMITTER_DATE=@1712566800 +0200');

        try {
            $this->git($dir, 'commit -m initial');
        } finally {
            putenv($previousAuthor === false ? 'GIT_AUTHOR_DATE' : 'GIT_AUTHOR_DATE=' . $previousAuthor);
            putenv($previousCommitter === false ? 'GIT_COMMITTER_DATE' : 'GIT_COMMITTER_DATE=' . $previousCommitter);
        }
    }

    private function setTaggerEnv(): void
    {
        foreach (
            [
            'GIT_COMMITTER_NAME' => 'Tagger Test',
            'GIT_COMMITTER_EMAIL' => 'tagger@example.com',
            'GIT_COMMITTER_DATE' => '@1712570400 +0200',
            'PITMASTER_COMMITTER_NAME' => 'Tagger Test',
            'PITMASTER_COMMITTER_EMAIL' => 'tagger@example.com',
            'PITMASTER_COMMITTER_DATE' => '@1712570400 +0200',
            ] as $name => $value
        ) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    private function git(string $dir, string $command): string
    {
        exec(
            sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command),
            $output,
            $exitCode,
        );

        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            self::fail("git {$command} failed in {$dir}:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }

    /**
     * @return array{exitCode: int, output: string}
     */
    private function gitAllowFailure(string $dir, string $command): array
    {
        exec(
            sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command),
            $output,
            $exitCode,
        );

        return [
            'exitCode' => $exitCode,
            'output' => implode("\n", $output),
        ];
    }
}
