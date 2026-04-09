<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

use Pitmaster\Exceptions\ProtocolException;
use Pitmaster\Object\ObjectId;

/**
 * Protocol v2 request/response helpers.
 */
final class ProtocolV2
{
    /**
     * @var list<string>
     */
    public const DEFAULT_LS_REFS_PREFIXES = [
        'refs/heads/',
        'refs/tags/',
        'HEAD',
    ];

    /**
     * @var list<string>
     */
    public const DEFAULT_FETCH_FEATURES = [
        'thin-pack',
        'no-progress',
        'ofs-delta',
    ];

    /**
     * Parse a v2 advertisement into a capability set.
     */
    public static function parseAdvertisement(string $response): Capability
    {
        $capabilities = [];

        foreach (self::decode($response) as $packet) {
            if ($packet['type'] !== 'data') {
                continue;
            }

            $line = $packet['payload'];

            if ($line === null || $line === '' || $line === 'version 2') {
                continue;
            }

            $eqPos = strpos($line, '=');

            if ($eqPos === false) {
                $capabilities[$line] = null;
                continue;
            }

            $capabilities[substr($line, 0, $eqPos)] = substr($line, $eqPos + 1);
        }

        return Capability::fromArray($capabilities);
    }

    /**
     * Build a protocol v2 ls-refs request.
     *
     * @param list<string> $refPrefixes
     * @param list<string> $serverOptions
     */
    public static function buildLsRefsRequest(
        array $refPrefixes = self::DEFAULT_LS_REFS_PREFIXES,
        bool $symrefs = true,
        bool $peel = true,
        bool $unborn = true,
        array $serverOptions = [],
    ): string {
        $request = self::commandPrefix('ls-refs', $serverOptions);

        if ($peel) {
            $request .= PktLine::encode("peel\n");
        }

        if ($symrefs) {
            $request .= PktLine::encode("symrefs\n");
        }

        if ($unborn) {
            $request .= PktLine::encode("unborn\n");
        }

        foreach ($refPrefixes as $prefix) {
            $request .= PktLine::encode("ref-prefix {$prefix}\n");
        }

        return $request . PktLine::flush();
    }

    /**
     * Parse a protocol v2 ls-refs response.
     */
    public static function parseLsRefsResponse(string $response, ?Capability $capabilities = null): RefDiscovery
    {
        $refs = [];
        $headSymref = null;

        foreach (self::decode($response) as $packet) {
            if ($packet['type'] !== 'data') {
                continue;
            }

            $line = $packet['payload'];

            if ($line === null || $line === '') {
                continue;
            }

            $parts = explode(' ', $line);

            if (count($parts) < 2 || strlen($parts[0]) !== 40 || !ctype_xdigit($parts[0])) {
                continue;
            }

            $oid = ObjectId::fromHex($parts[0]);
            $refName = $parts[1];
            $refs[$refName] = $oid;

            foreach (array_slice($parts, 2) as $attribute) {
                if (str_starts_with($attribute, 'symref-target:') && $refName === 'HEAD') {
                    $headSymref = substr($attribute, 14);
                }
            }
        }

        return RefDiscovery::fromParsed($refs, $capabilities, $headSymref);
    }

    /**
     * Build a protocol v2 fetch request.
     *
     * @param list<ObjectId> $wants
     * @param list<ObjectId> $haves
     * @param list<string> $features
     * @param list<string> $serverOptions
     */
    public static function buildFetchRequest(
        array $wants,
        array $haves = [],
        array $features = self::DEFAULT_FETCH_FEATURES,
        bool $done = true,
        array $serverOptions = [],
        ?int $depth = null,
    ): string {
        if ($wants === []) {
            throw new ProtocolException('protocol v2 fetch requires at least one want');
        }

        $request = self::commandPrefix('fetch', $serverOptions);

        foreach ($features as $feature) {
            $request .= PktLine::encode($feature . "\n");
        }

        if ($depth !== null) {
            $request .= PktLine::encode("deepen {$depth}\n");
        }

        foreach ($wants as $want) {
            $request .= PktLine::encode("want {$want->hex}\n");
        }

        foreach ($haves as $have) {
            $request .= PktLine::encode("have {$have->hex}\n");
        }

        if ($done) {
            $request .= PktLine::encode("done\n");
        }

        return $request . PktLine::flush();
    }

    /**
     * Extract raw pack data from a protocol v2 fetch response.
     */
    public static function extractPackData(string $response): string
    {
        $packPos = strpos($response, 'PACK');

        if ($packPos !== false) {
            return substr($response, $packPos);
        }

        throw new ProtocolException('protocol v2 fetch response did not contain pack data');
    }

    /**
     * @param list<string> $serverOptions
     */
    private static function commandPrefix(string $command, array $serverOptions): string
    {
        $request = PktLine::encode("command={$command}\n");
        $request .= PktLine::encode("agent=Pitmaster/1.0\n");
        $request .= PktLine::encode("object-format=sha1\n");

        foreach ($serverOptions as $option) {
            $request .= PktLine::encode("server-option={$option}\n");
        }

        return $request . PktLine::delimiter();
    }

    /**
     * @return list<array{type: 'data'|'flush'|'delimiter'|'response-end', payload: ?string}>
     */
    public static function decode(string $data): array
    {
        $packets = [];
        $offset = 0;
        $length = strlen($data);

        while ($offset + 4 <= $length) {
            $hexLen = substr($data, $offset, 4);

            if ($hexLen === PktLine::FLUSH) {
                $packets[] = ['type' => 'flush', 'payload' => null];
                $offset += 4;
                continue;
            }

            if ($hexLen === PktLine::DELIMITER) {
                $packets[] = ['type' => 'delimiter', 'payload' => null];
                $offset += 4;
                continue;
            }

            if ($hexLen === '0002') {
                $packets[] = ['type' => 'response-end', 'payload' => null];
                $offset += 4;
                continue;
            }

            if (!ctype_xdigit($hexLen)) {
                break;
            }

            $lineLen = (int) hexdec($hexLen);

            if ($lineLen < 4 || $offset + $lineLen > $length) {
                throw new ProtocolException('Truncated protocol v2 pkt-line');
            }

            $payload = substr($data, $offset + 4, $lineLen - 4);
            $packets[] = ['type' => 'data', 'payload' => rtrim($payload, "\n")];
            $offset += $lineLen;
        }

        return $packets;
    }
}
