<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

use Pitmaster\Exceptions\ProtocolException;

/**
 * Pkt-line encoding/decoding for the git protocol.
 *
 * Format: <4 hex digits = total line length including 4 bytes><payload>
 * Special packets: 0000 = flush, 0001 = delimiter (v2)
 */
final class PktLine
{
    public const FLUSH = "0000";
    public const DELIMITER = "0001";
    public const MAX_PAYLOAD = 65516; // 65520 - 4

    /**
     * Encode a line as pkt-line.
     */
    public static function encode(string $data): string
    {
        $length = strlen($data) + 4;

        if ($length > 65520) {
            throw new ProtocolException("Pkt-line too long: {$length} bytes");
        }

        return sprintf('%04x', $length) . $data;
    }

    /**
     * Encode a flush packet.
     */
    public static function flush(): string
    {
        return self::FLUSH;
    }

    /**
     * Encode a delimiter packet (protocol v2).
     */
    public static function delimiter(): string
    {
        return self::DELIMITER;
    }

    /**
     * Decode pkt-lines from a stream string.
     *
     * @return array<int, string|null|false> Decoded payloads (null = flush, false = delimiter)
     */
    public static function decode(string $data): array
    {
        $lines = [];
        $offset = 0;
        $length = strlen($data);

        while ($offset < $length) {
            if ($offset + 4 > $length) {
                break;
            }

            $hexLen = substr($data, $offset, 4);

            if ($hexLen === self::FLUSH) {
                $lines[] = null; // flush marker
                $offset += 4;
                continue;
            }

            if ($hexLen === self::DELIMITER) {
                $lines[] = false; // delimiter marker
                $offset += 4;
                continue;
            }

            if (!ctype_xdigit($hexLen)) {
                throw new ProtocolException("Invalid pkt-line length: {$hexLen}");
            }

            $lineLen = (int) hexdec($hexLen);

            if ($lineLen < 4 || $lineLen > 65520) {
                throw new ProtocolException("Invalid pkt-line length: {$hexLen}");
            }

            $payloadLen = $lineLen - 4;

            if ($offset + 4 + $payloadLen > $length) {
                throw new ProtocolException('Truncated pkt-line');
            }

            $payload = substr($data, $offset + 4, $payloadLen);

            // Strip trailing newline (common in pkt-line payloads)
            $lines[] = rtrim($payload, "\n");
            $offset += $lineLen;
        }

        return $lines;
    }

    /**
     * Read pkt-lines from a stream until flush packet.
     *
     * @param resource $stream
     * @return array<int, string>
     */
    public static function readFromStream($stream): array
    {
        $lines = [];

        while (true) {
            $hexLen = fread($stream, 4);

            if ($hexLen === false || strlen($hexLen) < 4) {
                break;
            }

            if ($hexLen === self::FLUSH) {
                break;
            }

            if ($hexLen === self::DELIMITER) {
                break;
            }

            if (!ctype_xdigit($hexLen)) {
                throw new ProtocolException("Invalid pkt-line length: {$hexLen}");
            }

            $lineLen = (int) hexdec($hexLen);

            if ($lineLen < 4 || $lineLen > 65520) {
                throw new ProtocolException("Invalid pkt-line length: {$hexLen}");
            }

            $payloadLen = $lineLen - 4;
            $payload = '';

            while (strlen($payload) < $payloadLen) {
                $remaining = $payloadLen - strlen($payload);

                if ($remaining < 1) {
                    break;
                }

                $chunk = fread($stream, $remaining);

                if ($chunk === false || $chunk === '') {
                    throw new ProtocolException('Truncated pkt-line stream');
                }

                $payload .= $chunk;
            }

            $lines[] = rtrim($payload, "\n");
        }

        return $lines;
    }
}
