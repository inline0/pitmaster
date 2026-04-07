<?php

declare(strict_types=1);

namespace Pitmaster\Exceptions;

use RuntimeException;

final class ObjectNotFoundException extends RuntimeException
{
    public static function forHash(string $hash): self
    {
        return new self("Object not found: {$hash}");
    }
}
