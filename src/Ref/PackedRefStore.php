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
    /** @var array<string, ObjectId> */
    private array $refs = [];

    /** @var array<string, ObjectId> Peeled values for tag refs */
    private array $peeled = [];

    private bool $loaded = false;

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

    /**
     * Write all current refs to the packed-refs file.
     */
    public function write(): void
    {
        $this->ensureLoaded();

        $lines = ["# pack-refs with: peeled fully-peeled sorted \n"];

        ksort($this->refs);

        foreach ($this->refs as $name => $id) {
            $lines[] = "{$id->hex} {$name}\n";

            if (isset($this->peeled[$name])) {
                $lines[] = "^{$this->peeled[$name]->hex}\n";
            }
        }

        file_put_contents($this->gitDir . '/packed-refs', implode('', $lines));
    }

    /**
     * Add a ref to the packed refs (in memory; call write() to persist).
     */
    public function add(string $name, ObjectId $id): void
    {
        $this->ensureLoaded();
        $this->refs[$name] = $id;
    }

    /**
     * Set the peeled value for an annotated tag.
     */
    public function setPeeled(string $name, ObjectId $id): void
    {
        $this->ensureLoaded();
        $this->peeled[$name] = $id;
    }

    /**
     * Remove a ref from the packed-refs view.
     */
    public function remove(string $name): void
    {
        $this->ensureLoaded();
        unset($this->refs[$name], $this->peeled[$name]);
    }

    /**
     * Replace the full packed-refs contents in memory.
     *
     * @param array<string, ObjectId> $refs
     * @param array<string, ObjectId> $peeled
     */
    public function replace(array $refs, array $peeled = []): void
    {
        $this->refs = $refs;
        $this->peeled = $peeled;
        $this->loaded = true;
    }

    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loaded = true;
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

                if (ObjectId::looksLikeHex($hex)) {
                    $this->peeled[$lastRef] = ObjectId::fromHex($hex);
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

            if (ObjectId::looksLikeHex($hex)) {
                $this->refs[$refName] = ObjectId::fromHex($hex);
                $lastRef = $refName;
            }
        }
    }
}
