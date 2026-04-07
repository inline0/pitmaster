<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

use Pitmaster\Object\ObjectId;

/**
 * Shallow clone support.
 *
 * A shallow clone has a .git/shallow file listing commit hashes where
 * the history is truncated. Parents beyond the shallow boundary are
 * not fetched.
 */
final class ShallowClone
{
    /**
     * Read the shallow file.
     *
     * @return array<int, ObjectId> Shallow boundary commits
     */
    public static function readShallow(string $gitDir): array
    {
        $path = $gitDir . '/shallow';

        if (!is_file($path)) {
            return [];
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return [];
        }

        $commits = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if (strlen($line) === 40 && ctype_xdigit($line)) {
                $commits[] = ObjectId::fromHex($line);
            }
        }

        return $commits;
    }

    /**
     * Write the shallow file.
     *
     * @param array<int, ObjectId> $commits
     */
    public static function writeShallow(string $gitDir, array $commits): void
    {
        if ($commits === []) {
            $path = $gitDir . '/shallow';

            if (is_file($path)) {
                unlink($path);
            }

            return;
        }

        $lines = array_map(fn (ObjectId $id) => $id->hex, $commits);
        file_put_contents($gitDir . '/shallow', implode("\n", $lines) . "\n");
    }

    /**
     * Check if a repository is shallow.
     */
    public static function isShallow(string $gitDir): bool
    {
        return is_file($gitDir . '/shallow');
    }

    /**
     * Build shallow fetch request lines (deepen).
     */
    public static function buildDeepenRequest(int $depth): string
    {
        return PktLine::encode("deepen {$depth}\n");
    }

    /**
     * Build shallow fetch request lines (deepen-since).
     */
    public static function buildDeepenSinceRequest(int $timestamp): string
    {
        return PktLine::encode("deepen-since {$timestamp}\n");
    }
}
