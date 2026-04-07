<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Hooks\HookRunner;

/**
 * Test Hooks\HookRunner against a real git repo hooks directory.
 */
final class HooksTest extends TestCase
{
    private string $tmpDir;
    private string $gitDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');
        $this->gitDir = $this->tmpDir . '/.git';

        // Remove sample hooks so they don't interfere
        $hooksDir = $this->gitDir . '/hooks';

        if (is_dir($hooksDir)) {
            foreach (scandir($hooksDir) as $file) {
                if (str_ends_with($file, '.sample')) {
                    unlink($hooksDir . '/' . $file);
                }
            }
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function installAndExistsDetectsHook(): void
    {
        $runner = new HookRunner($this->gitDir);

        $this->assertFalse($runner->exists('pre-commit'));

        $runner->install('pre-commit', "#!/bin/sh\necho hello\n");

        $this->assertTrue($runner->exists('pre-commit'));
    }

    #[Test]
    public function runReturnsExitCodeAndStdout(): void
    {
        $runner = new HookRunner($this->gitDir);
        $runner->install('pre-commit', "#!/bin/sh\necho hello\n");

        $result = $runner->run('pre-commit');

        $this->assertSame(0, $result['exitCode']);
        $this->assertSame("hello\n", $result['stdout']);
        $this->assertSame('', $result['stderr']);
    }

    #[Test]
    public function runFailingHookReturnsNonZero(): void
    {
        $runner = new HookRunner($this->gitDir);
        $runner->install('pre-commit', "#!/bin/sh\nexit 1\n");

        $result = $runner->run('pre-commit');

        $this->assertSame(1, $result['exitCode']);
    }

    #[Test]
    public function runAndCheckReturnsBool(): void
    {
        $runner = new HookRunner($this->gitDir);
        $runner->install('pre-commit', "#!/bin/sh\necho ok\n");

        $this->assertTrue($runner->runAndCheck('pre-commit'));

        $runner->install('commit-msg', "#!/bin/sh\nexit 1\n");
        $this->assertFalse($runner->runAndCheck('commit-msg'));
    }

    #[Test]
    public function listHooksIncludesInstalledHook(): void
    {
        $runner = new HookRunner($this->gitDir);
        $runner->install('pre-commit', "#!/bin/sh\necho hello\n");
        $runner->install('post-commit', "#!/bin/sh\necho done\n");

        $hooks = $runner->listHooks();

        $this->assertContains('pre-commit', $hooks);
        $this->assertContains('post-commit', $hooks);
    }

    #[Test]
    public function listHooksExcludesSampleFiles(): void
    {
        // Write a .sample file manually
        $hooksDir = $this->gitDir . '/hooks';

        if (!is_dir($hooksDir)) {
            mkdir($hooksDir, 0777, true);
        }

        file_put_contents($hooksDir . '/pre-push.sample', "#!/bin/sh\n");
        chmod($hooksDir . '/pre-push.sample', 0755);

        $runner = new HookRunner($this->gitDir);
        $hooks = $runner->listHooks();

        $this->assertNotContains('pre-push.sample', $hooks);
    }

    #[Test]
    public function runNonexistentHookReturnsSuccess(): void
    {
        $runner = new HookRunner($this->gitDir);
        $result = $runner->run('nonexistent-hook');

        // Non-existent hook should not block (exit 0)
        $this->assertSame(0, $result['exitCode']);
    }

    #[Test]
    public function hookReceivesArguments(): void
    {
        $runner = new HookRunner($this->gitDir);
        $runner->install('commit-msg', "#!/bin/sh\necho \"arg: \$1\"\n");

        $result = $runner->run('commit-msg', ['test-message']);

        $this->assertSame(0, $result['exitCode']);
        $this->assertStringContainsString('arg: test-message', $result['stdout']);
    }

    private function git(string $cmd): string
    {
        return shell_exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $cmd)) ?? '';
    }
}
