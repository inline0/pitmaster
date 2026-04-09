<?php

declare(strict_types=1);

namespace Pitmaster\Diff;

/**
 * Word-level diff.
 *
 * Instead of diffing by lines, splits content into words and diffs those.
 * Output uses {+added+} and [-removed-] markers inline.
 */
final class WordDiff
{
    /**
     * Produce a word-level diff between two strings.
     *
     * @return string Annotated text with {+...+} and [-...-] markers
     */
    public static function diff(string $old, string $new): string
    {
        if ($old === $new) {
            return $old;
        }

        $oldWords = self::tokenize($old);
        $newWords = self::tokenize($new);

        // Use LCS to find common subsequence
        $lcs = self::lcs($oldWords, $newWords);

        $result = '';
        $oi = 0;
        $ni = 0;
        $li = 0;

        while ($oi < count($oldWords) || $ni < count($newWords)) {
            if (
                $li < count($lcs) && $oi < count($oldWords) && $ni < count($newWords)
                && $oldWords[$oi] === $lcs[$li] && $newWords[$ni] === $lcs[$li]
            ) {
                // Common word
                $result .= $oldWords[$oi];
                $oi++;
                $ni++;
                $li++;
            } elseif ($li < count($lcs) && $oi < count($oldWords) && $oldWords[$oi] !== ($lcs[$li] ?? null)) {
                // Deleted word
                $result .= '[-' . $oldWords[$oi] . '-]';
                $oi++;
            } elseif ($li < count($lcs) && $ni < count($newWords) && $newWords[$ni] !== ($lcs[$li] ?? null)) {
                // Added word
                $result .= '{+' . $newWords[$ni] . '+}';
                $ni++;
            } elseif ($oi < count($oldWords)) {
                $result .= '[-' . $oldWords[$oi] . '-]';
                $oi++;
            } elseif ($ni < count($newWords)) {
                $result .= '{+' . $newWords[$ni] . '+}';
                $ni++;
            } else {
                break;
            }
        }

        return rtrim($result, "\n");
    }

    /**
     * Split text into word tokens (preserving whitespace as separate tokens).
     *
     * @return array<int, string>
     */
    private static function tokenize(string $text): array
    {
        $tokens = preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        return $tokens !== false ? $tokens : [$text];
    }

    /**
     * Find longest common subsequence of two word arrays.
     *
     * @param array<int, string> $a
     * @param array<int, string> $b
     * @return array<int, string>
     */
    private static function lcs(array $a, array $b): array
    {
        $m = count($a);
        $n = count($b);
        $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                if ($a[$i - 1] === $b[$j - 1]) {
                    $dp[$i][$j] = $dp[$i - 1][$j - 1] + 1;
                } else {
                    $dp[$i][$j] = max($dp[$i - 1][$j], $dp[$i][$j - 1]);
                }
            }
        }

        // Backtrace
        $result = [];
        $i = $m;
        $j = $n;

        while ($i > 0 && $j > 0) {
            if ($a[$i - 1] === $b[$j - 1]) {
                array_unshift($result, $a[$i - 1]);
                $i--;
                $j--;
            } elseif ($dp[$i - 1][$j] > $dp[$i][$j - 1]) {
                $i--;
            } else {
                $j--;
            }
        }

        return $result;
    }
}
