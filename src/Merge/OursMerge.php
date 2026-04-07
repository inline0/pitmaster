<?php

declare(strict_types=1);

namespace Pitmaster\Merge;

/**
 * "Ours" merge strategy: takes all content from the current branch.
 */
final class OursMerge
{
    /**
     * @return array{content: string, clean: bool, conflicts: int}
     */
    public static function merge(string $base, string $ours, string $theirs): array
    {
        return ['content' => $ours, 'clean' => true, 'conflicts' => 0];
    }
}
