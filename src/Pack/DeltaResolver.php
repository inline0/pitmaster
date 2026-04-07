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
        return defined('PITMASTER_MAX_DELTA_CHAIN')
            ? (int) constant('PITMASTER_MAX_DELTA_CHAIN')
            : 50;
    }
}
