<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

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

        return $this->http->receivePack($url, $request);
    }
}
