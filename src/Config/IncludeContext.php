<?php

declare(strict_types=1);

namespace Pitmaster\Config;

/** Repository state used when reading conditional configuration includes. */
final readonly class IncludeContext
{
    public function __construct(
        public string $gitDir,
        public ?string $workTree = null,
        public ?string $branch = null,
    ) {
    }

    public function matches(string $condition, string $configPath): bool
    {
        $parts = explode(':', $condition, 2);
        if (count($parts) !== 2) {
            return false;
        }
        [$kind, $pattern] = $parts;
        if ($kind === 'onbranch') {
            if ($this->branch === null) {
                return false;
            }
            if (str_ends_with($pattern, '/')) {
                $pattern .= '**';
            }
            return self::wildmatch($pattern, $this->branch);
        }
        if (!in_array($kind, ['gitdir', 'gitdir/i', 'worktree', 'worktree/i'], true)) {
            return false;
        }
        $path = str_starts_with($kind, 'worktree') ? $this->workTree : $this->gitDir;
        if ($path === null) {
            return false;
        }
        $ignoreCase = str_ends_with($kind, '/i');
        $prefix = '';
        if (str_starts_with($pattern, './')) {
            // Git treats the including file's directory literally, even if
            // that directory's name contains glob metacharacters.
            $prefix = dirname(realpath($configPath) ?: $configPath) . '/';
            $pattern = substr($pattern, 2);
        } elseif (str_starts_with($pattern, '~/')) {
            $home = getenv('HOME');
            if (!is_string($home) || $home === '') {
                return false;
            }
            $pattern = $home . substr($pattern, 1);
        } elseif (!str_starts_with($pattern, '/')) {
            $pattern = '**/' . $pattern;
        }
        if (str_ends_with($pattern, '/')) {
            $pattern .= '**';
        }
        $real = realpath($path) ?: $path;
        // Gitdir also accepts the absolute spelling; worktree deliberately
        // uses only the canonical location supplied by Git's repository setup.
        $candidates = str_starts_with($kind, 'gitdir') ? array_unique([$real, $path]) : [$real];
        foreach ($candidates as $candidate) {
            if ($prefix !== '') {
                $equal = $ignoreCase
                    ? strncasecmp($candidate, $prefix, strlen($prefix)) === 0
                    : str_starts_with($candidate, $prefix);
                if (!$equal) {
                    continue;
                }
                $candidate = substr($candidate, strlen($prefix));
            }
            if (self::wildmatch($pattern, $candidate, $ignoreCase)) {
                return true;
            }
        }
        return false;
    }

    /** Git's component-aware globstar rules, with native fnmatch for each component. */
    private static function wildmatch(string $pattern, string $path, bool $ignoreCase = false): bool
    {
        $patterns = explode('/', $pattern);
        $components = explode('/', $path);
        $memo = [];
        $flags = FNM_PATHNAME | ($ignoreCase ? FNM_CASEFOLD : 0);
        $match = static function (int $p, int $c) use (&$match, &$memo, $patterns, $components, $flags): bool {
            $key = $p . ':' . $c;
            if (isset($memo[$key])) {
                return $memo[$key];
            }
            if ($p === count($patterns)) {
                return $memo[$key] = $c === count($components);
            }
            if ($patterns[$p] === '**') {
                if ($p === count($patterns) - 1) {
                    return $memo[$key] = $p === 0 || $c < count($components);
                }
                return $memo[$key] = $match($p + 1, $c)
                    || ($c < count($components) && $match($p, $c + 1));
            }
            return $memo[$key] = $c < count($components)
                && fnmatch($patterns[$p], $components[$c], $flags)
                && $match($p + 1, $c + 1);
        };
        return $match(0, 0);
    }
}
