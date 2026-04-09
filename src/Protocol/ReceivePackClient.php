<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

use Pitmaster\Exceptions\ProtocolException;
use Pitmaster\Object\ObjectId;

/**
 * Push: send pack + ref update commands.
 */
final class ReceivePackClient
{
    public function __construct(private readonly ReceivePackTransport $transport)
    {
    }

    /**
     * Push objects to a remote.
     *
     * @param string $url Remote repository URL
     * @param array<int, array{old: ObjectId, new: ObjectId, ref: string}> $updates Ref updates
     * @param string $packData Raw pack file data to send
     * @param array<int, string>|null $capabilities Optional capability list for the first command
     * @return string Server response
     */
    public function push(string $url, array $updates, string $packData, ?array $capabilities = null): string
    {
        $request = ProtocolV1::buildPushRequest($updates, $capabilities ?? ProtocolV1::DEFAULT_PUSH_CAPABILITIES);
        $request .= $packData;

        $response = $this->transport->receivePack($url, $request);
        $this->validateResponse($response, $updates);

        return $response;
    }

    /**
     * @param array<int, array{old: ObjectId, new: ObjectId, ref: string}> $updates
     */
    private function validateResponse(string $response, array $updates): void
    {
        if ($response === '') {
            throw new ProtocolException('receive-pack returned empty response');
        }

        $lines = PktLine::decode($this->extractReportStatus($response));
        $unpackStatus = null;
        $acknowledgedRefs = [];

        foreach ($lines as $line) {
            if (!is_string($line) || $line === '') {
                continue;
            }

            if (str_starts_with($line, 'unpack ')) {
                $unpackStatus = substr($line, 7);
                continue;
            }

            if (str_starts_with($line, 'ok ')) {
                $acknowledgedRefs[] = substr($line, 3);
                continue;
            }

            if (str_starts_with($line, 'ng ')) {
                [, $ref, $reason] = array_pad(explode(' ', $line, 3), 3, '');
                throw new ProtocolException("receive-pack rejected {$ref}: {$reason}");
            }
        }

        if ($unpackStatus === null) {
            throw new ProtocolException('receive-pack response missing unpack status');
        }

        if ($unpackStatus !== 'ok') {
            throw new ProtocolException("receive-pack unpack failed: {$unpackStatus}");
        }

        foreach ($updates as $update) {
            if (!in_array($update['ref'], $acknowledgedRefs, true)) {
                throw new ProtocolException("receive-pack response missing status for {$update['ref']}");
            }
        }
    }

    private function extractReportStatus(string $response): string
    {
        $sideband = '';
        $offset = 0;
        $length = strlen($response);

        while ($offset + 4 <= $length) {
            $hexLen = substr($response, $offset, 4);

            if (!ctype_xdigit($hexLen)) {
                break;
            }

            $lineLen = (int) hexdec($hexLen);

            if ($lineLen === 0) {
                $offset += 4;
                continue;
            }

            if ($lineLen < 4 || $offset + $lineLen > $length) {
                break;
            }

            $payload = substr($response, $offset + 4, $lineLen - 4);

            if ($payload !== '' && in_array(ord($payload[0]), [1, 2, 3], true)) {
                if (ord($payload[0]) === 1) {
                    $sideband .= substr($payload, 1);
                }

                $offset += $lineLen;
                continue;
            }

            return $response;
        }

        return $sideband !== '' ? $sideband : $response;
    }
}
