<?php

declare(strict_types=1);

namespace Pitmaster\Merge;

/**
 * Three-way merge algorithm operating on blob content.
 *
 * Takes base (common ancestor), ours (current branch), and theirs (incoming branch)
 * content and produces merged output. Detects conflicts when both sides modify
 * the same region differently.
 */
final class ThreeWayMerge
{
    /**
     * Merge three versions of content.
     *
     * @return array{content: string, clean: bool, conflicts: int}
     */
    public static function merge(
        string $base,
        string $ours,
        string $theirs,
        string $oursLabel = 'HEAD',
        string $theirsLabel = 'incoming',
        string $conflictStyle = 'merge',
        string $baseLabel = 'base',
    ): array {
        // Trivial cases
        if ($ours === $theirs) {
            return ['content' => $ours, 'clean' => true, 'conflicts' => 0];
        }

        if ($base === $ours) {
            return ['content' => $theirs, 'clean' => true, 'conflicts' => 0];
        }

        if ($base === $theirs) {
            return ['content' => $ours, 'clean' => true, 'conflicts' => 0];
        }

        // Line-level three-way merge
        $baseLines = $base !== '' ? explode("\n", $base) : [];
        $oursLines = $ours !== '' ? explode("\n", $ours) : [];
        $theirsLines = $theirs !== '' ? explode("\n", $theirs) : [];

        // Compute diffs from base to ours and base to theirs
        $oursOps = self::computeOps($baseLines, $oursLines);
        $theirsOps = self::computeOps($baseLines, $theirsLines);

        // Walk through base lines and merge changes
        $result = [];
        $conflicts = 0;
        $baseLen = count($baseLines);
        $oursIdx = 0;
        $theirsIdx = 0;
        $baseIdx = 0;

        while ($baseIdx < $baseLen || $oursIdx < count($oursLines) || $theirsIdx < count($theirsLines)) {
            $oursChanged = isset($oursOps[$baseIdx]) && $oursOps[$baseIdx] !== 'keep';
            $theirsChanged = isset($theirsOps[$baseIdx]) && $theirsOps[$baseIdx] !== 'keep';

            if (!$oursChanged && !$theirsChanged) {
                // Both unchanged, take base
                if ($baseIdx < $baseLen) {
                    $result[] = $baseLines[$baseIdx];
                }
                $baseIdx++;
                $oursIdx++;
                $theirsIdx++;
            } elseif ($oursChanged && $theirsChanged) {
                // Both changed: conflict
                $conflicts++;
                $oursChunk = [];
                $theirsChunk = [];

                if ($oursIdx < count($oursLines)) {
                    $oursChunk[] = $oursLines[$oursIdx];
                    $oursIdx++;
                }

                if ($theirsIdx < count($theirsLines)) {
                    $theirsChunk[] = $theirsLines[$theirsIdx];
                    $theirsIdx++;
                }

                $baseChunk = $baseIdx < $baseLen ? [$baseLines[$baseIdx]] : [];
                $result[] = rtrim(ConflictMarker::mark(
                    implode("\n", $oursChunk),
                    implode("\n", $theirsChunk),
                    $oursLabel,
                    $theirsLabel,
                    $conflictStyle === 'diff3' ? implode("\n", $baseChunk) : null,
                    $baseLabel,
                ), "\n");
                $baseIdx++;
            } elseif ($oursChanged && !$theirsChanged) {
                // Only ours changed, take ours
                if ($oursOps[$baseIdx] === 'delete') {
                    $baseIdx++;
                    $theirsIdx++;
                } else {
                    if ($oursIdx < count($oursLines)) {
                        $result[] = $oursLines[$oursIdx];
                    }
                    $oursIdx++;
                    $baseIdx++;
                    $theirsIdx++;
                }
            } elseif ($theirsChanged) {
                // Only theirs changed, take theirs
                if ($theirsOps[$baseIdx] === 'delete') {
                    $baseIdx++;
                    $oursIdx++;
                } else {
                    if ($theirsIdx < count($theirsLines)) {
                        $result[] = $theirsLines[$theirsIdx];
                    }
                    $theirsIdx++;
                    $baseIdx++;
                    $oursIdx++;
                }
            }
        }

        $content = implode("\n", $result);

        return ['content' => $content, 'clean' => $conflicts === 0, 'conflicts' => $conflicts];
    }

    /**
     * Compute simple line operations (keep/change/delete) from base to target.
     *
     * @param array<int, string> $base
     * @param array<int, string> $target
     * @return array<int, string>
     */
    private static function computeOps(array $base, array $target): array
    {
        $ops = [];
        $targetSet = array_flip($target);

        foreach ($base as $i => $line) {
            if (isset($target[$i]) && $target[$i] === $line) {
                $ops[$i] = 'keep';
            } elseif (!isset($target[$i])) {
                $ops[$i] = 'delete';
            } else {
                $ops[$i] = 'change';
            }
        }

        return $ops;
    }
}
