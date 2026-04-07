<?php

declare(strict_types=1);

namespace Pitmaster;

/**
 * Static facade. Public API entry point.
 */
final class Pitmaster
{
    /**
     * Open an existing repository.
     */
    public static function open(string $path): Repository
    {
        return new Repository($path);
    }

    /**
     * Initialize a new repository.
     */
    public static function init(string $path): Repository
    {
        $gitDir = $path . '/.git';

        if (is_dir($gitDir)) {
            throw new \RuntimeException("Repository already exists at {$path}");
        }

        mkdir($gitDir, 0777, true);
        mkdir($gitDir . '/objects', 0777, true);
        mkdir($gitDir . '/refs/heads', 0777, true);
        mkdir($gitDir . '/refs/tags', 0777, true);

        file_put_contents($gitDir . '/HEAD', "ref: refs/heads/main\n");
        file_put_contents($gitDir . '/config', implode("\n", [
            '[core]',
            "\trepositoryformatversion = 0",
            "\tfilemode = true",
            "\tbare = false",
            "\tlogallrefupdates = true",
            '',
        ]));

        return new Repository($path);
    }
}
