<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

use Pitmaster\Exceptions\ProtocolException;

/** Verifies the negotiated ssh2 host key against OpenSSH known_hosts records. */
final class KnownHosts
{
    /** @param list<string> $files */
    public static function verify(string $host, int $port, string $fingerprint, array $files): void
    {
        $host = trim($host, '[]');
        $token = $port === 22 ? $host : '[' . $host . ']:' . $port;
        $fingerprint = strtolower($fingerprint);
        $trusted = false;

        foreach ($files as $file) {
            $lines = is_readable($file) ? file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : false;

            foreach ($lines === false ? [] : $lines as $line) {
                $parts = preg_split('/\s+/', trim($line));

                if ($parts === false || count($parts) < 3 || str_starts_with($parts[0], '#')) {
                    continue;
                }

                $marker = str_starts_with($parts[0], '@') ? array_shift($parts) : '';

                if (count($parts) < 3 || !self::matches($parts[0], $token)) {
                    continue;
                }

                $key = base64_decode($parts[2], true);

                if ($key === false || !hash_equals(sha1($key), $fingerprint)) {
                    continue;
                }

                if ($marker === '@revoked') {
                    throw new ProtocolException('SSH host key is revoked: ' . $token);
                }

                if ($marker === '') {
                    $trusted = true;
                }
            }
        }

        if (!$trusted) {
            throw new ProtocolException('SSH host key verification failed: ' . $token);
        }
    }

    private static function matches(string $patterns, string $host): bool
    {
        if (str_starts_with($patterns, '|1|')) {
            $parts = explode('|', $patterns);
            $salt = isset($parts[2]) ? base64_decode($parts[2], true) : false;
            $hash = isset($parts[3]) ? base64_decode($parts[3], true) : false;

            return $salt !== false && $hash !== false && hash_equals($hash, hash_hmac('sha1', $host, $salt, true));
        }

        $matched = false;

        foreach (explode(',', $patterns) as $pattern) {
            $negative = str_starts_with($pattern, '!');
            $pattern = $negative ? substr($pattern, 1) : $pattern;
            $regex = '/^' . str_replace(['\*', '\?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/iD';

            if (preg_match($regex, $host) === 1) {
                if ($negative) {
                    return false;
                }

                $matched = true;
            }
        }

        return $matched;
    }
}
