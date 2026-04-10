<?php

declare(strict_types=1);

namespace Pitmaster\Checkout;

use Pitmaster\Config\GitConfig;

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

    /** @var array<int, string> */
    private array $excludes = [];

    private bool $enabled = false;

    /** @var array<int, string> */
    private array $includedDirectories = [];

    /** @var array<string, true> */
    private array $includedDirectorySet = [];

    /** @var array<string, bool> */
    private array $includeCache = [];

    public function __construct(private readonly string $gitDir)
    {
        $this->load();
    }

    /**
     * Check if sparse checkout is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Initialize sparse checkout with cone mode.
     */
    public function init(bool $coneMode = true): void
    {
        $infoDir = $this->gitDir . '/info';

        if (!is_dir($infoDir)) {
            mkdir($infoDir, 0777, true);
        }

        $config = GitConfig::fromFile($this->gitDir . '/config');
        $config->set('extensions.worktreeconfig', 'true');
        $config->writeToFile($this->gitDir . '/config');

        $worktreeConfig = $this->configWorktree();
        $worktreeConfig->set('core.sparsecheckout', 'true');
        $worktreeConfig->set('core.sparsecheckoutcone', $coneMode ? 'true' : 'false');
        $worktreeConfig->unset('index.sparse');
        $worktreeConfig->writeToFile($this->configWorktreePath());

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
        $directories = array_values(array_unique(array_map(
            static fn (string $directory): string => trim($directory, '/'),
            $directories,
        )));
        sort($directories);
        $lines = ["/*\n", "!/*/\n"];

        foreach ($directories as $dir) {
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
        if (!$this->enabled) {
            return true;
        }

        if (isset($this->includeCache[$path])) {
            return $this->includeCache[$path];
        }

        // Root files (no /) are included if /* is in the include list
        if (!str_contains($path, '/')) {
            return $this->includeCache[$path] = in_array('/*', $this->includes, true);
        }

        $candidate = $path;

        while (true) {
            if (isset($this->includedDirectorySet[$candidate])) {
                return $this->includeCache[$path] = true;
            }

            $slash = strrpos($candidate, '/');

            if ($slash === false) {
                break;
            }

            $candidate = substr($candidate, 0, $slash);
        }

        return $this->includeCache[$path] = false;
    }

    /**
     * Get the list of included directories.
     *
     * @return array<int, string>
     */
    public function includedDirectories(): array
    {
        return $this->includedDirectories;
    }

    /**
     * Disable sparse checkout.
     */
    public function disable(): void
    {
        $worktreeConfig = $this->configWorktree();
        $worktreeConfig->set('core.sparsecheckout', 'false');
        $worktreeConfig->set('core.sparsecheckoutcone', 'false');
        $worktreeConfig->set('index.sparse', 'false');
        $worktreeConfig->writeToFile($this->configWorktreePath());

        $this->enabled = false;
        $this->includes = [];
        $this->excludes = [];
        $this->includedDirectories = [];
        $this->includedDirectorySet = [];
        $this->includeCache = [];
    }

    private function load(): void
    {
        $this->enabled = GitConfig::fromFile($this->configWorktreePath())->getBool('core.sparsecheckout', false);
        $this->includes = [];
        $this->excludes = [];
        $this->includedDirectories = [];
        $this->includedDirectorySet = [];
        $this->includeCache = [];

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

        foreach ($this->includes as $pattern) {
            if ($pattern !== '/*' && str_ends_with($pattern, '/')) {
                $directory = trim($pattern, '/');
                $this->includedDirectories[] = $directory;
                $this->includedDirectorySet[$directory] = true;
            }
        }
    }

    /**
     * Get excludes (parsed from file, used for non-cone mode).
     *
     * @return array<int, string>
     */
    public function excludes(): array
    {
        return $this->excludes;
    }

    private function configWorktreePath(): string
    {
        return $this->gitDir . '/config.worktree';
    }

    private function configWorktree(): GitConfig
    {
        return GitConfig::fromFile($this->configWorktreePath());
    }
}
