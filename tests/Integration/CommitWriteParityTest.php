<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Hooks\HookRunner;
use Pitmaster\Pitmaster;

final class CommitWriteParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-commit-parity-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (
            [
            'GIT_AUTHOR_NAME',
            'GIT_AUTHOR_EMAIL',
            'GIT_AUTHOR_DATE',
            'GIT_COMMITTER_NAME',
            'GIT_COMMITTER_EMAIL',
            'GIT_COMMITTER_DATE',
            'PITMASTER_AUTHOR_NAME',
            'PITMASTER_AUTHOR_EMAIL',
            'PITMASTER_AUTHOR_DATE',
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
    public function authorAndCommitterEnvironmentMatchesGitExactly(): void
    {
        $gitDir = $this->tmpDir . '/git-repo';
        $pitDir = $this->tmpDir . '/pit-repo';
        $message = "Subject line\n\nBody paragraph\nSigned-off-by: Trailer <trailer@example.com>\n";
        $messageFile = $this->tmpDir . '/message.txt';

        file_put_contents($messageFile, $message);
        $this->seedRepo($gitDir, "author parity\n");
        $this->seedRepo($pitDir, "author parity\n");
        $this->setIdentityEnv([
            'GIT_AUTHOR_NAME' => 'Alice Author',
            'GIT_AUTHOR_EMAIL' => 'alice@example.com',
            'GIT_AUTHOR_DATE' => '@1712563200 +0200',
            'GIT_COMMITTER_NAME' => 'Chris Committer',
            'GIT_COMMITTER_EMAIL' => 'chris@example.com',
            'GIT_COMMITTER_DATE' => '@1712566800 +0200',
        ]);

        $this->git(
            $gitDir,
            'commit --quiet --allow-empty -F ' . escapeshellarg($messageFile),
        );

        $repo = Pitmaster::open($pitDir);
        $repo->commit($message);

        $this->assertSame(trim($this->git($gitDir, 'rev-parse HEAD')), trim($this->git($pitDir, 'rev-parse HEAD')));
        $this->assertSame($this->git($gitDir, 'cat-file -p HEAD'), $this->git($pitDir, 'cat-file -p HEAD'));
    }

    #[Test]
    public function commitHooksRunInGitOrderAndCanEditMessage(): void
    {
        $gitDir = $this->tmpDir . '/git-hooks';
        $pitDir = $this->tmpDir . '/pit-hooks';

        $this->seedRepo($gitDir, "hook parity\n");
        $this->seedRepo($pitDir, "hook parity\n");
        $this->installCommitHooks($gitDir);
        $this->installCommitHooks($pitDir);

        $this->git($gitDir, 'commit --quiet --allow-empty -m ' . escapeshellarg('Hook subject'));

        $repo = Pitmaster::open($pitDir);
        $repo->commit("Hook subject\n");

        $this->assertSame(
            file_get_contents($gitDir . '/.hook-log'),
            file_get_contents($pitDir . '/.hook-log'),
        );
        $this->assertSame(
            $this->git($gitDir, 'log -1 --format=%B'),
            $this->git($pitDir, 'log -1 --format=%B'),
        );
    }

    #[Test]
    public function failingCommitMsgHookAbortsCommitLikeGit(): void
    {
        $gitDir = $this->tmpDir . '/git-hook-fail';
        $pitDir = $this->tmpDir . '/pit-hook-fail';

        $this->seedRepo($gitDir, "hook fail\n");
        $this->seedRepo($pitDir, "hook fail\n");

        $failingHook = "#!/bin/sh\nexit 1\n";
        (new HookRunner($gitDir . '/.git'))->install('commit-msg', $failingHook);
        (new HookRunner($pitDir . '/.git'))->install('commit-msg', $failingHook);

        $gitResult = $this->gitAllowFailure($gitDir, 'commit --quiet --allow-empty -m ' . escapeshellarg('Will fail'));

        $this->assertNotSame(0, $gitResult['exitCode']);

        $repo = Pitmaster::open($pitDir);

        try {
            $repo->commit("Will fail\n");
            self::fail('Expected commit-msg hook to abort commit');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('commit-msg hook failed', $e->getMessage());
        }

        $this->assertSame(trim($this->git($gitDir, 'rev-parse --verify HEAD 2>/dev/null || true')), trim($this->git($pitDir, 'rev-parse --verify HEAD 2>/dev/null || true')));
    }

    private function seedRepo(string $dir, string $content): void
    {
        mkdir($dir, 0777, true);
        $this->git($this->tmpDir, 'init --initial-branch=main ' . escapeshellarg($dir));
        $this->git($dir, 'config user.email test@example.com');
        $this->git($dir, 'config user.name Test');
        file_put_contents($dir . '/tracked.txt', $content);
        $this->git($dir, 'add tracked.txt');
    }

    private function installCommitHooks(string $dir): void
    {
        $runner = new HookRunner($dir . '/.git');
        $runner->install('pre-commit', "#!/bin/sh\necho pre-commit >> .hook-log\n");
        $runner->install(
            'prepare-commit-msg',
            "#!/bin/sh\n" .
            "echo \"prepare-commit-msg:\${2:-}\" >> .hook-log\n" .
            "printf '\\nPrepared-by: hook\\n' >> \"$1\"\n",
        );
        $runner->install(
            'commit-msg',
            "#!/bin/sh\n" .
            "echo commit-msg >> .hook-log\n" .
            "grep -q '^Prepared-by: hook$' \"$1\"\n",
        );
        $runner->install('post-commit', "#!/bin/sh\necho post-commit >> .hook-log\n");
    }

    /**
     * @param array<string, string> $vars
     */
    private function setIdentityEnv(array $vars): void
    {
        foreach ($vars as $name => $value) {
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
