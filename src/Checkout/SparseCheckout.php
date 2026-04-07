<?php

declare(strict_types=1);

namespace Pitmaster\Checkout;

/**
 * Sparse checkout: materialize only a subset of files in the working tree.
 *
 * Cone mode patterns are stored in .git/info/sparse-checkout.
 * Include and exclude patterns control which directories are checked out.
 */
final class SparseCheckout
{
    /** @var array<int, string> Include patterns */
    private array $includes = [];

    /** @var array<int, string> Exclude patterns */
    private array $excludes = [];

    private bool $coneMode = true;

    public function __construct(private readonly string $gitDir)
    {
        $this->load();
    }

    /**
     * Check if sparse checkout is enabled.
     */
    public function isEnabled(): bool
    {
        return is_file($this->gitDir . '/info/sparse-checkout');
    }

    /**
     * Initialize sparse checkout with cone mode.
     */
    public function init(bool $coneMode = true): void
    {
        $this->coneMode = $coneMode;
        $infoDir = $this->gitDir . '/info';

        if (!is_dir($infoDir)) {
            mkdir($infoDir, 0777, true);
        }

        // Default: include everything
        file_put_contents($infoDir . '/sparse-checkout', "/*\n!/*/\n");
        $this->load();
    }

    /**
     * Set the sparse checkout patterns (cone mode: directory list).
     *
     * @param array<int, string> $directories
     */
    public function set(array $directories): void
    {
        $lines = ["/*\n", "!/*/\n"];

        foreach ($directories as $dir) {
            $dir = trim($dir, '/');
            $lines[] = "/{$dir}/\n";
        }

        file_put_contents($this->gitDir . '/info/sparse-checkout', implode('', $lines));
        $this->load();
    }

    /**
     * Add directories to sparse checkout.
     *
     * @param array<int, string> $directories
     */
    public function add(array $directories): void
    {
        $current = $this->includedDirectories();
        $merged = array_unique(array_merge($current, $directories));
        $this->set($merged);
    }

    /**
     * Check if a path is included in the sparse checkout.
     */
    public function includes(string $path): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }

        // Root files are always included if /* is present
        if (!str_contains($path, '/') && in_array('/*', $this->includes, true)) {
            return true;
        }

        foreach ($this->includes as $pattern) {
            if ($this->matchPattern($pattern, $path)) {
                // Check exclusions
                foreach ($this->excludes as $exclude) {
                    if ($this->matchPattern($exclude, $path)) {
                        return false;
                    }
                }

                return true;
            }
        }

        return false;
    }

    /**
     * Get the list of included directories.
     *
     * @return array<int, string>
     */
    public function includedDirectories(): array
    {
        $dirs = [];

        foreach ($this->includes as $pattern) {
            if ($pattern !== '/*' && str_ends_with($pattern, '/')) {
                $dirs[] = trim($pattern, '/');
            }
        }

        return $dirs;
    }

    /**
     * Disable sparse checkout.
     */
    public function disable(): void
    {
        $path = $this->gitDir . '/info/sparse-checkout';

        if (is_file($path)) {
            unlink($path);
        }

        $this->includes = [];
        $this->excludes = [];
    }

    private function load(): void
    {
        $this->includes = [];
        $this->excludes = [];

        $path = $this->gitDir . '/info/sparse-checkout';

        if (!is_file($path)) {
            return;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return;
        }

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if ($line[0] === '!') {
                $this->excludes[] = substr($line, 1);
            } else {
                $this->includes[] = $line;
            }
        }
    }

    private function matchPattern(string $pattern, string $path): bool
    {
        $pattern = ltrim($pattern, '/');

        // Directory pattern: /src/ matches src/ and src/anything
        if (str_ends_with($pattern, '/')) {
            $dir = rtrim($pattern, '/');

            return str_starts_with($path, $dir . '/') || $path === $dir;
        }

        return fnmatch($pattern, $path) || fnmatch($pattern, basename($path));
    }
}
