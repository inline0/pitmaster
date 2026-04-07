<?php

declare(strict_types=1);

namespace Pitmaster\Exceptions;

use RuntimeException;

final class IndexParseException extends RuntimeException
{
    public static function invalidMagic(string $path): self
    {
        return new self("Invalid index file magic bytes: {$path}");
    }

    public static function unsupportedVersion(int $version, string $path): self
    {
        return new self("Unsupported index version {$version} in {$path}");
    }

    public static function checksumMismatch(string $path): self
    {
        return new self("Index file checksum mismatch: {$path}");
    }
}
