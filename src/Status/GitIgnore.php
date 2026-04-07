<?php

declare(strict_types=1);

namespace Pitmaster\Status;

/**
 * Parses .gitignore files and matches paths against ignore patterns.
 */
final class GitIgnore
{
    /** @var array<int, array{pattern: string, negated: bool, dirOnly: bool}> */
    private array $rules = [];

    /**
     * Load ignore rules from a repository root.
     * Reads .gitignore from the repo root (and could be extended for nested ones).
     */
    public static function forRepo(string $workDir): self
    {
        $ignore = new self();

        $path = $workDir . '/.gitignore';

        if (is_file($path)) {
            $ignore->addFile($path);
        }

        return $ignore;
    }

    public function addFile(string $path): void
    {
        $content = file_get_contents($path);

        if ($content === false) {
            return;
        }

        foreach (explode("\n", $content) as $line) {
            $this->addPattern($line);
        }
    }

    public function addPattern(string $line): void
    {
        $line = rtrim($line);

        if ($line === '' || $line[0] === '#') {
            return;
        }

        $negated = false;
        $dirOnly = false;

        if ($line[0] === '!') {
            $negated = true;
            $line = substr($line, 1);
        }

        if (str_ends_with($line, '/')) {
            $dirOnly = true;
            $line = rtrim($line, '/');
        }

        $this->rules[] = ['pattern' => $line, 'negated' => $negated, 'dirOnly' => $dirOnly];
    }

    /**
     * Check if a path is ignored.
     */
    public function isIgnored(string $path, bool $isDir = false): bool
    {
        $ignored = false;

        foreach ($this->rules as $rule) {
            if ($rule['dirOnly'] && !$isDir) {
                continue;
            }

            if ($this->matchPattern($rule['pattern'], $path)) {
                $ignored = !$rule['negated'];
            }
        }

        return $ignored;
    }

    private function matchPattern(string $pattern, string $path): bool
    {
        // If pattern contains a slash (not trailing), it's anchored to the root
        $anchored = str_contains($pattern, '/');

        if ($anchored) {
            $pattern = ltrim($pattern, '/');

            return $this->fnmatchRecursive($pattern, $path);
        }

        // Unanchored: match against any path component or the full path
        if ($this->fnmatchRecursive($pattern, $path)) {
            return true;
        }

        // Also match against each path component
        $parts = explode('/', $path);

        foreach ($parts as $part) {
            if ($this->fnmatchRecursive($pattern, $part)) {
                return true;
            }
        }

        return false;
    }

    private function fnmatchRecursive(string $pattern, string $path): bool
    {
        // Handle ** (match any number of directories)
        if (str_contains($pattern, '**')) {
            $parts = explode('**', $pattern, 2);
            $before = rtrim($parts[0], '/');
            $after = ltrim($parts[1], '/');

            if ($before === '') {
                // **/ at start: match any prefix
                if ($after === '') {
                    return true;
                }

                // Try matching $after against $path and all suffixes
                $pathParts = explode('/', $path);

                for ($i = 0; $i < count($pathParts); $i++) {
                    $subPath = implode('/', array_slice($pathParts, $i));

                    if (fnmatch($after, $subPath, FNM_PATHNAME)) {
                        return true;
                    }
                }

                return false;
            }

            return fnmatch($pattern, $path, FNM_PATHNAME);
        }

        return fnmatch($pattern, $path, FNM_PATHNAME);
    }
}
