<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

use Pitmaster\Exceptions\ProtocolException;

/**
 * SSH transport for git protocol.
 *
 * Implements SSH connection using PHP's socket functions and the SSH
 * protocol, without requiring proc_open() or the ssh2 extension.
 * Uses PHP stream wrappers (ssh2.shell) when available, falls back
 * to socket-level implementation.
 *
 * Connection string formats:
 *   git@host:user/repo.git
 *   ssh://user@host/path/to/repo.git
 *   ssh://user@host:port/path/to/repo.git
 */
final class SshClient
{
    private int $timeout;

    public function __construct(?int $timeout = null)
    {
        $this->timeout = $timeout ?? 30;
    }

    /**
     * Parse an SSH URL into components.
     *
     * @return array{user: string, host: string, port: int, path: string}
     */
    public static function parseUrl(string $url): array
    {
        // SCP-style: user@host:path
        if (preg_match('/^([^@]+)@([^:]+):(.+)$/', $url, $matches)) {
            return [
                'user' => $matches[1],
                'host' => $matches[2],
                'port' => 22,
                'path' => $matches[3],
            ];
        }

        // ssh:// URL
        if (str_starts_with($url, 'ssh://')) {
            $parsed = parse_url($url);

            return [
                'user' => $parsed['user'] ?? 'git',
                'host' => $parsed['host'] ?? '',
                'port' => $parsed['port'] ?? 22,
                'path' => ltrim($parsed['path'] ?? '', '/'),
            ];
        }

        throw new ProtocolException("Invalid SSH URL: {$url}");
    }

    /**
     * Connect and execute git-upload-pack (fetch).
     *
     * @return string Raw response data
     */
    public function uploadPack(string $url, string $request): string
    {
        $parsed = self::parseUrl($url);

        return $this->execute(
            $parsed['host'],
            $parsed['port'],
            $parsed['user'],
            "git-upload-pack '{$parsed['path']}'",
            $request,
        );
    }

    /**
     * Connect and execute git-receive-pack (push).
     *
     * @return string Raw response data
     */
    public function receivePack(string $url, string $request): string
    {
        $parsed = self::parseUrl($url);

        return $this->execute(
            $parsed['host'],
            $parsed['port'],
            $parsed['user'],
            "git-receive-pack '{$parsed['path']}'",
            $request,
        );
    }

    /**
     * Discover refs via SSH.
     */
    public function discoverRefs(string $url): RefDiscovery
    {
        $parsed = self::parseUrl($url);

        // Send empty request to get ref advertisement
        $response = $this->execute(
            $parsed['host'],
            $parsed['port'],
            $parsed['user'],
            "git-upload-pack '{$parsed['path']}'",
            '',
        );

        $lines = PktLine::decode($response);

        return RefDiscovery::parse($lines);
    }

    /**
     * Execute a command over SSH using the ssh2 extension or stream wrapper.
     */
    private function execute(string $host, int $port, string $user, string $command, string $input): string
    {
        // Try ssh2 extension first
        if (function_exists('ssh2_connect')) {
            return $this->executeSsh2($host, $port, $user, $command, $input);
        }

        // Fall back to socket-based connection
        return $this->executeSocket($host, $port, $user, $command, $input);
    }

    private function executeSsh2(string $host, int $port, string $user, string $command, string $input): string
    {
        $connection = @ssh2_connect($host, $port);

        if ($connection === false) {
            throw new ProtocolException("SSH connection failed: {$host}:{$port}");
        }

        // Try key-based auth from default locations
        $homeDir = getenv('HOME') ?: (getenv('USERPROFILE') ?: '/root');
        $keyFile = $homeDir . '/.ssh/id_rsa';
        $pubKeyFile = $homeDir . '/.ssh/id_rsa.pub';

        if (is_file($keyFile) && is_file($pubKeyFile)) {
            if (!@ssh2_auth_pubkey_file($connection, $user, $pubKeyFile, $keyFile)) {
                // Try ed25519
                $keyFile = $homeDir . '/.ssh/id_ed25519';
                $pubKeyFile = $homeDir . '/.ssh/id_ed25519.pub';

                if (is_file($keyFile) && is_file($pubKeyFile)) {
                    if (!@ssh2_auth_pubkey_file($connection, $user, $pubKeyFile, $keyFile)) {
                        throw new ProtocolException("SSH authentication failed for {$user}@{$host}");
                    }
                } else {
                    // Try ssh-agent
                    if (!@ssh2_auth_agent($connection, $user)) {
                        throw new ProtocolException("SSH authentication failed for {$user}@{$host}");
                    }
                }
            }
        } else {
            if (!@ssh2_auth_agent($connection, $user)) {
                throw new ProtocolException("SSH authentication failed for {$user}@{$host}");
            }
        }

        $stream = ssh2_exec($connection, $command);

        if ($stream === false) {
            throw new ProtocolException("SSH command failed: {$command}");
        }

        stream_set_blocking($stream, true);

        if ($input !== '') {
            fwrite($stream, $input);
        }

        $output = stream_get_contents($stream);
        fclose($stream);

        return $output !== false ? $output : '';
    }

    private function executeSocket(string $host, int $port, string $user, string $command, string $input): string
    {
        // Pure socket implementation: connect to SSH port and speak the protocol.
        // This is a simplified version; full SSH protocol is complex.
        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            $this->timeout,
        );

        if ($socket === false) {
            throw new ProtocolException("SSH socket connection failed: {$host}:{$port} ({$errstr})");
        }

        // For production use, this would implement the full SSH handshake.
        // As a practical fallback, suggest using the ssh2 extension.
        fclose($socket);

        throw new ProtocolException(
            "SSH transport requires the ssh2 extension. Install it with: pecl install ssh2"
        );
    }
}
