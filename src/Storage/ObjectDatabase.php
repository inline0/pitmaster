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
    private const DEFAULT_OBJECT_CACHE_ENTRIES = 1024;
    private const DEFAULT_OBJECT_CACHE_BYTES = 16777216;

    private readonly LooseObjectStore $loose;
    private readonly PackFileStore $packs;
    /** @var array<string, array{object: GitObject, bytes: int}> */
    private array $objectCache = [];
    /** @var list<string> */
    private array $objectCacheOrder = [];
    private int $objectCacheBytes = 0;

    public function __construct(string $objectsDir)
    {
        $this->loose = new LooseObjectStore($objectsDir);
        $this->packs = new PackFileStore($objectsDir . '/pack');
    }

    public function read(ObjectId $id): ?GitObject
    {
        if (isset($this->objectCache[$id->hex])) {
            $this->touchObjectCache($id->hex);

            return $this->objectCache[$id->hex]['object'];
        }

        // Try loose first (faster for recently written objects)
        $object = $this->loose->read($id);

        if ($object !== null) {
            $this->cacheObject($object);

            return $object;
        }

        $object = $this->packs->read($id);

        if ($object !== null) {
            $this->cacheObject($object);
        }

        return $object;
    }

    public function exists(ObjectId $id): bool
    {
        return $this->loose->exists($id) || $this->packs->exists($id);
    }

    public function write(GitObject $object): ObjectId
    {
        $id = $this->loose->write($object);
        $this->cacheObject($object);

        return $id;
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
        $this->objectCache = [];
        $this->objectCacheOrder = [];
        $this->objectCacheBytes = 0;
    }

    private function cacheObject(GitObject $object): void
    {
        $bytes = $object->size();
        $maxBytes = $this->maxObjectCacheBytes();

        if ($bytes > $maxBytes) {
            return;
        }

        if (isset($this->objectCache[$object->id->hex])) {
            $this->objectCacheBytes -= $this->objectCache[$object->id->hex]['bytes'];
            $this->removeObjectCacheOrder($object->id->hex);
        }

        $this->objectCache[$object->id->hex] = ['object' => $object, 'bytes' => $bytes];
        $this->objectCacheOrder[] = $object->id->hex;
        $this->objectCacheBytes += $bytes;
        $this->pruneObjectCache();
    }

    private function touchObjectCache(string $hex): void
    {
        $this->removeObjectCacheOrder($hex);
        $this->objectCacheOrder[] = $hex;
    }

    private function pruneObjectCache(): void
    {
        $maxEntries = $this->maxObjectCacheEntries();
        $maxBytes = $this->maxObjectCacheBytes();

        while (
            ($maxEntries > 0 && count($this->objectCacheOrder) > $maxEntries)
            || ($maxBytes > 0 && $this->objectCacheBytes > $maxBytes)
        ) {
            $oldest = array_shift($this->objectCacheOrder);

            if ($oldest === null || !isset($this->objectCache[$oldest])) {
                continue;
            }

            $this->objectCacheBytes -= $this->objectCache[$oldest]['bytes'];
            unset($this->objectCache[$oldest]);
        }
    }

    private function removeObjectCacheOrder(string $hex): void
    {
        $this->objectCacheOrder = array_values(array_filter(
            $this->objectCacheOrder,
            static fn (string $cachedHex): bool => $cachedHex !== $hex,
        ));
    }

    private function maxObjectCacheEntries(): int
    {
        return $this->configuredPositiveInt('PITMASTER_OBJECT_CACHE_ENTRIES', self::DEFAULT_OBJECT_CACHE_ENTRIES);
    }

    private function maxObjectCacheBytes(): int
    {
        if (!defined('PITMASTER_OBJECT_CACHE_BYTES')) {
            return self::DEFAULT_OBJECT_CACHE_BYTES;
        }

        return $this->configuredBytes('PITMASTER_OBJECT_CACHE_BYTES', self::DEFAULT_OBJECT_CACHE_BYTES);
    }

    private function configuredPositiveInt(string $constant, int $default): int
    {
        if (!defined($constant)) {
            return $default;
        }

        $value = constant($constant);

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return $default;
    }

    private function configuredBytes(string $constant, int $default): int
    {
        $value = constant($constant);

        return is_int($value) || is_string($value)
            ? $this->parseBytes((string) $value, $default)
            : $default;
    }

    private function parseBytes(string $value, int $default): int
    {
        $value = trim($value);

        if ($value === '') {
            return $default;
        }

        if (ctype_digit($value)) {
            return max(1, (int) $value);
        }

        if (!preg_match('/^(\d+)([kmg])$/i', $value, $matches)) {
            return $default;
        }

        $bytes = (int) $matches[1];
        $unit = strtolower($matches[2]);

        if ($unit === 'k') {
            return $bytes * 1024;
        }

        if ($unit === 'm') {
            return $bytes * 1024 * 1024;
        }

        if ($unit === 'g') {
            return $bytes * 1024 * 1024 * 1024;
        }

        return $default;
    }
}
