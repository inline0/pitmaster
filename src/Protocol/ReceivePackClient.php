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
    public function __construct(private readonly SmartHttpClient $http)
    {
    }

    /**
     * Push objects to a remote.
     *
     * @param string $url Remote repository URL
     * @param array<int, array{old: ObjectId, new: ObjectId, ref: string}> $updates Ref updates
     * @param string $packData Raw pack file data to send
     * @return string Server response
     */
    public function push(string $url, array $updates, string $packData): string
    {
        $request = '';

        $first = true;

        foreach ($updates as $update) {
            $line = sprintf(
                '%s %s %s',
                $update['old']->hex,
                $update['new']->hex,
                $update['ref'],
            );

            if ($first) {
                $line .= "\0report-status";
                $first = false;
            }

            $request .= PktLine::encode($line . "\n");
        }

        $request .= PktLine::flush();
        $request .= $packData;

        $response = $this->http->receivePack($url, $request);
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

        $lines = PktLine::decode($response);
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
}
