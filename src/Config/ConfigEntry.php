<?php

declare(strict_types=1);

namespace Pitmaster\Config;

/**
 * Single git config key/value pair.
 */
final readonly class ConfigEntry
{
    public function __construct(
        public string $section,
        public ?string $subsection,
        public string $key,
        public string $value,
    ) {
    }

    /**
     * Full qualified key: section.subsection.key or section.key.
     */
    public function qualifiedKey(): string
    {
        if ($this->subsection !== null) {
            return "{$this->section}.{$this->subsection}.{$this->key}";
        }

        return "{$this->section}.{$this->key}";
    }
}
