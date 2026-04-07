<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

use Pitmaster\Object\ObjectId;

/**
 * Git bundle reader/writer.
 *
 * Bundles are files that contain refs and a pack, allowing offline
 * transport of git objects.
 *
 * Format (v2):
 *   # v2 git bundle\n
 *   -<prerequisite sha>\n  (zero or more)
 *   <sha> <refname>\n      (one or more)
 *   \n                     (blank line separator)
 *   <pack data>            (raw pack file)
 */
final class Bundle
{
    /** @var array<int, ObjectId> Prerequisites (commits the receiver must have) */
    private array $prerequisites = [];

    /** @var array<string, ObjectId> Refs in the bundle */
    private array $refs = [];

    private string $packData = '';

    /**
     * Read a bundle file.
     */
    public static function open(string $path): self
    {
        $data = file_get_contents($path);

        if ($data === false) {
            throw new \RuntimeException("Cannot read bundle: {$path}");
        }

        return self::parse($data);
    }

    public static function parse(string $data): self
    {
        $bundle = new self();

        // Find the header line
        $pos = strpos($data, "\n");

        if ($pos === false) {
            throw new \RuntimeException('Invalid bundle: no header');
        }

        $header = substr($data, 0, $pos);

        if ($header !== '# v2 git bundle' && $header !== '# v3 git bundle') {
            throw new \RuntimeException("Unsupported bundle format: {$header}");
        }

        $offset = $pos + 1;

        // Parse prerequisites and refs until blank line
        while ($offset < strlen($data)) {
            $lineEnd = strpos($data, "\n", $offset);

            if ($lineEnd === false) {
                break;
            }

            $line = substr($data, $offset, $lineEnd - $offset);
            $offset = $lineEnd + 1;

            if ($line === '') {
                break; // Blank line = end of header
            }

            if (str_starts_with($line, '-')) {
                // Prerequisite
                $hex = substr($line, 1, 40);

                if (strlen($hex) === 40 && ctype_xdigit($hex)) {
                    $bundle->prerequisites[] = ObjectId::fromHex($hex);
                }
            } else {
                // Ref: "<sha> <refname>"
                $parts = explode(' ', $line, 2);

                if (count($parts) === 2 && strlen($parts[0]) === 40 && ctype_xdigit($parts[0])) {
                    $bundle->refs[$parts[1]] = ObjectId::fromHex($parts[0]);
                }
            }
        }

        // Rest is pack data
        $bundle->packData = substr($data, $offset);

        return $bundle;
    }

    /**
     * Create a bundle from refs and pack data.
     */
    public static function create(array $refs, string $packData, array $prerequisites = []): self
    {
        $bundle = new self();
        $bundle->refs = $refs;
        $bundle->packData = $packData;
        $bundle->prerequisites = $prerequisites;

        return $bundle;
    }

    /**
     * Serialize bundle to bytes.
     */
    public function serialize(): string
    {
        $lines = ["# v2 git bundle"];

        foreach ($this->prerequisites as $prereq) {
            $lines[] = "-{$prereq->hex}";
        }

        foreach ($this->refs as $name => $id) {
            $lines[] = "{$id->hex} {$name}";
        }

        $lines[] = '';

        return implode("\n", $lines) . $this->packData;
    }

    /**
     * Write bundle to a file.
     */
    public function writeTo(string $path): void
    {
        file_put_contents($path, $this->serialize());
    }

    /** @return array<int, ObjectId> */
    public function prerequisites(): array
    {
        return $this->prerequisites;
    }

    /** @return array<string, ObjectId> */
    public function refs(): array
    {
        return $this->refs;
    }

    public function packData(): string
    {
        return $this->packData;
    }
}
