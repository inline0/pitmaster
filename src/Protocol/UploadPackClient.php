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
    public function __construct(private readonly SmartHttpClient $http)
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
    public function fetch(string $url, array $wants, array $haves = []): string
    {
        if ($wants === []) {
            return '';
        }

        $request = ProtocolV1::buildFetchRequest($wants, $haves);

        $response = $this->http->uploadPack($url, $request);

        // Parse response: may contain NAK/ACK lines, then pack data
        return $this->extractPackData($response);
    }

    /**
     * Fetch objects from a remote using protocol v2.
     *
     * @param string $url Remote repository URL
     * @param array<int, ObjectId> $wants Object IDs we want
     * @param array<int, ObjectId> $haves Object IDs we already have
     * @return string Raw pack data
     */
    public function fetchV2(string $url, array $wants, array $haves = []): string
    {
        if ($wants === []) {
            return '';
        }

        $request = ProtocolV2::buildFetchRequest($wants, $haves);
        $response = $this->http->uploadPackV2($url, $request);

        return ProtocolV2::extractPackData($response);
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
}
