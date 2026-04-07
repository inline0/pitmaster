<?php

declare(strict_types=1);

namespace Pitmaster\Ref;

use Pitmaster\Object\ObjectId;

/**
 * Interface for resolving and listing refs.
 */
interface RefStore
{
    /**
     * Resolve a ref name to an ObjectId.
     */
    public function resolve(string $name): ?ObjectId;

    /**
     * List all refs as name => ObjectId pairs.
     *
     * @return array<string, ObjectId>
     */
    public function list(): array;

    /**
     * Check if a ref exists.
     */
    public function exists(string $name): bool;
}
