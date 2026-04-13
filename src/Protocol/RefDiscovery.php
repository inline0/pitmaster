<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

use Pitmaster\Object\ObjectId;

/**
 * Parse remote ref advertisement from git smart HTTP.
 *
 * V1 format: first line has capabilities after NUL byte.
 * V2 format: capabilities in separate section.
 */
final class RefDiscovery
{
    /** @var array<string, ObjectId> */
    private array $refs = [];

    private ?Capability $capabilities = null;

    private ?string $headSymref = null;

    /**
     * @param array<string, ObjectId> $refs
     */
    public static function fromParsed(array $refs, ?Capability $capabilities = null, ?string $headSymref = null): self
    {
        $discovery = new self();
        $discovery->refs = $refs;
        $discovery->capabilities = $capabilities;
        $discovery->headSymref = $headSymref;

        return $discovery;
    }

    /**
     * Parse ref discovery response.
     *
     * @param array<int, mixed> $pktLines Decoded pkt-lines
     */
    public static function parse(array $pktLines): self
    {
        $discovery = new self();
        $firstRef = true;

        foreach ($pktLines as $line) {
            if ($line === null || $line === false || !is_string($line)) {
                continue;
            }

            if (str_starts_with($line, '# service=')) {
                continue;
            }

            if (self::ingestLine($discovery, $line, $firstRef)) {
                $firstRef = false;
            }
        }

        return $discovery;
    }

    public static function parseAdvertisement(string $advertisement): self
    {
        $discovery = new self();
        $offset = 0;
        $length = strlen($advertisement);
        $firstRef = true;

        while ($offset < $length) {
            if ($offset + 4 > $length) {
                break;
            }

            $hexLen = substr($advertisement, $offset, 4);

            if ($hexLen === PktLine::FLUSH || $hexLen === PktLine::DELIMITER || $hexLen === '0002') {
                $offset += 4;
                continue;
            }

            if (!ctype_xdigit($hexLen)) {
                throw new \Pitmaster\Exceptions\ProtocolException("Invalid pkt-line length: {$hexLen}");
            }

            $lineLen = (int) hexdec($hexLen);

            if ($lineLen < 4 || $lineLen > 65520) {
                throw new \Pitmaster\Exceptions\ProtocolException("Invalid pkt-line length: {$hexLen}");
            }

            $payloadLen = $lineLen - 4;

            if ($offset + 4 + $payloadLen > $length) {
                throw new \Pitmaster\Exceptions\ProtocolException('Truncated pkt-line');
            }

            $line = rtrim(substr($advertisement, $offset + 4, $payloadLen), "\n");

            if ($line !== '' && !str_starts_with($line, '# service=')) {
                if (self::ingestLine($discovery, $line, $firstRef)) {
                    $firstRef = false;
                }
            }

            $offset += $lineLen;
        }

        return $discovery;
    }

    /**
     * @return array<string, ObjectId>
     */
    public function refs(): array
    {
        return $this->refs;
    }

    public function capabilities(): ?Capability
    {
        return $this->capabilities;
    }

    public function headSymref(): ?string
    {
        return $this->headSymref;
    }

    public function ref(string $name): ?ObjectId
    {
        return $this->refs[$name] ?? null;
    }

    private static function ingestLine(self $discovery, string $line, bool $allowCapabilities): bool
    {
        if ($allowCapabilities && str_contains($line, "\0")) {
            [$refPart, $capPart] = explode("\0", $line, 2);
            $discovery->capabilities = Capability::parse($capPart);
            $symref = $discovery->capabilities->get('symref');

            if ($symref !== null && str_starts_with($symref, 'HEAD:')) {
                $discovery->headSymref = substr($symref, 5);
            }

            $line = $refPart;
        }

        if (strlen($line) < 41) {
            return false;
        }

        $parts = explode(' ', $line, 2);

        if (
            count($parts) !== 2
            || !in_array(strlen($parts[0]), [40, 64], true)
            || !ctype_xdigit($parts[0])
        ) {
            return false;
        }

        $discovery->refs[$parts[1]] = ObjectId::fromHex($parts[0]);

        return true;
    }
}
