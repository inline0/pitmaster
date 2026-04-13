<?php

declare(strict_types=1);

namespace Pitmaster\Config;

/**
 * Reads .git/config files.
 *
 * Git config format:
 *   [section]
 *       key = value
 *   [section "subsection"]
 *       key = value
 */
final class GitConfig
{
    /** @var array<string, string> Flattened key => value pairs */
    private array $values = [];

    /** @var array<string, list<string>> Full key => all values */
    private array $multiValues = [];

    private function __construct()
    {
    }

    public static function parse(string $content): self
    {
        $config = new self();
        $currentSection = '';

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }

            // Section header: [section] or [section "subsection"]
            if (preg_match('/^\[([A-Za-z0-9][A-Za-z0-9.-]*)(?:\s+"((?:[^"\\\\]|\\\\.)*)")?\]$/', $line, $matches)) {
                $section = strtolower($matches[1]);

                if (isset($matches[2]) && $matches[2] !== '') {
                    $currentSection = $section . '.' . stripcslashes($matches[2]);
                } else {
                    $currentSection = $section;
                }

                continue;
            }

            // Key = value
            if (preg_match('/^([A-Za-z][A-Za-z0-9-]*)\s*(?:=\s*(.*))?$/', $line, $matches)) {
                $key = $currentSection . '.' . strtolower($matches[1]);
                $value = isset($matches[2]) ? trim($matches[2]) : 'true';

                // Strip inline comments
                if (preg_match('/^"([^"]*)"/', $value, $quoted)) {
                    $value = stripcslashes($quoted[1]);
                } elseif (($commentPos = strpos($value, ' #')) !== false) {
                    $value = trim(substr($value, 0, $commentPos));
                }

                $config->multiValues[$key] ??= [];
                $config->multiValues[$key][] = $value;
                $config->values[$key] = $value;
            }
        }

        return $config;
    }

    public static function fromFile(string $path): self
    {
        $config = new self();
        self::loadFile($config, $path, [], 0);

        return $config;
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->values[$key] ?? $default;
    }

    /**
     * @return list<string>
     */
    public function getAll(string $key): array
    {
        return $this->multiValues[$key] ?? [];
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower($value), ['true', 'yes', 'on', '1'], true);
    }

    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return (int) $value;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->values;
    }

    /**
     * Get all values under a section prefix.
     *
     * @return array<string, string>
     */
    public function section(string $prefix): array
    {
        $result = [];
        $prefix .= '.';
        $prefixLen = strlen($prefix);

        foreach ($this->values as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $result[substr($key, $prefixLen)] = $value;
            }
        }

        return $result;
    }

    /**
     * Set a config value.
     */
    public function set(string $key, string $value): void
    {
        $this->multiValues[$key] = [$value];
        $this->values[$key] = $value;
    }

    /**
     * Append a multi-valued config entry.
     */
    public function append(string $key, string $value): void
    {
        $this->multiValues[$key] ??= [];
        $this->multiValues[$key][] = $value;
        $this->values[$key] = $value;
    }

    /**
     * Remove a config value.
     */
    public function unset(string $key): void
    {
        unset($this->multiValues[$key]);
        unset($this->values[$key]);
    }

    /**
     * Write config to a file.
     */
    public function writeToFile(string $path): void
    {
        $sections = [];

        foreach ($this->multiValues as $key => $values) {
            $parts = explode('.', $key);

            if (count($parts) === 3) {
                $sectionKey = $parts[0] . '.' . $parts[1];
                $name = $parts[2];
            } elseif (count($parts) === 2) {
                $sectionKey = $parts[0];
                $name = $parts[1];
            } else {
                continue;
            }

            foreach ($values as $value) {
                $sections[$sectionKey][] = [
                    'name' => $name,
                    'value' => $value,
                ];
            }
        }

        $lines = [];

        foreach ($sections as $section => $entries) {
            $dotPos = strpos($section, '.');

            if ($dotPos !== false) {
                $main = substr($section, 0, $dotPos);
                $sub = substr($section, $dotPos + 1);
                $lines[] = "[{$main} \"" . addcslashes($sub, "\\\"") . "\"]";
            } else {
                $lines[] = "[{$section}]";
            }

            foreach ($entries as $entry) {
                $lines[] = "\t{$entry['name']} = {$entry['value']}";
            }
        }

        file_put_contents($path, implode("\n", $lines) . "\n");
    }

    /**
     * @param list<string> $stack
     */
    private static function loadFile(self $config, string $path, array $stack, int $depth): void
    {
        if (!is_file($path)) {
            return;
        }

        $realPath = realpath($path) ?: $path;

        if ($depth >= 10 || in_array($realPath, $stack, true)) {
            throw new \RuntimeException(
                "exceeded maximum include depth (10) while including {$realPath}"
            );
        }

        $stack[] = $realPath;
        $content = file_get_contents($realPath);

        if ($content === false) {
            return;
        }

        $currentSection = '';

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }

            if (preg_match('/^\[([A-Za-z0-9][A-Za-z0-9.-]*)(?:\s+"((?:[^"\\\\]|\\\\.)*)")?\]$/', $line, $matches)) {
                $section = strtolower($matches[1]);

                if (isset($matches[2]) && $matches[2] !== '') {
                    $currentSection = $section . '.' . stripcslashes($matches[2]);
                } else {
                    $currentSection = $section;
                }

                continue;
            }

            if (!preg_match('/^([A-Za-z][A-Za-z0-9-]*)\s*(?:=\s*(.*))?$/', $line, $matches)) {
                continue;
            }

            $key = $currentSection . '.' . strtolower($matches[1]);
            $value = isset($matches[2]) ? trim($matches[2]) : 'true';

            if (preg_match('/^"([^"]*)"/', $value, $quoted)) {
                $value = stripcslashes($quoted[1]);
            } elseif (($commentPos = strpos($value, ' #')) !== false) {
                $value = trim(substr($value, 0, $commentPos));
            }

            if ($key === 'include.path') {
                self::loadFile($config, self::resolveIncludePath($realPath, $value), $stack, $depth + 1);
                continue;
            }

            $config->multiValues[$key] ??= [];
            $config->multiValues[$key][] = $value;
            $config->values[$key] = $value;
        }
    }

    private static function resolveIncludePath(string $configPath, string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if (str_starts_with($value, '~/')) {
            $home = getenv('HOME');

            if (is_string($home) && $home !== '') {
                return $home . substr($value, 1);
            }
        }

        if (str_starts_with($value, '/')) {
            return $value;
        }

        return dirname($configPath) . '/' . $value;
    }
}
