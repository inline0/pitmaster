<?php

declare(strict_types=1);

namespace Pitmaster\Ref;

use Pitmaster\Object\ObjectId;

/**
 * Reads refs from the packed-refs file.
 *
 * Format:
 *   # pack-refs with: peeled fully-peeled sorted
 *   <hex> <refname>
 *   ^<hex>             (peeled tag, optional, on line after tag ref)
 */
final class PackedRefStore implements RefStore
{
    /** @var array<string, ObjectId>|null */
    private ?array $refs = null;

    /** @var array<string, ObjectId> Peeled values for tag refs */
    private array $peeled = [];

    public function __construct(private readonly string $gitDir)
    {
    }

    public function resolve(string $name): ?ObjectId
    {
        $this->ensureLoaded();

        return $this->refs[$name] ?? null;
    }

    public function exists(string $name): bool
    {
        $this->ensureLoaded();

        return isset($this->refs[$name]);
    }

    /**
     * @return array<string, ObjectId>
     */
    public function list(): array
    {
        $this->ensureLoaded();

        return $this->refs;
    }

    /**
     * Get the peeled value for a tag ref.
     */
    public function peeled(string $name): ?ObjectId
    {
        $this->ensureLoaded();

        return $this->peeled[$name] ?? null;
    }

    private function ensureLoaded(): void
    {
        if ($this->refs !== null) {
            return;
        }

        $this->refs = [];
        $path = $this->gitDir . '/packed-refs';

        if (!is_file($path)) {
            return;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return;
        }

        $lastRef = null;

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            // Peeled line: ^<hex>
            if ($line[0] === '^' && $lastRef !== null) {
                $hex = substr($line, 1);

                if (strlen($hex) >= 40 && ctype_xdigit(substr($hex, 0, 40))) {
                    $this->peeled[$lastRef] = ObjectId::fromHex(substr($hex, 0, 40));
                }

                continue;
            }

            // Regular line: <hex> <refname>
            $spacePos = strpos($line, ' ');

            if ($spacePos === false) {
                continue;
            }

            $hex = substr($line, 0, $spacePos);
            $refName = substr($line, $spacePos + 1);

            if (strlen($hex) >= 40 && ctype_xdigit(substr($hex, 0, 40))) {
                $this->refs[$refName] = ObjectId::fromHex(substr($hex, 0, 40));
                $lastRef = $refName;
            }
        }
    }
}
