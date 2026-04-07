<?php

declare(strict_types=1);

namespace Pitmaster\Submodule;

use Pitmaster\Object\ObjectId;

/**
 * Represents a single submodule entry.
 */
final readonly class Submodule
{
    public function __construct(
        public string $name,
        public string $path,
        public string $url,
        public ?string $branch = null,
    ) {
    }
}
