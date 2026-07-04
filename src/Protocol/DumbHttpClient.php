<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

use Pitmaster\Exceptions\ProtocolException;
use Pitmaster\Object\ObjectId;

/**
 * Dumb HTTP client.
 *
 * The "dumb" HTTP protocol uses plain HTTP GET requests to fetch
 * individual files from the server. No git-specific server code needed.
 *
 * Used for read-only access to repos served by plain web servers.
 */
final class DumbHttpClient
{
    public function __construct(private readonly int $timeout = 30)
    {
    }

    /**
     * Fetch info/refs (plain text, not smart protocol).
     *
     * @return array<string, ObjectId> refname => ObjectId
     */
    public function fetchRefs(string $url): array
    {
        $content = $this->get(rtrim($url, '/') . '/info/refs');
        $refs = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = explode("\t", $line, 2);

            if (count($parts) === 2 && strlen($parts[0]) === 40 && ctype_xdigit($parts[0])) {
                $refs[$parts[1]] = ObjectId::fromHex($parts[0]);
            }
        }

        return $refs;
    }

    /**
     * Fetch a loose object.
     */
    public function fetchObject(string $url, string $hex): string
    {
        $prefix = substr($hex, 0, 2);
        $suffix = substr($hex, 2);
        $objectUrl = rtrim($url, '/') . "/objects/{$prefix}/{$suffix}";

        return $this->get($objectUrl);
    }

    /**
     * Fetch a pack file by name.
     */
    public function fetchPack(string $url, string $packName): string
    {
        $packUrl = rtrim($url, '/') . "/objects/pack/{$packName}";

        return $this->get($packUrl);
    }

    /**
     * Fetch the objects/info/packs file listing available packs.
     *
     * @return array<int, string> Pack file names
     */
    public function fetchPackList(string $url): array
    {
        $content = $this->get(rtrim($url, '/') . '/objects/info/packs');
        $packs = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if (str_starts_with($line, 'P ')) {
                $packs[] = substr($line, 2);
            }
        }

        return $packs;
    }

    /**
     * Fetch the remote HEAD file from a dumb HTTP export.
     */
    public function fetchHead(string $url): string
    {
        return $this->get(rtrim($url, '/') . '/HEAD');
    }

    private function get(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'header' => "User-Agent: Pitmaster/1.0\r\n",
                'follow_location' => true,
                'ignore_errors' => true,
            ],
        ]);

        $stream = @fopen($url, 'rb', false, $context);

        if ($stream === false) {
            $headers = $this->lastResponseHeaders();
            $status = $this->statusCode($headers);

            if ($status !== null) {
                throw new ProtocolException("Unexpected HTTP status {$status} from {$url}");
            }

            throw new ProtocolException("Dumb HTTP GET failed: {$url}");
        }

        $headers = $this->streamHeaders($stream);
        $response = stream_get_contents($stream);
        fclose($stream);

        if ($response === false) {
            throw new ProtocolException("Dumb HTTP GET failed: {$url}");
        }

        $status = $this->statusCode($headers);

        if ($status !== null && ($status < 200 || $status >= 300)) {
            throw new ProtocolException("Unexpected HTTP status {$status} from {$url}");
        }

        return $response;
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
