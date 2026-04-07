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
     * Parse ref discovery response.
     *
     * @param array<int, mixed> $pktLines Decoded pkt-lines
     */
    public static function parse(array $pktLines): self
    {
        $discovery = new self();

        foreach ($pktLines as $i => $line) {
            if ($line === null || $line === false || !is_string($line)) {
                continue;
            }

            // First line may have capabilities after NUL
            if ($i === 0 && str_contains($line, "\0")) {
                [$refPart, $capPart] = explode("\0", $line, 2);
                $discovery->capabilities = Capability::parse($capPart);

                // Extract symref for HEAD
                $symref = $discovery->capabilities->get('symref');

                if ($symref !== null && str_starts_with($symref, 'HEAD:')) {
                    $discovery->headSymref = substr($symref, 5);
                }

                $line = $refPart;
            }

            // Parse "hash refname"
            if (strlen($line) >= 41) {
                $parts = explode(' ', $line, 2);

                if (count($parts) === 2 && strlen($parts[0]) === 40 && ctype_xdigit($parts[0])) {
                    $discovery->refs[$parts[1]] = ObjectId::fromHex($parts[0]);
                }
            }
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
}
