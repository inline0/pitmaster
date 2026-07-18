<?php

declare(strict_types=1);

namespace Pitmaster\Bench;

final class BenchmarkShell
{
    private static ?string $gitBinary = null;
    private static ?string $gitExecPath = null;

    public static function gitBinary(): string
    {
        if (self::$gitBinary !== null) {
            return self::$gitBinary;
        }

        $resolved = trim(shell_exec('command -v git') ?? '');

        return self::$gitBinary = ($resolved !== '' ? $resolved : 'git');
    }

    public static function gitExecPath(): string
    {
        if (self::$gitExecPath !== null) {
            return self::$gitExecPath;
        }

        return self::$gitExecPath = trim(self::run(escapeshellarg(self::gitBinary()) . ' --exec-path'));
    }

    public static function git(string $arguments, string $cwd, array $env = []): string
    {
        return self::run(escapeshellarg(self::gitBinary()) . ' ' . $arguments, $cwd, array_replace(
            self::gitRepositoryIsolationEnv(),
            self::gitBackgroundMaintenanceSuppressionEnv(),
            $env,
        ));
    }

    public static function run(string $command, ?string $cwd = null, array $env = []): string
    {
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $cwd,
            self::normalizedEnv($env),
        );

        if (!is_resource($process)) {
            throw new \RuntimeException("Failed to start command: {$command}");
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new \RuntimeException(sprintf(
                "Command failed (%d): %s\n%s",
                $exitCode,
                $command,
                trim((string) $stderr),
            ));
        }

        return (string) $stdout;
    }

    /**
     * @return array<string, null>
     */
    private static function gitRepositoryIsolationEnv(): array
    {
        return [
            'GIT_ALTERNATE_OBJECT_DIRECTORIES' => null,
            'GIT_COMMON_DIR' => null,
            'GIT_DIR' => null,
            'GIT_INDEX_FILE' => null,
            'GIT_NAMESPACE' => null,
            'GIT_OBJECT_DIRECTORY' => null,
            'GIT_QUARANTINE_PATH' => null,
            'GIT_WORK_TREE' => null,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function gitBackgroundMaintenanceSuppressionEnv(): array
    {
        return [
            'GIT_CONFIG_COUNT' => '2',
            'GIT_CONFIG_KEY_0' => 'maintenance.auto',
            'GIT_CONFIG_VALUE_0' => 'false',
            'GIT_CONFIG_KEY_1' => 'gc.autoDetach',
            'GIT_CONFIG_VALUE_1' => 'false',
        ];
    }

    /**
     * @param array<string, scalar|null> $env Null values remove inherited environment variables.
     * @return array<string, string>
     */
    private static function normalizedEnv(array $env): array
    {
        $base = [];

        foreach ([...$_SERVER, ...$_ENV] as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (is_scalar($value)) {
                $base[$key] = (string) $value;
            }
        }

        foreach ($env as $key => $value) {
            if ($value === null) {
                unset($base[$key]);
                continue;
            }

            $base[$key] = (string) $value;
        }

        return $base;
    }
}
