<?php

declare(strict_types=1);

namespace Pitmaster\Config;

/**
 * .gitattributes parser.
 *
 * Format: <pattern> <attr1> <attr2> ...
 * Attributes can be: set (attr), unset (-attr), value (attr=value), unspecified (!attr).
 */
final class GitAttributes
{
    /** @var array<int, array{pattern: string, base: string, attrs: array<string, string|true|false|null>}> */
    private array $rules = [];

    public static function forRepo(string $workDir): self
    {
        $attrs = new self();

        $path = $workDir . '/.gitattributes';

        if (is_file($path)) {
            $attrs->addFile($path);
        }

        return $attrs;
    }

    /** Resolve attributes for one path, including directory and repository overrides. */
    public static function forPath(string $workDir, string $path, string $gitDir): self
    {
        $attrs = self::forRepo($workDir);
        $directory = dirname($path);
        $prefix = '';
        if ($directory !== '.') {
            foreach (explode('/', $directory) as $part) {
                $prefix .= $part . '/';
                $file = $workDir . '/' . $prefix . '.gitattributes';
                if (is_file($file)) {
                    $attrs->addFile($file, $prefix);
                }
            }
        }
        if (is_file($gitDir . '/info/attributes')) {
            $attrs->addFile($gitDir . '/info/attributes');
        }
        return $attrs;
    }

    public function addFile(string $path, string $base = ''): void
    {
        $content = file_get_contents($path);

        if ($content === false) {
            return;
        }

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $parts = preg_split('/\s+/', $line);

            if ($parts === false || count($parts) < 2) {
                continue;
            }

            $pattern = array_shift($parts);
            $attrs = [];

            foreach ($parts as $attr) {
                if (str_starts_with($attr, '-')) {
                    $attrs[substr($attr, 1)] = false;
                } elseif (str_starts_with($attr, '!')) {
                    $attrs[substr($attr, 1)] = null;
                } elseif (str_contains($attr, '=')) {
                    [$key, $value] = explode('=', $attr, 2);
                    $attrs[$key] = $value;
                } else {
                    $attrs[$attr] = true;
                }
            }

            $this->rules[] = ['pattern' => $pattern, 'base' => $base, 'attrs' => $attrs];
        }
    }

    /**
     * Get attributes for a file path.
     *
     * @return array<string, string|true|false>
     */
    public function getAttributes(string $path): array
    {
        $result = [];

        foreach ($this->rules as $rule) {
            if (!str_starts_with($path, $rule['base'])) {
                continue;
            }
            $relative = substr($path, strlen($rule['base']));
            if (fnmatch($rule['pattern'], $relative) || fnmatch($rule['pattern'], basename($relative))) {
                $result = array_merge($result, $rule['attrs']);
            }
        }

        return array_filter($result, static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Check if a file is marked as binary.
     */
    public function isBinary(string $path): bool
    {
        $attrs = $this->getAttributes($path);

        if (isset($attrs['binary']) && $attrs['binary'] === true) {
            return true;
        }

        if (isset($attrs['diff']) && $attrs['diff'] === false) {
            return true;
        }

        return false;
    }

    /**
     * Check if a file has a specific attribute.
     */
    public function hasAttribute(string $path, string $attribute): bool
    {
        $attrs = $this->getAttributes($path);

        return isset($attrs[$attribute]);
    }
}
