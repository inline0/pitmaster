<?php

declare(strict_types=1);

namespace Pitmaster\Status;

use Pitmaster\Config\GitConfig;

/**
 * Filesystem monitor integration.
 *
 * Git's canonical fsmonitor protocol invokes a configured hook/command with:
 *   query-fsmonitor 2 <last-token>
 *
 * The command returns a NUL-delimited payload:
 *   <next-token>\0<path>\0<path>\0...
 *
 * When no usable fsmonitor hook is configured or the command fails, Pitmaster
 * falls back to a polling scan so status/diff remain functional.
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
        $value = GitConfig::fromFile($this->gitDir . '/config')->get('core.fsmonitor');

        return $value !== null && !$this->isFalseLike($value);
    }

    /**
     * Query for changed files since the given token (timestamp).
     *
     * @return array{files: array<int, string>, token: string}
     */
    public function query(?string $lastToken = null): array
    {
        $hookResult = $this->queryHook($lastToken);

        if ($hookResult !== null) {
            return $hookResult;
        }

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

    /**
     * @return array{files: array<int, string>, token: string}|null
     */
    private function queryHook(?string $lastToken): ?array
    {
        $command = $this->resolveHookCommand();

        if ($command === null) {
            return null;
        }

        $queryToken = $lastToken ?? '';
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            $command . ' ' . escapeshellarg('2') . ' ' . escapeshellarg($queryToken),
            $descriptors,
            $pipes,
            $this->workDir,
            [
                'GIT_DIR' => $this->gitDir,
                'GIT_WORK_TREE' => $this->workDir,
            ],
        );

        if (!is_resource($process)) {
            return null;
        }

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || ($stdout === '' && trim($stderr) !== '')) {
            return null;
        }

        return $this->parseHookOutput($stdout);
    }

    /**
     * @return array{files: array<int, string>, token: string}|null
     */
    private function parseHookOutput(string $output): ?array
    {
        $payload = rtrim($output, "\0\r\n");

        if ($payload === '') {
            return null;
        }

        $parts = explode("\0", $payload);
        $token = array_shift($parts);

        if ($token === null || $token === '') {
            return null;
        }

        $files = array_values(array_filter($parts, static fn (string $path): bool => $path !== ''));

        return [
            'files' => $files,
            'token' => $token,
        ];
    }

    private function resolveHookCommand(): ?string
    {
        $value = GitConfig::fromFile($this->gitDir . '/config')->get('core.fsmonitor');

        if ($value === null || $this->isFalseLike($value)) {
            return null;
        }

        $path = $this->resolveConfiguredPath($value);

        if ($path !== null) {
            return escapeshellarg($path);
        }

        return $value;
    }

    private function resolveConfiguredPath(string $value): ?string
    {
        if ($value === '' || $this->isTrueLike($value)) {
            return null;
        }

        if (str_starts_with($value, '/')) {
            return is_file($value) ? $value : null;
        }

        $worktreePath = $this->workDir . '/' . $value;

        if (is_file($worktreePath)) {
            return $worktreePath;
        }

        $gitPath = $this->gitDir . '/' . $value;

        if (is_file($gitPath)) {
            return $gitPath;
        }

        return null;
    }

    private function isFalseLike(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['false', 'no', 'off', '0'], true);
    }

    private function isTrueLike(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['true', 'yes', 'on', '1'], true);
    }
}
