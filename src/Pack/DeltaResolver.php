<?php

declare(strict_types=1);

namespace Pitmaster\Pack;

/**
 * Resolves delta chains in pack files.
 *
 * This is a coordination layer; the actual delta application happens in DeltaApplier.
 * The chain-following logic lives in PackFile::resolveAtOffset().
 *
 * This class provides utilities for analyzing delta chains and verifying
 * chain depth limits.
 */
final class DeltaResolver
{
    public static function maxChainDepth(): int
    {
        if (!defined('PITMASTER_MAX_DELTA_CHAIN')) {
            return 50;
        }

        $value = constant('PITMASTER_MAX_DELTA_CHAIN');

        return is_int($value) ? $value : 50;
    }
}
