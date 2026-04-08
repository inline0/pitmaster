<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

use Pitmaster\Exceptions\ProtocolException;

/**
 * Git smart HTTP transport layer.
 *
 * Communicates with git servers over HTTPS using the smart HTTP protocol.
 * Uses PHP's native stream functions (file_get_contents with stream context).
 */
final class SmartHttpClient
{
    private int $timeout;

    public function __construct(?int $timeout = null)
    {
        $this->timeout = $timeout ?? (defined('PITMASTER_HTTP_TIMEOUT')
            ? (int) constant('PITMASTER_HTTP_TIMEOUT')
            : 30);
    }

    /**
     * Discover refs from a remote URL.
     */
    public function discoverRefs(string $url): RefDiscovery
    {
        $infoUrl = rtrim($url, '/') . '/info/refs?service=git-upload-pack';
        $response = $this->get($infoUrl, 'application/x-git-upload-pack-advertisement');

        // Skip the service announcement line
        $pktLines = PktLine::decode($response);

        // First pkt-line is "# service=git-upload-pack\n"
        $filtered = [];

        foreach ($pktLines as $line) {
            if (!is_string($line)) {
                continue; // skip flush (null) and delimiter (false) packets
            }

            if (str_starts_with($line, '# service=')) {
                continue;
            }

            $filtered[] = $line;
        }

        return RefDiscovery::parse($filtered);
    }

    /**
     * POST to git-upload-pack (fetch).
     */
    public function uploadPack(string $url, string $body): string
    {
        $packUrl = rtrim($url, '/') . '/git-upload-pack';

        return $this->post(
            $packUrl,
            $body,
            'application/x-git-upload-pack-request',
            'application/x-git-upload-pack-result',
        );
    }

    /**
     * POST to git-receive-pack (push).
     */
    public function receivePack(string $url, string $body): string
    {
        $packUrl = rtrim($url, '/') . '/git-receive-pack';

        return $this->post(
            $packUrl,
            $body,
            'application/x-git-receive-pack-request',
            'application/x-git-receive-pack-result',
        );
    }

    private function get(string $url, string $expectedContentType): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'header' => "User-Agent: Pitmaster/1.0\r\n",
                'follow_location' => true,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $headers = $http_response_header;

        if ($response === false) {
            $status = $this->statusCode($headers);
            if ($status !== null) {
                throw new ProtocolException("Unexpected HTTP status {$status} from {$url}");
            }

            throw new ProtocolException("HTTP GET failed: {$url}");
        }

        $this->validateResponse($url, $headers, $expectedContentType);

        return $response;
    }

    private function post(string $url, string $body, string $contentType, string $accept): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => $this->timeout,
                'header' => implode("\r\n", [
                    "Content-Type: {$contentType}",
                    "Accept: {$accept}",
                    'User-Agent: Pitmaster/1.0',
                ]),
                'content' => $body,
                'follow_location' => true,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $headers = $http_response_header;

        if ($response === false) {
            $status = $this->statusCode($headers);
            if ($status !== null) {
                throw new ProtocolException("Unexpected HTTP status {$status} from {$url}");
            }

            throw new ProtocolException("HTTP POST failed: {$url}");
        }

        $this->validateResponse($url, $headers, $accept);

        return $response;
    }

    /**
     * @param array<int, string> $headers
     */
    private function validateResponse(string $url, array $headers, string $expectedContentType): void
    {
        $status = $this->statusCode($headers);

        if ($status === null) {
            throw new ProtocolException("HTTP response missing status line: {$url}");
        }

        if ($status < 200 || $status >= 300) {
            throw new ProtocolException("Unexpected HTTP status {$status} from {$url}");
        }

        $contentType = $this->contentType($headers);

        if ($contentType === null) {
            throw new ProtocolException("HTTP response missing Content-Type from {$url}");
        }

        if (!str_starts_with(strtolower($contentType), strtolower($expectedContentType))) {
            throw new ProtocolException(
                "Unexpected Content-Type {$contentType} from {$url}, expected {$expectedContentType}"
            );
        }
    }

    /**
     * @param array<int, string> $headers
     */
    private function statusCode(array $headers): ?int
    {
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d{3})\b/i', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $headers
     */
    private function contentType(array $headers): ?string
    {
        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type:') !== 0) {
                continue;
            }

            $value = trim(substr($header, 13));

            if ($value === '') {
                return null;
            }

            $parts = explode(';', $value, 2);

            return trim($parts[0]);
        }

        return null;
    }
}
