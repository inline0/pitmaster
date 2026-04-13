<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Oracle;

use RuntimeException;

final class CommandCapture
{
    /**
     * @param array<string, string> $environment
     * @return array{stdout: string, stderr: string, exitCode: int, combined: string}
     */
    public static function run(string $command, string $cwd, array $environment = []): array
    {
        $baseEnvironment = getenv();

        if (!is_array($baseEnvironment)) {
            $baseEnvironment = [];
        }

        $process = proc_open(
            $command,
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $cwd,
            array_merge($baseEnvironment, $environment),
        );

        if (!is_resource($process)) {
            throw new RuntimeException("Failed to start command: {$command}");
        }

        $stdout = stream_get_contents($pipes[1]) ?: '';
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        return [
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exitCode' => $exitCode,
            'combined' => $stdout . $stderr,
        ];
    }
}
