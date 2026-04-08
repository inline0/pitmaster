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
        exec(sprintf('rm -rf %s', escapeshellarg($path)));
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
