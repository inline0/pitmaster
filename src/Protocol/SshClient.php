<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

use Pitmaster\Exceptions\ProtocolException;

/**
 * SSH transport for git protocol.
 *
 * Uses the ssh2 extension when available and falls back to the system
 * `ssh` binary otherwise.
 *
 * Connection string formats:
 *   git@host:user/repo.git
 *   ssh://user@host/path/to/repo.git
 *   ssh://user@host:port/path/to/repo.git
 */
final class SshClient implements UploadPackTransport, ReceivePackTransport
{
    private int $timeout;

    public function __construct(?int $timeout = null)
    {
        $this->timeout = $timeout ?? 30;
    }

    public static function isSshUrl(string $url): bool
    {
        return str_starts_with($url, 'ssh://')
            || preg_match('/^[^@]+@[^:]+:.+$/', $url) === 1;
    }

    /**
     * Parse an SSH URL into components.
     *
     * @return array{user: string, host: string, port: int, path: string}
     */
    public static function parseUrl(string $url): array
    {
        // ssh:// URL
        if (str_starts_with($url, 'ssh://')) {
            $parsed = parse_url($url);

            return [
                'user' => $parsed['user'] ?? 'git',
                'host' => $parsed['host'] ?? '',
                'port' => $parsed['port'] ?? 22,
                'path' => $parsed['path'] ?? '',
            ];
        }

        // SCP-style: user@host:path
        if (preg_match('/^([^@]+)@([^:]+):(.+)$/', $url, $matches)) {
            return [
                'user' => $matches[1],
                'host' => $matches[2],
                'port' => 22,
                'path' => $matches[3],
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
            true,
        );

        return RefDiscovery::parse(PktLine::decode($response));
    }

    public function discoverReceivePackRefs(string $url): RefDiscovery
    {
        $parsed = self::parseUrl($url);
        $response = $this->execute(
            $parsed['host'],
            $parsed['port'],
            $parsed['user'],
            "git-receive-pack '{$parsed['path']}'",
            '',
            true,
        );

        return RefDiscovery::parse(PktLine::decode($response));
    }

    /**
     * Execute a command over SSH using the ssh2 extension or stream wrapper.
     */
    private function execute(string $host, int $port, string $user, string $command, string $input, bool $allowExitWithOutput = false): string
    {
        // Try ssh2 extension first
        if (function_exists('ssh2_connect')) {
            return $this->executeSsh2($host, $port, $user, $command, $input, $allowExitWithOutput);
        }

        return $this->executeProcess($host, $port, $user, $command, $input, $allowExitWithOutput);
    }

    private function executeSsh2(string $host, int $port, string $user, string $command, string $input, bool $allowExitWithOutput): string
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

        if ($output !== false && ($output !== '' || $allowExitWithOutput)) {
            return $output;
        }

        return '';
    }

    private function executeProcess(string $host, int $port, string $user, string $command, string $input, bool $allowExitWithOutput): string
    {
        $ssh = getenv('PITMASTER_SSH_COMMAND');
        $ssh = is_string($ssh) && $ssh !== '' ? $ssh : 'ssh';
        $identityFile = getenv('PITMASTER_SSH_IDENTITY_FILE');
        $knownHosts = getenv('PITMASTER_SSH_KNOWN_HOSTS');
        $strict = getenv('PITMASTER_SSH_STRICT_HOST_KEY_CHECKING');
        $processCommand = $this->canUseDirectProcessCommand($ssh)
            ? $this->buildDirectProcessCommand($ssh, $host, $port, $user, $command, $identityFile, $knownHosts, $strict)
            : $this->buildShellProcessCommand($ssh, $host, $port, $user, $command, $identityFile, $knownHosts, $strict);

        $process = proc_open(
            $processCommand,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (!is_resource($process)) {
            throw new ProtocolException('Failed to start ssh process');
        }

        if ($input !== '' && str_starts_with($command, 'git-receive-pack ')) {
            $this->readAdvertisement($pipes[1]);
        }

        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            if ($allowExitWithOutput && $stdout !== false && $stdout !== '') {
                return $stdout;
            }

            $message = trim((string) $stderr);
            throw new ProtocolException($message !== '' ? "SSH command failed: {$message}" : 'SSH command failed');
        }

        return $stdout !== false ? $stdout : '';
    }

