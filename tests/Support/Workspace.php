<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Support;

final class Workspace
{
    public static function createDirectory(string $prefix): string
    {
        $path = self::root() . '/' . $prefix . bin2hex(random_bytes(8));

        if (!mkdir($path, 0777, true) && !is_dir($path)) {
            throw new \RuntimeException("Failed to create workspace directory: {$path}");
        }

        return $path;
    }

    public static function createFile(string $prefix, string $suffix = ''): string
    {
        $path = self::root() . '/' . $prefix . bin2hex(random_bytes(8)) . $suffix;

        if (file_put_contents($path, '') === false) {
            throw new \RuntimeException("Failed to create workspace file: {$path}");
        }

        return $path;
    }

    public static function remove(string $path): void
    {
        self::stopScenarioServices($path);
        exec(sprintf('rm -rf %s', escapeshellarg($path)));
    }

    private static function stopScenarioServices(string $path): void
    {
        $services = [
            '.scenario-server.pid' => 'pitmaster.test.workspace=' . $path,
            '.scenario-daemon.pid' => '--base-path=' . $path . '/export',
        ];
        foreach ($services as $file => $ownership) {
            $contents = @file_get_contents($path . '/' . $file);
            if (!is_string($contents) || !ctype_digit(trim($contents))) {
                continue;
            }
            $pid = (int) trim($contents);
            if ($pid <= 1) {
                continue;
            }
            $command = shell_exec(sprintf('ps -p %d -o command=', $pid));
            // Copied workspaces contain the same PID file. Only its original
            // owner may stop the service, and stale PIDs must not stop other work.
            if (is_string($command) && str_contains($command, $ownership)) {
                exec(sprintf('kill -TERM %d 2>/dev/null', $pid));
            }
        }
    }

    private static function root(): string
    {
        $path = dirname(__DIR__, 2) . '/.pitmaster/workspaces';

        if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
            throw new \RuntimeException("Failed to create workspace root: {$path}");
        }

        return $path;
    }
}
