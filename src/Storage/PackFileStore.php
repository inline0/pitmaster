<?php

declare(strict_types=1);

namespace Pitmaster\Storage;

use Pitmaster\Object\GitObject;
use Pitmaster\Object\ObjectId;
use Pitmaster\Pack\PackFile;

/**
 * Reads objects from pack files in .git/objects/pack/.
 *
 * Discovers all .pack/.idx pairs and searches each for requested objects.
 */
final class PackFileStore implements ObjectStore
{
    /** @var array<int, PackFile>|null */
    private ?array $packs = null;

    public function __construct(private readonly string $packDir)
    {
    }

    public function read(ObjectId $id): ?GitObject
    {
        foreach ($this->packs() as $pack) {
            $object = $pack->read($id->hex);

            if ($object !== null) {
                return $object;
            }
        }

        return null;
    }

    public function exists(ObjectId $id): bool
    {
        foreach ($this->packs() as $pack) {
            if ($pack->has($id->hex)) {
                return true;
            }
        }

        return false;
    }

    public function write(GitObject $object): ObjectId
    {
        // Pack file writing is deferred per CLAUDE.md: "write loose objects and let git gc repack"
        throw new \RuntimeException('Pack file writing is not supported. Write to loose object store.');
    }

    /**
     * Drop the cached pack list so newly written packs become visible.
     */
    public function refresh(): void
    {
        $this->packs = null;
    }

    /**
     * List all object hashes across all packs.
     *
     * @return array<int, string>
     */
    public function listAll(): array
    {
        $hashes = [];

        foreach ($this->packs() as $pack) {
            foreach ($pack->allHashes() as $hash) {
                $hashes[] = $hash;
            }
        }

        return array_unique($hashes);
    }

    /**
     * @return array<int, PackFile>
     */
    private function packs(): array
    {
        if ($this->packs !== null) {
            return $this->packs;
        }

        $this->packs = [];

        if (!is_dir($this->packDir)) {
            return $this->packs;
        }

        foreach (scandir($this->packDir) as $file) {
            if (!str_ends_with($file, '.pack')) {
                continue;
            }

            $packPath = $this->packDir . '/' . $file;
            $idxPath = substr($packPath, 0, -5) . '.idx';

            if (!is_file($idxPath)) {
                continue;
            }

            $this->packs[] = PackFile::open($packPath, $idxPath);
        }

        return $this->packs;
    }
}
