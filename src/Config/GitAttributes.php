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
    /** @var array<int, array{pattern: string, attrs: array<string, string|true|false>}> */
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

    public function addFile(string $path): void
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
                    // Unspecified: don't set anything
                    continue;
                } elseif (str_contains($attr, '=')) {
                    [$key, $value] = explode('=', $attr, 2);
                    $attrs[$key] = $value;
                } else {
                    $attrs[$attr] = true;
                }
            }

            $this->rules[] = ['pattern' => $pattern, 'attrs' => $attrs];
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
            if (fnmatch($rule['pattern'], $path) || fnmatch($rule['pattern'], basename($path))) {
                $result = array_merge($result, $rule['attrs']);
            }
        }

        return $result;
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
