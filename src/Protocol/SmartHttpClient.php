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
final class SmartHttpClient implements UploadPackTransport, ReceivePackTransport
{
    private int $timeout;

    public function __construct(?int $timeout = null)
    {
        if ($timeout !== null) {
            $this->timeout = $timeout;
        } elseif (defined('PITMASTER_HTTP_TIMEOUT')) {
            $value = constant('PITMASTER_HTTP_TIMEOUT');
            $this->timeout = is_int($value) ? $value : 30;
        } else {
            $this->timeout = 30;
        }
    }

    /**
     * Discover refs from a remote URL.
     */
    public function discoverRefs(string $url): RefDiscovery
    {
        return $this->discoverServiceRefs($url, 'git-upload-pack', 'application/x-git-upload-pack-advertisement');
    }

    /**
     * Discover refs and capabilities from a remote receive-pack advertisement.
     */
    public function discoverReceivePackRefs(string $url): RefDiscovery
    {
        return $this->discoverServiceRefs($url, 'git-receive-pack', 'application/x-git-receive-pack-advertisement');
    }

    /**
     * Discover refs from a remote URL using protocol v2.
     */
    public function discoverRefsV2(string $url): RefDiscovery
    {
        $infoUrl = rtrim($url, '/') . '/info/refs?service=git-upload-pack';
        $advertisement = $this->get(
            $infoUrl,
            'application/x-git-upload-pack-advertisement',
            ['Git-Protocol: version=2'],
        );
        $capabilities = ProtocolV2::parseAdvertisement($advertisement);
        $response = $this->post(
            rtrim($url, '/') . '/git-upload-pack',
            ProtocolV2::buildLsRefsRequest(),
            'application/x-git-upload-pack-request',
            'application/x-git-upload-pack-result',
            ['Git-Protocol: version=2'],
        );

        return ProtocolV2::parseLsRefsResponse($response, $capabilities);
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
     * POST to git-upload-pack (fetch) using protocol v2.
     */
    public function uploadPackV2(string $url, string $body): string
    {
        $packUrl = rtrim($url, '/') . '/git-upload-pack';

        return $this->post(
            $packUrl,
            $body,
            'application/x-git-upload-pack-request',
            'application/x-git-upload-pack-result',
            ['Git-Protocol: version=2'],
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

    private function discoverServiceRefs(string $url, string $service, string $expectedContentType): RefDiscovery
    {
        $infoUrl = rtrim($url, '/') . '/info/refs?service=' . $service;
        $response = $this->get($infoUrl, $expectedContentType);
        $pktLines = PktLine::decode($response);
        $filtered = [];

        foreach ($pktLines as $line) {
            if (!is_string($line)) {
                continue;
            }

            if (str_starts_with($line, '# service=')) {
                continue;
            }

            $filtered[] = $line;
        }

        return RefDiscovery::parse($filtered);
    }

    /**
     * @param array<int, string> $extraHeaders
     */
    private function get(string $url, string $expectedContentType, array $extraHeaders = []): string
    {
        $headers = array_merge(['User-Agent: Pitmaster/1.0'], $extraHeaders);
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'header' => implode("\r\n", $headers),
                'follow_location' => true,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $stream = @fopen($url, 'rb', false, $context);

        if ($stream === false) {
            $headers = $this->lastResponseHeaders();
            $status = $this->statusCode($headers);
            if ($status !== null) {
                throw new ProtocolException("Unexpected HTTP status {$status} from {$url}");
            }

            throw new ProtocolException("HTTP GET failed: {$url}");
        }

        $headers = $this->streamHeaders($stream);
        $response = stream_get_contents($stream);
        fclose($stream);

        if ($response === false) {
            throw new ProtocolException("HTTP GET failed: {$url}");
        }

        $this->validateResponse($url, $headers, $expectedContentType);

        return $response;
    }

    /**
     * @param array<int, string> $extraHeaders
     */
    private function post(
        string $url,
        string $body,
        string $contentType,
        string $accept,
        array $extraHeaders = [],
    ): string {
        $headers = array_merge([
            "Content-Type: {$contentType}",
            "Accept: {$accept}",
            'User-Agent: Pitmaster/1.0',
        ], $extraHeaders);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => $this->timeout,
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'follow_location' => true,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $stream = @fopen($url, 'rb', false, $context);

        if ($stream === false) {
            $headers = $this->lastResponseHeaders();
            $status = $this->statusCode($headers);
            if ($status !== null) {
                throw new ProtocolException("Unexpected HTTP status {$status} from {$url}");
            }

            throw new ProtocolException("HTTP POST failed: {$url}");
        }

        $headers = $this->streamHeaders($stream);
        $response = stream_get_contents($stream);
        fclose($stream);

        if ($response === false) {
            throw new ProtocolException("HTTP POST failed: {$url}");
        }

        $this->validateResponse($url, $headers, $accept);

        return $response;
    }

    /**
     * @param array<int, string>|null $headers
     */
    private function validateResponse(string $url, ?array $headers, string $expectedContentType): void
    {
        if ($headers === null) {
            throw new ProtocolException("HTTP response missing headers from {$url}");
        }

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
     * @param array<int, string>|null $headers
     */
    private function statusCode(?array $headers): ?int
    {
        if ($headers === null) {
            return null;
        }

        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\d+(?:\.\d+)?\s+(\d{3})\b/i', $header, $matches) === 1) {
                return (int) $matches[1];
            }
        }

        return null;
    }

    /**
     * @param array<int, string>|null $headers
     */
    private function contentType(?array $headers): ?string
    {
        if ($headers === null) {
            return null;
        }

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

    /**
     * @param resource $stream
     * @return array<int, string>
     */
    private function streamHeaders(mixed $stream): array
    {
        $metadata = stream_get_meta_data($stream);
        $headers = $metadata['wrapper_data'] ?? [];

        if (is_string($headers)) {
            return [$headers];
        }

        if (!is_array($headers)) {
            return [];
        }

        return array_values(array_filter($headers, 'is_string'));
    }

    /**
     * @return array<int, string>
     */
    private function lastResponseHeaders(): array
    {
        if (!function_exists('http_get_last_response_headers')) {
            return [];
        }

        $headers = http_get_last_response_headers();

        return is_array($headers) ? $headers : [];
    }
}
