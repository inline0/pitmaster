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
        ?string $base = null,
        string $baseLabel = 'base',
    ): string {
        $result = "<<<<<<< {$oursLabel}\n" . self::ensureTrailingNewline($ours);

        if ($base !== null) {
            $result .= "||||||| {$baseLabel}\n" . self::ensureTrailingNewline($base);
        }

        $result .= "=======\n"
            . self::ensureTrailingNewline($theirs)
            . ">>>>>>> {$theirsLabel}\n";

        return $result;
    }

    private static function ensureTrailingNewline(string $content): string
    {
        if ($content === '') {
            return '';
        }

        return str_ends_with($content, "\n") ? $content : $content . "\n";
    }
}
