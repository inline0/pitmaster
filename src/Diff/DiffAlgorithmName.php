<?php

declare(strict_types=1);

namespace Pitmaster\Diff;

final class DiffAlgorithmName
{
    public static function normalize(string $algorithm): string
    {
        return match (strtolower(trim($algorithm))) {
            '', 'default', 'myers' => 'myers',
            'minimal' => 'minimal',
            'patience' => 'patience',
            'histogram' => 'histogram',
            default => throw new \InvalidArgumentException("Unknown diff algorithm: {$algorithm}"),
        };
    }
}
