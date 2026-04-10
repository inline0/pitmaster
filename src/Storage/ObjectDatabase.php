<?php

declare(strict_types=1);

namespace Pitmaster\Storage;

use Pitmaster\Object\GitObject;
use Pitmaster\Object\ObjectId;

/**
 * Composite object store: tries loose first, then packs.
 *
 * Writes always go to loose. This follows git's pattern: new objects
 * are written loose, then git gc repacks them later.
 */
final class ObjectDatabase implements ObjectStore
{
    private readonly LooseObjectStore $loose;
    private readonly PackFileStore $packs;

    public function __construct(string $objectsDir)
    {
        $this->loose = new LooseObjectStore($objectsDir);
        $this->packs = new PackFileStore($objectsDir . '/pack');
    }

    public function read(ObjectId $id): ?GitObject
    {
        // Try loose first (faster for recently written objects)
        $object = $this->loose->read($id);

        if ($object !== null) {
            return $object;
        }

        return $this->packs->read($id);
    }

    public function exists(ObjectId $id): bool
    {
        return $this->loose->exists($id) || $this->packs->exists($id);
    }

    public function write(GitObject $object): ObjectId
    {
        return $this->loose->write($object);
    }

    /**
     * List all object hashes (loose + packed).
     *
     * @return array<int, string>
     */
    public function listAll(): array
    {
        return array_values(array_unique(
            array_merge($this->loose->listAll(), $this->packs->listAll())
        ));
    }

    public function looseStore(): LooseObjectStore
    {
        return $this->loose;
    }

    public function packStore(): PackFileStore
    {
        return $this->packs;
    }

    public function refresh(): void
    {
        $this->packs->refresh();
    }
}
