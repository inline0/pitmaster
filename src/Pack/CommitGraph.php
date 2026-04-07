<?php

declare(strict_types=1);

namespace Pitmaster\Pack;

use Pitmaster\Encoding\BinaryReader;

/**
 * Commit-graph file reader.
 *
 * Stores commit metadata (parents, tree, generation, timestamp) in a
 * compact binary format for faster graph traversal without parsing
 * individual commit objects.
 *
 * Format:
 *   Header: CGPH signature + version + hash version + chunk count + 0
 *   Chunk lookup table
 *   Chunks: OID fanout, OID lookup, commit data, extra edges
 */
final class CommitGraph
{
    private const MAGIC = "CGPH";

    /** @var array<int, int> */
    private array $fanout = [];

    /** @var array<int, string> Commit hashes (sorted) */
    private array $oids = [];

    /** @var array<int, array{tree: string, parent1: int, parent2: int, generation: int, timestamp: int}> */
    private array $commitData = [];

    private int $objectCount = 0;

    public static function open(string $path): ?self
    {
        if (!is_file($path)) {
            return null;
        }

        $reader = BinaryReader::fromFile($path);

        return self::parse($reader);
    }

    public static function parse(BinaryReader $reader): self
    {
        $graph = new self();

        $magic = $reader->read(4);

        if ($magic !== self::MAGIC) {
            return $graph;
        }

        $version = $reader->readByte();
        $hashVersion = $reader->readByte();
        $chunkCount = $reader->readByte();
        $_ = $reader->readByte(); // base graph count

        // Chunk lookup
        $chunks = [];

        for ($i = 0; $i < $chunkCount; $i++) {
            $chunkId = $reader->readUint32();
            $chunkOffset = $reader->readUint32();
            $reader->readUint32(); // upper offset
            $chunks[] = ['id' => $chunkId, 'offset' => $chunkOffset];
        }

        // Parse chunks
        foreach ($chunks as $chunk) {
            $reader->seek($chunk['offset']);

            switch ($chunk['id']) {
                case 0x4F494446: // OIDF - OID fanout
                    for ($i = 0; $i < 256; $i++) {
                        $graph->fanout[$i] = $reader->readUint32();
                    }
                    $graph->objectCount = $graph->fanout[255];
                    break;

                case 0x4F49444C: // OIDL - OID lookup
                    for ($i = 0; $i < $graph->objectCount; $i++) {
                        $graph->oids[$i] = $reader->readHash20();
                    }
                    break;

                case 0x43444154: // CDAT - Commit data
                    for ($i = 0; $i < $graph->objectCount; $i++) {
                        $treeHash = $reader->readHash20();
                        $parent1 = $reader->readUint32();
                        $parent2 = $reader->readUint32();
                        $genAndTs = $reader->readUint32();
                        $timestamp = $reader->readUint32();

                        $generation = ($genAndTs >> 2) & 0x3FFFFFFF;

                        $graph->commitData[$i] = [
                            'tree' => $treeHash,
                            'parent1' => $parent1,
                            'parent2' => $parent2,
                            'generation' => $generation,
                            'timestamp' => $timestamp,
                        ];
                    }
                    break;
            }
        }

        return $graph;
    }

    public function objectCount(): int
    {
        return $this->objectCount;
    }

    /**
     * Look up commit data by hash.
     *
     * @return array{tree: string, parent1: int, parent2: int, generation: int, timestamp: int}|null
     */
    public function lookup(string $hex): ?array
    {
        $firstByte = (int) hexdec(substr($hex, 0, 2));
        $lo = $firstByte > 0 ? ($this->fanout[$firstByte - 1] ?? 0) : 0;
        $hi = ($this->fanout[$firstByte] ?? 0) - 1;

        while ($lo <= $hi) {
            $mid = (int) (($lo + $hi) / 2);
            $cmp = strcmp($hex, $this->oids[$mid] ?? '');

            if ($cmp === 0) {
                return $this->commitData[$mid] ?? null;
            }

            if ($cmp < 0) {
                $hi = $mid - 1;
            } else {
                $lo = $mid + 1;
            }
        }

        return null;
    }
}
