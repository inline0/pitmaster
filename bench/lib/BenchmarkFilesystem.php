<?php

declare(strict_types=1);

namespace Pitmaster\Bench;

final class BenchmarkFilesystem
{
    public static function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
    }

    public static function removeDirectory(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir() && !$item->isLink()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($path);
    }

    public static function copyDirectory(string $source, string $destination): void
    {
        self::removeDirectory($destination);
        self::ensureDirectory($destination);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $target = $destination . '/' . $iterator->getSubPathName();

            if ($item->isDir()) {
                self::ensureDirectory($target);
                continue;
            }

            self::ensureDirectory(dirname($target));
            copy($item->getPathname(), $target);
            chmod($target, $item->getPerms() & 0777);
        }
    }

    public static function writeFile(string $path, string $content): void
    {
        self::ensureDirectory(dirname($path));
        file_put_contents($path, $content);
    }
}
