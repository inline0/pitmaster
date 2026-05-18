<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

use Pitmaster\Exceptions\ProtocolException;
use Pitmaster\Object\ObjectId;

/**
 * Fetch negotiation: want/have/done protocol.
 *
 * Sends wants (refs we need), haves (refs we already have), and
 * receives a pack file containing the missing objects.
 */
final class UploadPackClient
{
    public function __construct(private readonly UploadPackTransport $transport)
    {
    }

    /**
     * Fetch objects from a remote.
     *
     * @param string $url Remote repository URL
     * @param array<int, ObjectId> $wants Object IDs we want
     * @param array<int, ObjectId> $haves Object IDs we already have
     * @return string Raw pack data
     */
    public function fetch(string $url, array $wants, array $haves = [], ?int $depth = null): string
    {
        return $this->fetchResult($url, $wants, $haves, $depth)['packData'];
    }

    /**
     * Fetch objects plus shallow-state updates from a remote.
     *
     * @param string $url Remote repository URL
     * @param array<int, ObjectId> $wants Object IDs we want
     * @param array<int, ObjectId> $haves Object IDs we already have
     * @return array{packData: string, shallow: list<ObjectId>, unshallow: list<ObjectId>}
     */
    public function fetchResult(string $url, array $wants, array $haves = [], ?int $depth = null): array
    {
        if ($wants === []) {
            return [
                'packData' => '',
                'shallow' => [],
                'unshallow' => [],
            ];
        }

        $request = ProtocolV1::buildFetchRequest($wants, $haves, ProtocolV1::DEFAULT_FETCH_CAPABILITIES, $depth);

        $response = $this->transport->uploadPack($url, $request);

        return $this->extractResult($response);
    }

    /**
     * Fetch objects from a remote using protocol v2.
     *
     * @param string $url Remote repository URL
     * @param array<int, ObjectId> $wants Object IDs we want
     * @param array<int, ObjectId> $haves Object IDs we already have
     * @return string Raw pack data
     */
    public function fetchV2(string $url, array $wants, array $haves = [], ?int $depth = null): string
    {
        return $this->fetchV2Result($url, $wants, $haves, $depth)['packData'];
    }

    /**
     * @param string $url Remote repository URL
     * @param array<int, ObjectId> $wants Object IDs we want
     * @param array<int, ObjectId> $haves Object IDs we already have
     * @return array{packData: string, shallow: list<ObjectId>, unshallow: list<ObjectId>}
     */
    public function fetchV2Result(string $url, array $wants, array $haves = [], ?int $depth = null): array
    {
        if ($wants === []) {
            return [
                'packData' => '',
                'shallow' => [],
                'unshallow' => [],
            ];
        }

        $request = ProtocolV2::buildFetchRequest(array_values($wants), array_values($haves), ProtocolV2::DEFAULT_FETCH_FEATURES, true, [], $depth);
        if (!$this->transport instanceof SmartHttpClient) {
            throw new ProtocolException('Protocol v2 fetch requires smart HTTP transport');
        }

        $response = $this->transport->uploadPackV2($url, $request);

        return $this->extractResult($response, true);
    }

    /**
     * Extract the raw pack data from an upload-pack response.
     *
     * The response may use side-band encoding (channel 1 = pack data,
     * channel 2 = progress, channel 3 = error).
     */
    private function extractPackData(string $response): string
    {
        $packData = '';
        $offset = 0;
        $length = strlen($response);
        $hasSideBand = false;
        $errors = [];

        while ($offset < $length) {
            if ($offset + 4 > $length) {
                throw new ProtocolException('Truncated upload-pack response');
            }

            $hexLen = substr($response, $offset, 4);

            if ($hexLen === PktLine::FLUSH) {
                $offset += 4;
                continue;
            }

            if (!ctype_xdigit($hexLen)) {
                throw new ProtocolException("Invalid pkt-line length in upload-pack response: {$hexLen}");
            }

            $lineLen = (int) hexdec($hexLen);

            if ($lineLen < 4) {
                throw new ProtocolException("Invalid pkt-line length in upload-pack response: {$hexLen}");
            }

            $payloadLen = $lineLen - 4;

            if ($offset + 4 + $payloadLen > $length) {
                throw new ProtocolException('Truncated side-band packet in upload-pack response');
            }

            $payload = substr($response, $offset + 4, $payloadLen);

            if ($payloadLen >= 1) {
                $channel = ord($payload[0]);

                if ($channel === 1) {
                    $packData .= substr($payload, 1);
                    $hasSideBand = true;
                } elseif ($channel === 2 || $channel === 3) {
                    $hasSideBand = true;
                    if ($channel === 3) {
                        $errors[] = trim(substr($payload, 1));
                    }
                } elseif (str_starts_with($payload, "ERR ")) {
                    $errors[] = trim(substr($payload, 4));
                }
            }

            $offset += $lineLen;
        }

        if ($errors !== []) {
            throw new ProtocolException('upload-pack error: ' . implode('; ', array_filter($errors)));
        }

        if ($hasSideBand && $packData !== '' && str_starts_with($packData, 'PACK')) {
            return $packData;
        }

        if ($hasSideBand) {
            throw new ProtocolException('upload-pack response did not contain pack data');
        }

        $nakPos = strpos($response, "NAK\n");

        if ($nakPos !== false) {
            $afterNak = substr($response, $nakPos + 4);
            $packStart = strpos($afterNak, 'PACK');

            if ($packStart !== false) {
                return substr($afterNak, $packStart);
            }
        }

        $packPos = strpos($response, 'PACK');

        if ($packPos !== false) {
            return substr($response, $packPos);
        }

        if (preg_match('/(?:^|\n)ERR (.+)/', $response, $matches) === 1) {
            throw new ProtocolException('upload-pack error: ' . trim($matches[1]));
        }

        throw new ProtocolException('upload-pack response did not contain pack data');
    }

    /**
     * @return array{packData: string, shallow: list<ObjectId>, unshallow: list<ObjectId>}
     */
    private function extractResult(string $response, bool $protocolV2 = false): array
    {
        $packData = $protocolV2 ? ProtocolV2::extractPackData($response) : $this->extractPackData($response);
        $packPos = strpos($response, 'PACK');
        $prefix = $packPos === false ? $response : substr($response, 0, $packPos);

        return [
            'packData' => $packData,
            'shallow' => $this->extractObjectIdsFromPrefix($prefix, 'shallow'),
            'unshallow' => $this->extractObjectIdsFromPrefix($prefix, 'unshallow'),
        ];
    }

    /**
     * @return list<ObjectId>
     */
    private function extractObjectIdsFromPrefix(string $prefix, string $keyword): array
    {
        $matches = [];
        preg_match_all(
            '/\b' . preg_quote($keyword, '/') . ' ([0-9a-f]{40}|[0-9a-f]{64})\n/i',
            $prefix,
            $matches,
        );

        return array_values(array_map(
            static fn (string $hex): ObjectId => ObjectId::fromHex(strtolower($hex)),
            $matches[1],
        ));
    }
}
