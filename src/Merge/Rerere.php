<?php

declare(strict_types=1);

namespace Pitmaster\Merge;

/**
 * Rerere: reuse recorded resolution of merge conflicts.
 *
 * When a conflict is resolved, the resolution is recorded in
 * .git/rr-cache/<hash>/. When the same conflict is seen again,
 * the recorded resolution is applied automatically.
 *
 * The hash is computed from the conflicted content (normalized:
 * the conflict markers but not the branch names).
 */
final class Rerere
{
    public function __construct(private readonly string $gitDir)
    {
    }

    /**
     * Check if rerere is enabled in config.
     */
    public function isEnabled(): bool
    {
        $configPath = $this->gitDir . '/config';

        if (!is_file($configPath)) {
            return false;
        }

        $content = file_get_contents($configPath);

        return $content !== false && (
            str_contains($content, 'rerere.enabled = true') ||
            str_contains($content, 'rerere.enabled=true')
        );
    }

    /**
     * Record a conflict resolution.
     *
     * @param string $conflicted The conflicted file content (with markers)
     * @param string $resolved The resolved file content
     */
    public function record(string $conflicted, string $resolved): void
    {
        $normalized = $this->normalizeConflict($conflicted);
        $hash = $this->conflictHash($conflicted);
        $cacheDir = $this->cacheDir($hash);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        file_put_contents($cacheDir . '/preimage', $normalized);
        file_put_contents($cacheDir . '/postimage', $resolved);
    }

    /**
     * Try to find a recorded resolution for a conflict.
     *
     * @return string|null The resolved content, or null if no recording found
     */
    public function resolve(string $conflicted): ?string
    {
        $cacheDir = $this->findMatchingCacheDir($conflicted);

        if ($cacheDir === null) {
            return null;
        }

        $postimage = $cacheDir . '/postimage';

        $content = file_get_contents($postimage);

        return $content !== false ? $content : null;
    }

    /**
     * Forget a recorded resolution.
     */
    public function forget(string $conflicted): void
    {
        $cacheDir = $this->findMatchingCacheDir($conflicted);

        if ($cacheDir !== null && is_dir($cacheDir)) {
            foreach (scandir($cacheDir) as $file) {
                if ($file !== '.' && $file !== '..') {
                    unlink($cacheDir . '/' . $file);
                }
            }

            rmdir($cacheDir);
        }
    }

    /**
     * List all recorded resolutions.
     *
     * @return array<int, string> Resolution hashes
     */
    public function listRecorded(): array
    {
        $rrCache = $this->gitDir . '/rr-cache';

        if (!is_dir($rrCache)) {
            return [];
        }

        $hashes = [];

        foreach (scandir($rrCache) as $entry) {
            if ($entry === '.' || $entry === '..' || !is_dir($rrCache . '/' . $entry)) {
                continue;
            }

            if (is_file($rrCache . '/' . $entry . '/postimage')) {
                $hashes[] = $entry;
            }
        }

        return $hashes;
    }

    /**
     * Compute a hash for a conflict by normalizing the conflict markers
     * (remove branch-specific labels, keep only the conflicting content).
     */
    private function conflictHash(string $content): string
    {
        $normalized = $this->normalizeConflict($content);
        $lines = preg_split("/\r\n|\n|\r/", $normalized) ?: [];
        $payload = '';
        $count = count($lines);
        $i = 0;

        while ($i < $count) {
            if ($lines[$i] !== '<<<<<<<') {
                $i++;
                continue;
            }

            $i++;
            $first = [];
            $second = [];

            while ($i < $count && $lines[$i] !== '=======') {
                $first[] = $lines[$i];
                $i++;
            }

            if ($i >= $count) {
                return sha1($normalized);
            }

            $i++;

            while ($i < $count && $lines[$i] !== '>>>>>>>') {
                $second[] = $lines[$i];
                $i++;
            }

            if ($i >= $count) {
                return sha1($normalized);
            }

            $payload .= implode("\n", $first) . "\n\0";
            $payload .= implode("\n", $second) . "\n\0";
            $i++;
        }

        return sha1($payload !== '' ? $payload : $normalized);
    }

    private function cacheDir(string $hash): string
    {
        return $this->gitDir . '/rr-cache/' . $hash;
    }

    private function findMatchingCacheDir(string $conflicted): ?string
    {
        $direct = $this->cacheDir($this->conflictHash($conflicted));

        if (is_file($direct . '/postimage') || is_file($direct . '/preimage')) {
            return $direct;
        }

        $rrCache = $this->gitDir . '/rr-cache';
        $normalized = $this->normalizeConflict($conflicted);

        if (!is_dir($rrCache)) {
            return null;
        }

        foreach (scandir($rrCache) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $candidate = $rrCache . '/' . $entry;
            $preimage = $candidate . '/preimage';

            if (!is_file($preimage)) {
                continue;
            }

            if (file_get_contents($preimage) === $normalized) {
                return $candidate;
            }
        }

        return null;
    }

    private function normalizeConflict(string $content): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $content) ?: [];

        if ($lines !== [] && end($lines) === '') {
            array_pop($lines);
        }

        $normalized = [];
        $count = count($lines);
        $i = 0;

        while ($i < $count) {
            $line = $lines[$i];

            if (!str_starts_with($line, '<<<<<<<')) {
                $normalized[] = $line;
                $i++;
                continue;
            }

            $i++;
            $ours = [];
            $theirs = [];

            while ($i < $count && $lines[$i] !== '=======') {
                $ours[] = $lines[$i];
                $i++;
            }

            if ($i >= $count) {
                return $content;
            }

            $i++;

            while ($i < $count && !str_starts_with($lines[$i], '>>>>>>>')) {
                $theirs[] = $lines[$i];
                $i++;
            }

            if ($i >= $count) {
                return $content;
            }

            $normalized[] = '<<<<<<<';
            array_push($normalized, ...$theirs);
            $normalized[] = '=======';
            array_push($normalized, ...$ours);
            $normalized[] = '>>>>>>>';
            $i++;
        }

        return implode("\n", $normalized) . "\n";
    }
}
