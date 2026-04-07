<?php

declare(strict_types=1);

namespace Pitmaster\Lfs;

/**
 * Git LFS pointer file parser.
 *
 * LFS pointer files replace large file content with a small text file:
 *   version https://git-lfs.github.com/spec/v1
 *   oid sha256:<hex>
 *   size <bytes>
 */
final readonly class LfsPointer
{
    public function __construct(
        public string $oid,
        public int $size,
        public string $version = 'https://git-lfs.github.com/spec/v1',
    ) {
    }

    /**
     * Parse a pointer file content.
     */
    public static function parse(string $content): ?self
    {
        if (!str_starts_with($content, 'version ')) {
            return null;
        }

        $version = null;
        $oid = null;
        $size = null;

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if (str_starts_with($line, 'version ')) {
                $version = substr($line, 8);
            } elseif (str_starts_with($line, 'oid sha256:')) {
                $oid = substr($line, 11);
            } elseif (str_starts_with($line, 'size ')) {
                $size = (int) substr($line, 5);
            }
        }

        if ($version === null || $oid === null || $size === null) {
            return null;
        }

        return new self($oid, $size, $version);
    }

    /**
     * Check if content looks like an LFS pointer.
     */
    public static function isPointer(string $content): bool
    {
        return str_starts_with($content, 'version https://git-lfs.github.com/spec/v1')
            && str_contains($content, 'oid sha256:')
            && str_contains($content, 'size ');
    }

    /**
     * Serialize to pointer file content.
     */
    public function serialize(): string
    {
        return "version {$this->version}\noid sha256:{$this->oid}\nsize {$this->size}\n";
    }

    /**
     * Create a pointer for a file by hashing its content.
     */
    public static function forContent(string $content): self
    {
        $oid = hash('sha256', $content);
        $size = strlen($content);

        return new self($oid, $size);
    }
}
