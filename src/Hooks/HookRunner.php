<?php

declare(strict_types=1);

namespace Pitmaster\Hooks;

/**
 * Git hook detection and invocation.
 *
 * Hooks are executable scripts in .git/hooks/. Pitmaster detects them
 * and invokes them at the appropriate points using PHP's process control.
 *
 * Supported hooks:
 *   pre-commit, prepare-commit-msg, commit-msg, post-commit,
 *   pre-rebase, post-checkout, post-merge, pre-push,
 *   pre-receive, update, post-receive, post-update,
 *   pre-auto-gc, post-rewrite
 */
final class HookRunner
{
    public function __construct(private readonly string $gitDir)
    {
    }

    /**
     * Check if a hook exists and is executable.
     */
    public function exists(string $hookName): bool
    {
        $path = $this->hookPath($hookName);

        return is_file($path) && is_executable($path);
    }

    /**
     * Run a hook. Returns the exit code.
     *
     * @param array<int, string> $args Arguments to pass to the hook
     * @param string|null $stdin Data to pipe to stdin
     * @return array{exitCode: int, stdout: string, stderr: string}
     */
    public function run(string $hookName, array $args = [], ?string $stdin = null): array
    {
        $path = $this->hookPath($hookName);

        if (!is_file($path) || !is_executable($path)) {
            return ['exitCode' => 0, 'stdout' => '', 'stderr' => ''];
        }

        $command = escapeshellarg($path);

        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }

        $descriptors = [
            0 => $stdin !== null ? ['pipe', 'r'] : ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = [
            'GIT_DIR' => $this->gitDir,
            'GIT_WORK_TREE' => dirname($this->gitDir),
        ];

        $process = proc_open($command, $descriptors, $pipes, dirname($this->gitDir), $env);

        if (!is_resource($process)) {
            return ['exitCode' => 1, 'stdout' => '', 'stderr' => 'Failed to start hook'];
        }

        if ($stdin !== null && isset($pipes[0])) {
            fwrite($pipes[0], $stdin);
            fclose($pipes[0]);
        }

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return ['exitCode' => $exitCode, 'stdout' => $stdout, 'stderr' => $stderr];
    }

    /**
     * Run a hook and return whether it succeeded (exit 0).
     *
     * @param array<int, string> $args
     */
    public function runAndCheck(string $hookName, array $args = [], ?string $stdin = null): bool
    {
        $result = $this->run($hookName, $args, $stdin);

        return $result['exitCode'] === 0;
    }

    /**
     * List all available hooks.
     *
     * @return array<int, string>
     */
    public function listHooks(): array
    {
        $hooksDir = $this->gitDir . '/hooks';

        if (!is_dir($hooksDir)) {
            return [];
        }

        $hooks = [];

        foreach (scandir($hooksDir) as $file) {
            if ($file === '.' || $file === '..' || str_ends_with($file, '.sample')) {
                continue;
            }

            $path = $hooksDir . '/' . $file;

            if (is_file($path) && is_executable($path)) {
                $hooks[] = $file;
            }
        }

        return $hooks;
    }

    /**
     * Install a hook script.
     */
    public function install(string $hookName, string $content): void
    {
        $hooksDir = $this->gitDir . '/hooks';

        if (!is_dir($hooksDir)) {
            mkdir($hooksDir, 0777, true);
        }

        $path = $hooksDir . '/' . $hookName;
        file_put_contents($path, $content);
        chmod($path, 0755);
    }

    private function hookPath(string $hookName): string
    {
        return $this->gitDir . '/hooks/' . $hookName;
    }
}
