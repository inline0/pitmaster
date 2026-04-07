<?php

declare(strict_types=1);

namespace Pitmaster\Ref;

use Pitmaster\Object\ObjectId;

/**
 * Reads and writes loose refs from .git/refs/ and .git/HEAD.
 *
 * Loose refs are individual files containing a 40-char hex hash or
 * a symbolic reference ("ref: refs/heads/main").
 */
final class LooseRefStore implements RefStore
{
    private readonly string $commonDir;

    /**
     * @param string $gitDir Per-worktree git dir (HEAD lives here)
     * @param string|null $commonDir Common dir for shared refs/ (null = same as gitDir)
     */
    public function __construct(
        private readonly string $gitDir,
        ?string $commonDir = null,
    ) {
        $this->commonDir = $commonDir ?? $gitDir;
    }

    public function resolve(string $name, int $depth = 0): ?ObjectId
    {
        // Guard against circular symbolic refs (e.g. refs/heads/loop -> refs/heads/loop)
        if ($depth > 10) {
            return null;
        }

        $content = $this->readRefFile($name);

        if ($content === null) {
            return null;
        }

        // Follow symbolic refs
        $symbolic = SymbolicRef::parse($name, $content);

        if ($symbolic !== null) {
            return $this->resolve($symbolic->target, $depth + 1);
        }

        $hex = trim($content);

        if (strlen($hex) >= 40 && ctype_xdigit(substr($hex, 0, 40))) {
            return ObjectId::fromHex(substr($hex, 0, 40));
        }

        return null;
    }

    public function exists(string $name): bool
    {
        return $this->readRefFile($name) !== null;
    }

    /**
     * @return array<string, ObjectId>
     */
    public function list(): array
    {
        $refs = [];

        // Scan common dir refs first, then per-worktree refs override
        $commonRefsDir = $this->commonDir . '/refs';

        if (is_dir($commonRefsDir)) {
            $this->scanRefsDir($commonRefsDir, 'refs', $refs);
        }

        // Per-worktree refs (if different from common)
        $refsDir = $this->gitDir . '/refs';

        if ($refsDir !== $commonRefsDir && is_dir($refsDir)) {
            $this->scanRefsDir($refsDir, 'refs', $refs);
        }

        return $refs;
    }

    /**
     * Read the HEAD symbolic ref target.
     */
    public function readHead(): ?SymbolicRef
    {
        $content = $this->readRefFile('HEAD');

        if ($content === null) {
            return null;
        }

        return SymbolicRef::parse('HEAD', $content);
    }

    /**
     * Resolve HEAD to an ObjectId (following symbolic ref).
     */
    public function resolveHead(): ?ObjectId
    {
        return $this->resolve('HEAD');
    }

    /**
     * Write a ref file.
     */
    public function update(string $name, ObjectId $target): void
    {
        $path = $this->refPath($name);
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $target->hex . "\n");
    }

    /**
     * Write a symbolic ref.
     */
    public function updateSymbolic(string $name, string $target): void
    {
        $path = $this->refPath($name);
        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, "ref: {$target}\n");
    }

    /**
     * Delete a ref.
     */
    public function delete(string $name): void
    {
        $path = $this->refPath($name);

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function readRefFile(string $name): ?string
    {
        $path = $this->refPath($name);

        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        return $content !== false ? $content : null;
    }

    private function refPath(string $name): string
    {
        // HEAD and per-worktree refs live in gitDir
        // Shared refs (refs/heads, refs/tags, etc.) live in commonDir
        if (
            $name === 'HEAD' || $name === 'MERGE_HEAD' || $name === 'CHERRY_PICK_HEAD'
            || $name === 'REVERT_HEAD' || $name === 'BISECT_HEAD'
        ) {
            return $this->gitDir . '/' . $name;
        }

        // Try per-worktree first, then common
        $perWorktree = $this->gitDir . '/' . $name;

        if (is_file($perWorktree)) {
            return $perWorktree;
        }

        return $this->commonDir . '/' . $name;
    }

    /**
     * @param array<string, ObjectId> $refs
     */
    private function scanRefsDir(string $dir, string $prefix, array &$refs): void
    {
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            $refName = $prefix . '/' . $entry;

            if (is_dir($path)) {
                $this->scanRefsDir($path, $refName, $refs);
                continue;
            }

            if (!is_file($path)) {
                continue;
            }

            $content = file_get_contents($path);

            if ($content === false) {
                continue;
            }

            $trimmed = trim($content);

            if (strlen($trimmed) >= 40 && ctype_xdigit(substr($trimmed, 0, 40))) {
                $refs[$refName] = ObjectId::fromHex(substr($trimmed, 0, 40));
            } elseif (str_starts_with($trimmed, 'ref: ')) {
                // Symbolic ref: resolve it
                $target = substr($trimmed, 5);
                $resolved = $this->resolve($target);

                if ($resolved !== null) {
                    $refs[$refName] = $resolved;
                }
            }
        }
    }
}
