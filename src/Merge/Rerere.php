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
        $hash = $this->conflictHash($conflicted);
        $cacheDir = $this->cacheDir($hash);

        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }

        file_put_contents($cacheDir . '/preimage', $conflicted);
        file_put_contents($cacheDir . '/postimage', $resolved);
    }

    /**
     * Try to find a recorded resolution for a conflict.
     *
     * @return string|null The resolved content, or null if no recording found
     */
    public function resolve(string $conflicted): ?string
    {
        $hash = $this->conflictHash($conflicted);
        $postimage = $this->cacheDir($hash) . '/postimage';

        if (!is_file($postimage)) {
            return null;
        }

        $content = file_get_contents($postimage);

        return $content !== false ? $content : null;
    }

    /**
     * Forget a recorded resolution.
     */
    public function forget(string $conflicted): void
    {
        $hash = $this->conflictHash($conflicted);
        $cacheDir = $this->cacheDir($hash);

        if (is_dir($cacheDir)) {
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
        // Normalize: strip branch labels from conflict markers
        $normalized = preg_replace('/^<<<<<<< .+$/m', '<<<<<<<', $content);
        $normalized = preg_replace('/^>>>>>>> .+$/m', '>>>>>>>', $normalized);

        return sha1($normalized ?? $content);
    }

    private function cacheDir(string $hash): string
    {
        return $this->gitDir . '/rr-cache/' . $hash;
    }
}
