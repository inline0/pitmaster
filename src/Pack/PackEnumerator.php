<?php

declare(strict_types=1);

namespace Pitmaster\Pack;

use Pitmaster\Object\GitObject;

/**
 * Iterates all objects in a pack file.
 */
final class PackEnumerator
{
    public function __construct(private readonly PackFile $pack)
    {
    }

    /**
     * Yield all objects in the pack.
     *
     * @return iterable<GitObject>
     */
    public function enumerate(): iterable
    {
        $entries = $this->pack->index()->allEntries();

        foreach ($entries as $hex => $offset) {
            yield $this->pack->readAtOffset($offset, $hex);
        }
    }

    /**
     * Get the count of objects in the pack.
     */
    public function count(): int
    {
        return $this->pack->objectCount();
    }
}
