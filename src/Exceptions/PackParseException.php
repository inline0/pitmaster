<?php

declare(strict_types=1);

namespace Pitmaster\Exceptions;

use RuntimeException;

final class PackParseException extends RuntimeException
{
    public static function invalidMagic(string $path): self
    {
        return new self("Invalid pack file magic bytes: {$path}");
    }

    public static function unsupportedVersion(int $version, string $path): self
    {
        return new self("Unsupported pack version {$version} in {$path}");
    }

    public static function deltaChainTooDeep(int $depth, int $max): self
    {
        return new self("Delta chain depth {$depth} exceeds maximum {$max}");
    }

    public static function invalidDeltaBase(string $detail): self
    {
        return new self("Invalid delta base: {$detail}");
    }

    public static function truncated(string $path, string $detail): self
    {
        return new self("Truncated pack file {$path}: {$detail}");
    }
}
