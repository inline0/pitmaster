<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CliErrorParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-cli-error-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init -b main');
        $this->git('config user.email test@example.com');
        $this->git('config user.name Test');
        file_put_contents($this->tmpDir . '/app.txt', "base\n");
        $this->git('add app.txt');
        $this->git('commit -m base');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function mergeContinueWithoutStateMatchesGitCli(): void
    {
        $this->assertCliFailureMatchesGit('merge --continue');
    }

    #[Test]
    public function rebaseContinueWithoutStateMatchesGitCli(): void
    {
        $this->assertCliFailureMatchesGit('rebase --continue');
    }

    #[Test]
    public function cherryPickContinueWithoutStateMatchesGitCli(): void
    {
        $this->assertCliFailureMatchesGit('cherry-pick --continue');
    }

    #[Test]
    public function revertContinueWithoutStateMatchesGitCli(): void
    {
        $this->assertCliFailureMatchesGit('revert --continue');
    }

    #[Test]
    public function checkoutOverwriteFailureMatchesGitExitCodeAndSignal(): void
    {
        $this->git('checkout -b topic');
        file_put_contents($this->tmpDir . '/app.txt', "topic\n");
        $this->git('commit -am topic');
        $this->git('checkout main');
        file_put_contents($this->tmpDir . '/app.txt', "local\n");

        $git = $this->gitAllowFailure('checkout topic');
        $pit = $this->pitAllowFailure('checkout topic');

        $this->assertSame($git['exitCode'], $pit['exitCode']);
        $this->assertStringContainsString('would be overwritten by checkout', $git['stderr']);
        $this->assertStringContainsString('would be overwritten by checkout', $pit['stderr']);
    }

    private function assertCliFailureMatchesGit(string $command): void
    {
        $git = $this->gitAllowFailure($command);
        $pit = $this->pitAllowFailure($command);

        $this->assertSame($git['exitCode'], $pit['exitCode']);
        $this->assertSame($git['stdout'], $pit['stdout']);
        $this->assertSame($git['stderr'], $pit['stderr']);
    }

    private function git(string $command): string
    {
        $result = $this->gitAllowFailure($command);

        if ($result['exitCode'] !== 0) {
            self::fail("git {$command} failed:\n{$result['stderr']}");
        }

        return $result['stdout'];
    }

    /**
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function gitAllowFailure(string $command): array
    {
        return $this->capture(
            sprintf('cd %s && git %s', escapeshellarg($this->tmpDir), $command),
        );
    }

    /**
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function pitAllowFailure(string $command): array
    {
        return $this->capture(
            sprintf(
                'cd %s && php %s %s',
                escapeshellarg($this->tmpDir),
                escapeshellarg(dirname(__DIR__, 2) . '/bin/pitmaster'),
                $command,
            ),
        );
    }

    /**
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    private function capture(string $command): array
    {
        $process = proc_open(
            $command,
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (!is_resource($process)) {
            self::fail("Failed to start command: {$command}");
        }

        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }
}
