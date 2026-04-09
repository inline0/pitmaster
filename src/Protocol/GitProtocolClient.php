<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

use Pitmaster\Exceptions\ProtocolException;

/**
 * git:// protocol transport (native git daemon protocol).
 *
 * Uses raw TCP sockets to communicate with git-daemon on port 9418.
 * The protocol is identical to the smart HTTP protocol (pkt-line framing)
 * but over a direct TCP connection with a host header.
 *
 * Connection format: git://<host>[:<port>]/<path>
 * Initial request: "git-upload-pack /<path>\0host=<host>\0"
 */
final class GitProtocolClient
{
    private const DEFAULT_PORT = 9418;
    private const NEGOTIATION_RETRY_DELAY_US = 250000;
    private const NEGOTIATION_RETRY_LIMIT = 8;

    private int $timeout;

    public function __construct(?int $timeout = null)
    {
        $this->timeout = $timeout ?? 30;
    }

    /**
     * Parse a git:// URL.
     *
     * @return array{host: string, port: int, path: string}
     */
    public static function parseUrl(string $url): array
    {
        if (!str_starts_with($url, 'git://')) {
            throw new ProtocolException("Not a git:// URL: {$url}");
        }

        $parsed = parse_url($url);

        return [
            'host' => $parsed['host'] ?? '',
            'port' => $parsed['port'] ?? self::DEFAULT_PORT,
            'path' => $parsed['path'] ?? '/',
        ];
    }

    /**
     * Discover refs from a git:// remote.
     */
    public function discoverRefs(string $url): RefDiscovery
    {
        $parsed = self::parseUrl($url);
        $socket = $this->connect($parsed['host'], $parsed['port']);

        try {
            // Send initial request
            $request = "git-upload-pack {$parsed['path']}\0host={$parsed['host']}\0";
            fwrite($socket, PktLine::encode($request));

            // Read ref advertisement
            $response = $this->readAll($socket);
            $lines = PktLine::decode($response);

            return RefDiscovery::parse($lines);
        } finally {
            fclose($socket);
        }
    }

    /**
     * Fetch objects using git-upload-pack over git://.
     */
    public function uploadPack(string $url, string $request): string
    {
        $lastResponse = '';

        for ($attempt = 0; $attempt < self::NEGOTIATION_RETRY_LIMIT; $attempt++) {
            $response = $this->uploadPackOnce($url, $request);

            if (str_contains($response, 'PACK') || trim($response) === '') {
                return $response;
            }

            $lastResponse = $response;

            if (!$this->isNegotiationOnlyResponse($response)) {
                return $response;
            }

            usleep(self::NEGOTIATION_RETRY_DELAY_US);
        }

        return $lastResponse;
    }

    private function uploadPackOnce(string $url, string $request): string
    {
        $parsed = self::parseUrl($url);
        $socket = $this->connect($parsed['host'], $parsed['port']);

        try {
            // Send service request
            $init = "git-upload-pack {$parsed['path']}\0host={$parsed['host']}\0";
            fwrite($socket, PktLine::encode($init));

            // Read ref advertisement (consume but don't parse here)
            $this->readUntilFlush($socket);

            // Send want/have/done
            fwrite($socket, $request);
            stream_socket_shutdown($socket, STREAM_SHUT_WR);

            // Read pack response
            return $this->readAll($socket);
        } finally {
            fclose($socket);
        }
    }

    /**
     * @return resource
     */
    private function connect(string $host, int $port)
    {
        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            $this->timeout,
        );

        if ($socket === false) {
            throw new ProtocolException("git:// connection failed: {$host}:{$port} ({$errstr})");
        }

        stream_set_timeout($socket, $this->timeout);

        return $socket;
    }

    /**
     * @param resource $socket
     */
    private function readAll($socket): string
    {
        $data = '';
        $lastActivity = microtime(true);

        while (true) {
            $chunk = fread($socket, 65536);

            if ($chunk === false) {
                $meta = stream_get_meta_data($socket);

                if ($meta['eof'] === true || feof($socket)) {
                    break;
                }

                if (microtime(true) - $lastActivity >= $this->timeout) {
                    break;
                }

                usleep(10000);
                continue;
            }

            if ($chunk === '') {
                $meta = stream_get_meta_data($socket);

                if ($meta['eof'] === true || feof($socket)) {
                    break;
                }

                if (microtime(true) - $lastActivity >= $this->timeout) {
                    break;
                }

                usleep(10000);
                continue;
            }

            $data .= $chunk;
            $lastActivity = microtime(true);
        }

        return $data;
    }

    /**
     * Read pkt-lines until a flush packet.
     *
     * @param resource $socket
     */
    private function readUntilFlush($socket): string
    {
        $data = '';

        while (!feof($socket)) {
            $hexLen = fread($socket, 4);

            if ($hexLen === false || strlen($hexLen) < 4) {
                break;
            }

            $data .= $hexLen;

            if ($hexLen === PktLine::FLUSH) {
                break;
            }

            $lineLen = (int) hexdec($hexLen);

            if ($lineLen < 4) {
                break;
            }

            $payload = '';
            $remaining = $lineLen - 4;

            while (strlen($payload) < $remaining) {
                $chunk = fread($socket, $remaining - strlen($payload));

                if ($chunk === false) {
                    break 2;
                }

                $payload .= $chunk;
            }

            $data .= $payload;
        }

        return $data;
    }

    private function isNegotiationOnlyResponse(string $response): bool
    {
        $trimmed = trim($response);

        if ($trimmed === '') {
            return false;
        }

        return preg_match('/^(?:0008NAK\\n|0008ACK\\b.*\\n)+$/', $trimmed . "\n") === 1;
    }
}
