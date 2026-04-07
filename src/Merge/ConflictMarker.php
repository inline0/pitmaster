<?php

declare(strict_types=1);

namespace Pitmaster\Merge;

/**
 * Generate conflict markers for merge conflicts.
 */
final class ConflictMarker
{
    /**
     * Wrap conflicting content with standard git conflict markers.
     */
    public static function mark(
        string $ours,
        string $theirs,
        string $oursLabel = 'HEAD',
        string $theirsLabel = 'incoming',
    ): string {
        return "<<<<<<< {$oursLabel}\n"
            . $ours
            . "=======\n"
            . $theirs
            . ">>>>>>> {$theirsLabel}\n";
    }
}