    /**
     * @return list<string>
     */
    private function buildDirectProcessCommand(
        string $ssh,
        string $host,
        int $port,
        string $user,
        string $command,
        string|false $identityFile,
        string|false $knownHosts,
        string|false $strict,
    ): array {
        $parts = [
            $ssh,
            '-o',
            'BatchMode=yes',
            '-o',
            'ConnectTimeout=' . $this->timeout,
            '-p',
            (string) $port,
        ];

        if (is_string($identityFile) && $identityFile !== '') {
            $parts[] = '-i';
            $parts[] = $identityFile;
        }

        if (is_string($knownHosts) && $knownHosts !== '') {
            $parts[] = '-o';
            $parts[] = 'UserKnownHostsFile=' . $knownHosts;
        }

        if (is_string($strict) && $strict !== '') {
            $parts[] = '-o';
            $parts[] = 'StrictHostKeyChecking=' . $strict;
        }

        $parts[] = $user . '@' . $host;
        $parts[] = $command;

        return $parts;
    }

    private function buildShellProcessCommand(
        string $ssh,
        string $host,
        int $port,
        string $user,
        string $command,
        string|false $identityFile,
        string|false $knownHosts,
        string|false $strict,
    ): string {
        $parts = [
            escapeshellarg($ssh),
            '-o BatchMode=yes',
            '-o ' . escapeshellarg('ConnectTimeout=' . $this->timeout),
            '-p ' . (int) $port,
        ];

        if (is_string($identityFile) && $identityFile !== '') {
            $parts[] = '-i ' . escapeshellarg($identityFile);
        }

        if (is_string($knownHosts) && $knownHosts !== '') {
            $parts[] = '-o ' . escapeshellarg('UserKnownHostsFile=' . $knownHosts);
        }

        if (is_string($strict) && $strict !== '') {
            $parts[] = '-o ' . escapeshellarg('StrictHostKeyChecking=' . $strict);
        }

        $parts[] = escapeshellarg($user . '@' . $host);
        $parts[] = escapeshellarg($command);

        return implode(' ', $parts);
    }

    private function canUseDirectProcessCommand(string $ssh): bool
    {
        return preg_match('/^[A-Za-z0-9._\\/-]+$/', $ssh) === 1;
    }

    /**
     * Drain the initial receive-pack ref advertisement from a stateful SSH session.
     *
     * @param resource $stream
     */
    private function readAdvertisement($stream): void
    {
        while (true) {
            $hexLen = $this->readExact($stream, 4);

            if ($hexLen === null) {
                throw new ProtocolException('SSH receive-pack advertisement ended unexpectedly');
            }

            if ($hexLen === PktLine::FLUSH) {
                return;
            }

            if (!ctype_xdigit($hexLen)) {
                throw new ProtocolException("Invalid pkt-line length in SSH receive-pack advertisement: {$hexLen}");
            }

            $lineLen = (int) hexdec($hexLen);

            if ($lineLen < 4) {
                throw new ProtocolException("Invalid pkt-line length in SSH receive-pack advertisement: {$hexLen}");
            }

            $payloadLen = $lineLen - 4;

            if ($payloadLen === 0) {
                continue;
            }

            if ($this->readExact($stream, $payloadLen) === null) {
                throw new ProtocolException('Truncated SSH receive-pack advertisement');
            }
        }
    }

    /**
     * @param resource $stream
     */
    private function readExact($stream, int $bytes): ?string
    {
        $buffer = '';

        while (strlen($buffer) < $bytes) {
            $toRead = $bytes - strlen($buffer);

            if ($toRead < 1) {
                break;
            }

            $chunk = fread($stream, $toRead);

            if ($chunk === false || $chunk === '') {
                return null;
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }
}
