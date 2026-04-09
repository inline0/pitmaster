<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

use Pitmaster\Object\ObjectId;

/**
 * Git protocol v1 support.
 *
 * V1 uses service-specific endpoints with capabilities in the first ref line
 * (after a NUL byte). This class handles the v1 ref advertisement format
 * and negotiation.
 */
final class ProtocolV1
{
    public const DEFAULT_FETCH_CAPABILITIES = [
        'multi_ack_detailed',
        'no-done',
        'side-band-64k',
        'thin-pack',
        'no-progress',
        'ofs-delta',
        'deepen-since',
        'deepen-not',
        'agent=Pitmaster/1.0',
    ];

    public const DEFAULT_PUSH_CAPABILITIES = [
        'report-status-v2',
        'side-band-64k',
        'quiet',
        'object-format=sha1',
        'agent=Pitmaster/1.0',
    ];

    /**
     * Parse v1 ref advertisement (the format used by most servers).
     *
     * First line: "<hash> <refname>\0<capabilities>\n"
     * Subsequent lines: "<hash> <refname>\n"
     * Terminated by flush packet.
     *
     * @param string $data Raw pkt-line encoded response
     * @return RefDiscovery
     */
    public static function parseRefAdvertisement(string $data): RefDiscovery
    {
        $lines = PktLine::decode($data);

        return RefDiscovery::parse($lines);
    }

    /**
     * Build a v1 want request for upload-pack.
     *
     * @param array<int, ObjectId> $wants
     * @param array<int, ObjectId> $haves
     * @param array<int, string> $capabilities
     */
    public static function buildFetchRequest(
        array $wants,
        array $haves = [],
        array $capabilities = self::DEFAULT_FETCH_CAPABILITIES,
    ): string {
        $request = '';
        $first = true;

        foreach ($wants as $want) {
            if ($first) {
                $caps = implode(' ', $capabilities);
                $request .= PktLine::encode("want {$want->hex} {$caps}\n");
                $first = false;
            } else {
                $request .= PktLine::encode("want {$want->hex}\n");
            }
        }

        $request .= PktLine::flush();

        foreach ($haves as $have) {
            $request .= PktLine::encode("have {$have->hex}\n");
        }

        $request .= PktLine::encode("done\n");
        $request .= PktLine::flush();

        return $request;
    }

    /**
     * Build a v1 push request for receive-pack.
     *
     * @param array<int, array{old: ObjectId, new: ObjectId, ref: string}> $updates
     */
    public static function buildPushRequest(array $updates, array $capabilities = self::DEFAULT_PUSH_CAPABILITIES): string
    {
        $request = '';
        $first = true;

        foreach ($updates as $update) {
            $line = "{$update['old']->hex} {$update['new']->hex} {$update['ref']}";

            if ($first) {
                $line .= "\0 " . implode(' ', $capabilities);
                $first = false;
            }

            $request .= PktLine::encode($line . "\n");
        }

        $request .= PktLine::flush();

        return $request;
    }
}
