<?php

declare(strict_types=1);

namespace Pitmaster\Status;

/**
 * Filesystem monitor integration.
 *
 * Tracks which files have changed since the last query, allowing
 * status/diff to skip unchanged files. Uses a token-based protocol:
 * query with last token, receive list of changed files + new token.
 *
 * In pure PHP, we implement a polling-based monitor using file
 * modification times and a cached state file.
 */
final class Fsmonitor
{
    private const STATE_FILE = 'fsmonitor--daemon/state';

    public function __construct(
        private readonly string $gitDir,
        private readonly string $workDir,
    ) {
    }

    /**
     * Check if fsmonitor is enabled.
     */
    public function isEnabled(): bool
    {
        $configPath = $this->gitDir . '/config';

        if (!is_file($configPath)) {
            return false;
        }

        $content = file_get_contents($configPath);

        return $content !== false && str_contains($content, 'core.fsmonitor');
    }

    /**
     * Query for changed files since the given token (timestamp).
     *
     * @return array{files: array<int, string>, token: string}
     */
    public function query(?string $lastToken = null): array
    {
        $lastTime = $lastToken !== null ? (int) $lastToken : 0;
        $currentTime = time();
        $changed = [];

        $this->scanForChanges($this->workDir, '', $lastTime, $changed);

        return [
            'files' => $changed,
            'token' => (string) $currentTime,
        ];
    }

    /**
     * Get the last saved token.
     */
    public function lastToken(): ?string
    {
        $statePath = $this->gitDir . '/' . self::STATE_FILE;

        if (!is_file($statePath)) {
            return null;
        }

        $content = file_get_contents($statePath);

        return $content !== false ? trim($content) : null;
    }

    /**
     * Save a token for future queries.
     */
    public function saveToken(string $token): void
    {
        $stateDir = dirname($this->gitDir . '/' . self::STATE_FILE);

        if (!is_dir($stateDir)) {
            mkdir($stateDir, 0777, true);
        }

        file_put_contents($this->gitDir . '/' . self::STATE_FILE, $token . "\n");
    }

    /**
     * @param array<int, string> $changed
     */
    private function scanForChanges(string $dir, string $prefix, int $since, array &$changed): void
    {
        $entries = scandir($dir);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $name) {
            if ($name === '.' || $name === '..' || $name === '.git') {
                continue;
            }

            $fullPath = $dir . '/' . $name;
            $relPath = $prefix !== '' ? $prefix . '/' . $name : $name;

            if (is_dir($fullPath)) {
                $this->scanForChanges($fullPath, $relPath, $since, $changed);
                continue;
            }

            if (is_file($fullPath)) {
                $mtime = filemtime($fullPath);

                if ($mtime !== false && $mtime > $since) {
                    $changed[] = $relPath;
                }
            }
        }
    }
}
