<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration\Support;

final class GitTestRuntime
{
    private static ?string $gitBinary = null;
    private static ?string $gitExecPath = null;

    public static function gitBinary(): string
    {
        if (self::$gitBinary !== null) {
            return self::$gitBinary;
        }

        $binary = trim(self::run('command -v git'));

        if ($binary === '') {
            $binary = 'git';
        }

        return self::$gitBinary = $binary;
    }

    public static function gitHttpBackend(): string
    {
        $override = getenv('PITMASTER_TEST_GIT_HTTP_BACKEND');

        if (is_string($override) && $override !== '') {
            return $override;
        }

        return self::gitExecPath() . '/git-http-backend';
    }

    public static function gitDaemonCommand(): string
    {
        return escapeshellarg(self::gitBinary()) . ' daemon';
    }

    private static function gitExecPath(): string
    {
        if (self::$gitExecPath !== null) {
            return self::$gitExecPath;
        }

        $path = trim(self::run(escapeshellarg(self::gitBinary()) . ' --exec-path'));

        if ($path === '') {
            throw new \RuntimeException('Unable to resolve git exec path for integration tests');
        }

        return self::$gitExecPath = $path;
    }

    private static function run(string $command): string
    {
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException(sprintf(
                "Command failed while resolving Git test runtime: %s\n%s",
                $command,
                implode("\n", $output),
            ));
        }

        return implode("\n", $output);
    }
}
