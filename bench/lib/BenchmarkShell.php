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
        return self::run(escapeshellarg(self::gitBinary()) . ' ' . $arguments, $cwd, $env);
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
     * @param array<string, scalar|null> $env
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
            $base[$key] = $value === null ? '' : (string) $value;
        }

        return $base;
    }
}
