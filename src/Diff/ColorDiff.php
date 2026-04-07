<?php

declare(strict_types=1);

namespace Pitmaster\Diff;

/**
 * ANSI color formatting for diff output.
 */
final class ColorDiff
{
    private const RED = "\033[31m";
    private const GREEN = "\033[32m";
    private const CYAN = "\033[36m";
    private const BOLD = "\033[1m";
    private const RESET = "\033[0m";

    /**
     * Colorize a unified diff output string.
     */
    public static function colorize(string $diff): string
    {
        $lines = explode("\n", $diff);
        $result = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, '+++') || str_starts_with($line, '---')) {
                $result[] = self::BOLD . $line . self::RESET;
            } elseif (str_starts_with($line, '@@')) {
                $result[] = self::CYAN . $line . self::RESET;
            } elseif (str_starts_with($line, '+')) {
                $result[] = self::GREEN . $line . self::RESET;
            } elseif (str_starts_with($line, '-')) {
                $result[] = self::RED . $line . self::RESET;
            } elseif (str_starts_with($line, 'diff --git')) {
                $result[] = self::BOLD . $line . self::RESET;
            } else {
                $result[] = $line;
            }
        }

        return implode("\n", $result);
    }

    /**
     * Colorize DiffResult objects.
     *
     * @param array<int, DiffResult> $results
     */
    public static function formatResults(array $results): string
    {
        $output = '';

        foreach ($results as $result) {
            $output .= self::colorize($result->format());
        }

        return $output;
    }
}
