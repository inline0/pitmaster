<?php

declare(strict_types=1);

namespace Pitmaster\Bench;

final class BenchmarkStats
{
    /**
     * @param list<int|float> $values
     * @return array<string, int|float|list<int|float>>
     */
    public static function summarize(array $values): array
    {
        if ($values === []) {
            return [
                'median' => 0,
                'min' => 0,
                'max' => 0,
                'raw' => [],
            ];
        }

        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);
        $median = $count % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];

        return [
            'median' => is_float($median) ? round($median, 3) : $median,
            'min' => $values[0],
            'max' => $values[$count - 1],
            'raw' => array_values($values),
        ];
    }
}
