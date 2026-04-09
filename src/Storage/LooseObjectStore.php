<?php

declare(strict_types=1);

namespace Pitmaster\Storage;

use Pitmaster\Object\GitObject;
use Pitmaster\Object\ObjectId;

/**
 * Reads and writes loose objects from .git/objects/XX/YYYY...
 *
 * Loose objects are individually zlib-compressed files stored at
 * objects/<first 2 hex chars>/<remaining 38 hex chars>.
 */
final class LooseObjectStore implements ObjectStore
{
    public function __construct(private readonly string $objectsDir)
    {
    }

    public function read(ObjectId $id): ?GitObject
    {
        $path = $this->objectPath($id);

        if (!is_file($path)) {
            return null;
        }

        $compressed = file_get_contents($path);

        if ($compressed === false) {
            return null;
        }

        return ObjectSerializer::decode($compressed, $id->hex);
    }

    public function exists(ObjectId $id): bool
    {
        return is_file($this->objectPath($id));
    }

    public function write(GitObject $object): ObjectId
    {
        $path = $this->objectPath($object->id);

        if (is_file($path)) {
            return $object->id;
        }

        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $encoded = ObjectSerializer::encode($object);

        // Atomic write: write to temp, then rename.
        $tmp = $path . '.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, $encoded);
        rename($tmp, $path);

        return $object->id;
    }

    /**
     * Write an already-encoded loose object exactly as served by Git.
     */
    public function writeEncoded(ObjectId $id, string $encoded): ObjectId
    {
        $path = $this->objectPath($id);

        if (is_file($path)) {
            return $id;
        }

        $dir = dirname($path);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $tmp = $path . '.' . bin2hex(random_bytes(4));
        file_put_contents($tmp, $encoded);
        rename($tmp, $path);

        return $id;
    }

    /**
     * List all loose object hashes.
     *
     * @return array<int, string> Hex hashes
     */
    public function listAll(): array
    {
        $hashes = [];

        if (!is_dir($this->objectsDir)) {
            return $hashes;
        }

        foreach (scandir($this->objectsDir) as $prefix) {
            if (strlen($prefix) !== 2 || !ctype_xdigit($prefix)) {
                continue;
            }

            $prefixDir = $this->objectsDir . '/' . $prefix;

            if (!is_dir($prefixDir)) {
                continue;
            }

            foreach (scandir($prefixDir) as $suffix) {
                if ($suffix === '.' || $suffix === '..') {
                    continue;
                }

                if (strlen($suffix) === 38 && ctype_xdigit($suffix)) {
                    $hashes[] = $prefix . $suffix;
                }
            }
        }

        return $hashes;
    }

    private function objectPath(ObjectId $id): string
    {
        return $this->objectsDir . '/' . $id->prefix() . '/' . $id->suffix();
    }
}
