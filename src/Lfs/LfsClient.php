<?php

declare(strict_types=1);

namespace Pitmaster\Lfs;

use Pitmaster\Exceptions\ProtocolException;

/**
 * Git LFS batch API client.
 *
 * Communicates with LFS servers to download and upload large files.
 * Uses the batch API: POST to <url>/objects/batch with JSON body.
 */
final class LfsClient
{
    public function __construct(
        private readonly string $lfsUrl,
        private readonly int $timeout = 60,
    ) {
    }

    /**
     * Derive LFS URL from a git remote URL.
     */
    public static function fromRemoteUrl(string $gitUrl): self
    {
        // Standard convention: append /info/lfs to the git URL
        $lfsUrl = rtrim($gitUrl, '/');

        if (str_ends_with($lfsUrl, '.git')) {
            $lfsUrl .= '/info/lfs';
        } else {
            $lfsUrl .= '.git/info/lfs';
        }

        return new self($lfsUrl);
    }

    /**
     * Request download URLs for LFS objects.
     *
     * @param array<int, array{oid: string, size: int}> $objects
     * @return array<int, array{oid: string, size: int, href: ?string, error: ?string}>
     */
    public function batchDownload(array $objects): array
    {
        return $this->batch('download', $objects);
    }

    /**
     * Request upload URLs for LFS objects.
     *
     * @param array<int, array{oid: string, size: int}> $objects
     * @return array<int, array{oid: string, size: int, href: ?string, error: ?string}>
     */
    public function batchUpload(array $objects): array
    {
        return $this->batch('upload', $objects);
    }

    /**
     * Download an LFS object by its OID.
     */
    public function download(string $oid, int $size): string
    {
        $batch = $this->batchDownload([['oid' => $oid, 'size' => $size]]);

        if (empty($batch[0]['href'])) {
            throw new ProtocolException("LFS download failed for {$oid}: no download URL");
        }

        return $this->httpGet($batch[0]['href']);
    }

    /**
     * Upload an LFS object.
     */
    public function upload(string $oid, int $size, string $content): void
    {
        $batch = $this->batchUpload([['oid' => $oid, 'size' => $size]]);

        if (empty($batch[0]['href'])) {
            return; // Server already has it
        }

        $this->httpPut($batch[0]['href'], $content);
    }

    /**
     * @param array<int, array{oid: string, size: int}> $objects
     * @return array<int, array{oid: string, size: int, href: ?string, error: ?string}>
     */
    private function batch(string $operation, array $objects): array
    {
        $body = json_encode([
            'operation' => $operation,
            'transfers' => ['basic'],
            'objects' => $objects,
        ], JSON_UNESCAPED_SLASHES);

        $url = rtrim($this->lfsUrl, '/') . '/objects/batch';

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'timeout' => $this->timeout,
                'header' => implode("\r\n", [
                    'Content-Type: application/vnd.git-lfs+json',
                    'Accept: application/vnd.git-lfs+json',
                    'User-Agent: Pitmaster-LFS/1.0',
                ]),
                'content' => $body,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new ProtocolException("LFS batch request failed: {$url}");
        }

        $data = json_decode($response, true);

        if (!is_array($data) || !isset($data['objects'])) {
            throw new ProtocolException('Invalid LFS batch response');
        }

        $results = [];

        foreach ($data['objects'] as $obj) {
            $href = null;

            if (isset($obj['actions'][$operation]['href'])) {
                $href = $obj['actions'][$operation]['href'];
            }

            $error = $obj['error']['message'] ?? null;

            $results[] = [
                'oid' => $obj['oid'] ?? '',
                'size' => $obj['size'] ?? 0,
                'href' => $href,
                'error' => $error,
            ];
        }

        return $results;
    }

    private function httpGet(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'header' => "User-Agent: Pitmaster-LFS/1.0\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new ProtocolException("LFS download failed: {$url}");
        }

        return $response;
    }

    private function httpPut(string $url, string $content): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'PUT',
                'timeout' => $this->timeout,
                'header' => implode("\r\n", [
                    'Content-Type: application/octet-stream',
                    'User-Agent: Pitmaster-LFS/1.0',
                ]),
                'content' => $content,
            ],
        ]);

        @file_get_contents($url, false, $context);
    }
}
