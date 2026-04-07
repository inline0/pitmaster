<?php

declare(strict_types=1);

namespace Pitmaster\Merge;

use Pitmaster\Object\ObjectId;

/**
 * Octopus merge: merge 3+ branches simultaneously.
 *
 * Refuses if any conflicts arise (octopus merge must be clean).
 */
final class OctopusMerge
{
    /**
     * Check if an octopus merge of multiple branches would be clean.
     *
     * @param array<int, string> $contents Array of content from each branch
     * @return array{content: string, clean: bool}
     */
    public static function merge(string $base, array $contents): array
    {
        $result = $base;

        foreach ($contents as $theirs) {
            $merged = ThreeWayMerge::merge($result, $result, $theirs);

            if (!$merged['clean']) {
                return ['content' => $result, 'clean' => false];
            }

            $result = $merged['content'];
        }

        return ['content' => $result, 'clean' => true];
    }
}
