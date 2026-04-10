<?php

declare(strict_types=1);

namespace Pitmaster\Bench;

final class BenchmarkEnvironment
{
    /**
     * @return array<string, scalar|null>
     */
    public static function capture(): array
    {
        return [
            'php_binary' => PHP_BINARY,
            'php_version' => PHP_VERSION,
            'git_version' => self::safeCommand(escapeshellarg(BenchmarkShell::gitBinary()) . ' --version'),
            'os' => php_uname('s') . ' ' . php_uname('r'),
            'machine' => php_uname('m'),
            'hostname' => gethostname() ?: null,
            'cpu' => self::cpuSummary(),
            'timestamp_utc' => gmdate('c'),
            'xdebug' => extension_loaded('xdebug') ? 'enabled' : 'disabled',
        ];
    }

    private static function cpuSummary(): ?string
    {
        if (is_file('/proc/cpuinfo')) {
            $cpuinfo = file_get_contents('/proc/cpuinfo');

            if ($cpuinfo !== false && preg_match('/^model name\s*:\s*(.+)$/m', $cpuinfo, $matches) === 1) {
                return trim($matches[1]);
            }
        }

        try {
            $summary = self::safeCommand('sysctl -n machdep.cpu.brand_string');

            return $summary !== '' ? $summary : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function safeCommand(string $command): string
    {
        try {
            return trim(BenchmarkShell::run($command));
        } catch (\Throwable) {
            return '';
        }
    }
}
