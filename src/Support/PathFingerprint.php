<?php

declare(strict_types=1);

namespace Pitmaster\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class PathFingerprint
{
    /** @var array<string, string> */
    private static array $cache = [];

    /**
     * @param array<int, string> $paths
     */
    public static function forPaths(array $paths): string
    {
        sort($paths);
        $cacheKey = sha1(implode("\n", $paths));

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $hash = hash_init('sha1');

        foreach ($paths as $path) {
            self::updateHashForPath($hash, $path);
        }

        return self::$cache[$cacheKey] = hash_final($hash);
    }

    /**
     * @param \HashContext $hash
     */
    private static function updateHashForPath($hash, string $path): void
    {
        if (!file_exists($path)) {
            hash_update($hash, "missing:{$path}\n");

            return;
        }

        if (is_file($path)) {
            hash_update($hash, sprintf(
                "file:%s:%d:%d\n",
                $path,
                filemtime($path) ?: 0,
                filesize($path) ?: 0,
            ));

            return;
        }

        hash_update($hash, "dir:{$path}\n");
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            hash_update($hash, sprintf(
                "file:%s:%d:%d\n",
                $fileInfo->getPathname(),
                $fileInfo->getMTime(),
                $fileInfo->getSize(),
            ));
        }
    }
}
