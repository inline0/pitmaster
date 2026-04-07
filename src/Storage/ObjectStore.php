<?php

declare(strict_types=1);

namespace Pitmaster\Storage;

use Pitmaster\Object\GitObject;
use Pitmaster\Object\ObjectId;

/**
 * Interface for reading and writing git objects.
 */
interface ObjectStore
{
    public function read(ObjectId $id): ?GitObject;

    public function exists(ObjectId $id): bool;

    public function write(GitObject $object): ObjectId;
}
