<?php

declare(strict_types=1);

namespace Pitmaster\Exceptions;

use RuntimeException;

final class CorruptObjectException extends RuntimeException
{
    public static function invalidHeader(string $hash, string $detail = ''): self
    {
        $message = "Corrupt object header: {$hash}";

        if ($detail !== '') {
            $message .= " ({$detail})";
        }

        return new self($message);
    }

    public static function hashMismatch(string $expected, string $actual): self
    {
        return new self("Object hash mismatch: expected {$expected}, got {$actual}");
    }

    public static function invalidContent(string $hash, string $detail): self
    {
        return new self("Corrupt object content [{$hash}]: {$detail}");
    }
}
