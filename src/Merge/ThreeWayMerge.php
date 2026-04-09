<?php

declare(strict_types=1);

namespace Pitmaster\Merge;

use Pitmaster\Diff\MyersDiff;

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

        $baseLines = MyersDiff::normalizeLines($base);
        $oursLines = MyersDiff::normalizeLines($ours);
        $theirsLines = MyersDiff::normalizeLines($theirs);

        $oursRegions = self::buildChangeRegions($baseLines, $oursLines);
        $theirsRegions = self::buildChangeRegions($baseLines, $theirsLines);

        [$result, $conflicts] = self::mergeRegions(
            $baseLines,
            $oursRegions,
            $theirsRegions,
            $oursLabel,
            $theirsLabel,
            $conflictStyle,
            $baseLabel,
        );

        $content = implode("\n", $result);

        if (
            $content !== ''
            && (str_ends_with($base, "\n") || str_ends_with($ours, "\n") || str_ends_with($theirs, "\n"))
            && !str_ends_with($content, "\n")
        ) {
            $content .= "\n";
        }

        return ['content' => $content, 'clean' => $conflicts === 0, 'conflicts' => $conflicts];
    }

    /**
     * @param array<int, string> $baseLines
     * @param array<int, string> $targetLines
     * @return array<int, array{start: int, end: int, lines: array<int, string>}>
     */
    private static function buildChangeRegions(array $baseLines, array $targetLines): array
    {
        $ops = MyersDiff::opsFromLines($baseLines, $targetLines);
        $regions = [];
        $baseIndex = 0;
        $opIndex = 0;
        $count = count($ops);

        while ($opIndex < $count) {
            if ($ops[$opIndex]['type'] === 'equal') {
                $baseIndex++;
                $opIndex++;
                continue;
            }

            $start = $baseIndex;
            $replacement = [];

            while ($opIndex < $count && $ops[$opIndex]['type'] !== 'equal') {
                if ($ops[$opIndex]['type'] === 'delete') {
                    $baseIndex++;
                } elseif ($ops[$opIndex]['type'] === 'insert') {
                    $replacement[] = $ops[$opIndex]['line'];
                }

                $opIndex++;
            }

            $regions[] = [
                'start' => $start,
                'end' => $baseIndex,
                'lines' => $replacement,
            ];
        }

        return $regions;
    }

    /**
     * @param array<int, string> $baseLines
     * @param array<int, array{start: int, end: int, lines: array<int, string>}> $oursRegions
     * @param array<int, array{start: int, end: int, lines: array<int, string>}> $theirsRegions
     * @return array{0: array<int, string>, 1: int}
     *
     */
    private static function mergeRegions(
        array $baseLines,
        array $oursRegions,
        array $theirsRegions,
        string $oursLabel,
        string $theirsLabel,
        string $conflictStyle,
        string $baseLabel,
    ): array {
        $result = [];
        $conflicts = 0;
        $baseIndex = 0;
        $oursIndex = 0;
        $theirsIndex = 0;
        $oursCount = count($oursRegions);
        $theirsCount = count($theirsRegions);

        while ($oursIndex < $oursCount || $theirsIndex < $theirsCount) {
            $nextOurs = $oursRegions[$oursIndex] ?? null;
            $nextTheirs = $theirsRegions[$theirsIndex] ?? null;
            $nextStart = min($nextOurs['start'] ?? PHP_INT_MAX, $nextTheirs['start'] ?? PHP_INT_MAX);

            if ($baseIndex < $nextStart) {
                foreach (array_slice($baseLines, $baseIndex, $nextStart - $baseIndex) as $line) {
                    $result[] = $line;
                }

                $baseIndex = $nextStart;
            }

            [$oursWindow, $theirsWindow, $windowEnd, $oursIndex, $theirsIndex] = self::collectWindow(
                $oursRegions,
                $theirsRegions,
                $oursIndex,
                $theirsIndex,
                $nextStart,
            );

            if ($oursWindow === []) {
                foreach (self::applyRegions($baseLines, $nextStart, $windowEnd, $theirsWindow) as $line) {
                    $result[] = $line;
                }

                $baseIndex = $windowEnd;
                continue;
            }

            if ($theirsWindow === []) {
                foreach (self::applyRegions($baseLines, $nextStart, $windowEnd, $oursWindow) as $line) {
                    $result[] = $line;
                }

                $baseIndex = $windowEnd;
                continue;
            }

            $baseChunk = array_slice($baseLines, $nextStart, $windowEnd - $nextStart);
            $oursChunk = self::applyRegions($baseLines, $nextStart, $windowEnd, $oursWindow);
            $theirsChunk = self::applyRegions($baseLines, $nextStart, $windowEnd, $theirsWindow);

            if ($oursChunk === $theirsChunk) {
                foreach ($oursChunk as $line) {
                    $result[] = $line;
                }

                $baseIndex = $windowEnd;
                continue;
            }

            if ($oursChunk === $baseChunk) {
                foreach ($theirsChunk as $line) {
                    $result[] = $line;
                }

                $baseIndex = $windowEnd;
                continue;
            }

            if ($theirsChunk === $baseChunk) {
                foreach ($oursChunk as $line) {
                    $result[] = $line;
                }

                $baseIndex = $windowEnd;
                continue;
            }

            $conflicts++;
            $result[] = rtrim(ConflictMarker::mark(
                implode("\n", $oursChunk),
                implode("\n", $theirsChunk),
                $oursLabel,
                $theirsLabel,
                $conflictStyle === 'diff3' ? implode("\n", $baseChunk) : null,
                $baseLabel,
            ), "\n");
            $baseIndex = $windowEnd;
        }

        if ($baseIndex < count($baseLines)) {
            foreach (array_slice($baseLines, $baseIndex) as $line) {
                $result[] = $line;
            }
        }

        return [$result, $conflicts];
    }

    /**
     * @param array<int, array{start: int, end: int, lines: array<int, string>}> $oursRegions
     * @param array<int, array{start: int, end: int, lines: array<int, string>}> $theirsRegions
     * @return array{
     *   0: array<int, array{start: int, end: int, lines: array<int, string>}>,
     *   1: array<int, array{start: int, end: int, lines: array<int, string>}>,
     *   2: int,
     *   3: int,
     *   4: int
     * }
     */
    private static function collectWindow(
        array $oursRegions,
        array $theirsRegions,
        int $oursIndex,
        int $theirsIndex,
        int $windowStart,
    ): array {
        $oursWindow = [];
        $theirsWindow = [];
        $windowEnd = $windowStart;
        $progress = true;

        while ($progress) {
            $progress = false;

            if (
                isset($oursRegions[$oursIndex])
                && self::regionTouchesWindow($oursRegions[$oursIndex], $windowStart, $windowEnd)
            ) {
                $oursWindow[] = $oursRegions[$oursIndex];
                $windowEnd = max($windowEnd, $oursRegions[$oursIndex]['end']);
                $oursIndex++;
                $progress = true;
            }

            if (
                isset($theirsRegions[$theirsIndex])
                && self::regionTouchesWindow($theirsRegions[$theirsIndex], $windowStart, $windowEnd)
            ) {
                $theirsWindow[] = $theirsRegions[$theirsIndex];
                $windowEnd = max($windowEnd, $theirsRegions[$theirsIndex]['end']);
                $theirsIndex++;
                $progress = true;
            }
        }

        return [$oursWindow, $theirsWindow, $windowEnd, $oursIndex, $theirsIndex];
    }

    /**
     * @param array{start: int, end: int, lines: array<int, string>} $region
     */
    private static function regionTouchesWindow(array $region, int $windowStart, int $windowEnd): bool
    {
        if ($windowStart === $windowEnd) {
            return $region['start'] === $windowStart;
        }

        return $region['start'] < $windowEnd;
    }

    /**
     * @param array<int, string> $baseLines
     * @param array<int, array{start: int, end: int, lines: array<int, string>}> $regions
     * @return array<int, string>
     */
    private static function applyRegions(array $baseLines, int $windowStart, int $windowEnd, array $regions): array
    {
        $result = [];
        $cursor = $windowStart;

        foreach ($regions as $region) {
            foreach (array_slice($baseLines, $cursor, $region['start'] - $cursor) as $line) {
                $result[] = $line;
            }

            foreach ($region['lines'] as $line) {
                $result[] = $line;
            }

            $cursor = $region['end'];
        }

        foreach (array_slice($baseLines, $cursor, $windowEnd - $cursor) as $line) {
            $result[] = $line;
        }

        return $result;
    }
}
