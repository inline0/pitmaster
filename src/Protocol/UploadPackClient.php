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

        // Build request
        $request = '';

        // First want includes capabilities
        $first = true;

        foreach ($wants as $want) {
            if ($first) {
                $request .= PktLine::encode("want {$want->hex} multi_ack_detailed side-band-64k ofs-delta\n");
                $first = false;
            } else {
                $request .= PktLine::encode("want {$want->hex}\n");
            }
        }

        $request .= PktLine::flush();

        // Send haves
        foreach ($haves as $have) {
            $request .= PktLine::encode("have {$have->hex}\n");
        }

        // Done
        $request .= PktLine::encode("done\n");

        $response = $this->http->uploadPack($url, $request);

        // Parse response: may contain NAK/ACK lines, then pack data
        return $this->extractPackData($response);
    }

    /**
     * Extract the raw pack data from an upload-pack response.
     *
     * The response may use side-band encoding (channel 1 = pack data,
     * channel 2 = progress, channel 3 = error).
     */
    private function extractPackData(string $response): string
    {
        // Always try side-band parsing first (GitHub and most servers use this).
        // Side-band pkt-line format: length + channel byte + data
        $packData = '';
        $offset = 0;
        $length = strlen($response);
        $hasSideBand = false;

        while ($offset < $length) {
            if ($offset + 4 > $length) {
                break;
            }

            $hexLen = substr($response, $offset, 4);

            if ($hexLen === PktLine::FLUSH) {
                $offset += 4;
                continue;
            }

            $lineLen = (int) hexdec($hexLen);

            if ($lineLen < 4) {
                $offset += 4;
                continue;
            }

            $payloadLen = $lineLen - 4;

            if ($offset + 4 + $payloadLen > $length) {
                break;
            }

            $payload = substr($response, $offset + 4, $payloadLen);

            if ($payloadLen >= 1) {
                $channel = ord($payload[0]);

                if ($channel === 1) {
                    $packData .= substr($payload, 1);
                    $hasSideBand = true;
                } elseif ($channel === 2 || $channel === 3) {
                    $hasSideBand = true;
                }
                // Other channels: NAK/ACK lines (not side-band)
            }

            $offset += $lineLen;
        }

        if ($hasSideBand && $packData !== '' && str_starts_with($packData, 'PACK')) {
            return $packData;
        }

        // No side-band: look for raw PACK data after NAK line
        $nakPos = strpos($response, "NAK\n");

        if ($nakPos !== false) {
            $afterNak = substr($response, $nakPos + 4);
            $packStart = strpos($afterNak, 'PACK');

            if ($packStart !== false) {
                return substr($afterNak, $packStart);
            }
        }

        // Last resort: find PACK anywhere
        $packPos = strpos($response, 'PACK');

        if ($packPos !== false) {
            return substr($response, $packPos);
        }

        return $response;
    }
}
