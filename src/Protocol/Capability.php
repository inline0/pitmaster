<?php

declare(strict_types=1);

namespace Pitmaster\Protocol;

/**
 * Protocol capability negotiation.
 *
 * Parses and manages capabilities advertised by git servers.
 */
final class Capability
{
    /** @var array<string, ?string> capability => value (null if no value) */
    private array $capabilities = [];

    /**
     * @param array<string, ?string> $capabilities
     */
    public static function fromArray(array $capabilities): self
    {
        $cap = new self();
        $cap->capabilities = $capabilities;

        return $cap;
    }

    /**
     * Parse capabilities from a NUL-delimited string (v1 format).
     * Format: "capability1 capability2=value capability3"
     */
    public static function parse(string $capString): self
    {
        $cap = new self();

        foreach (explode(' ', trim($capString)) as $item) {
            if ($item === '') {
                continue;
            }

            $eqPos = strpos($item, '=');

            if ($eqPos !== false) {
                $cap->capabilities[substr($item, 0, $eqPos)] = substr($item, $eqPos + 1);
            } else {
                $cap->capabilities[$item] = null;
            }
        }

        return $cap;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->capabilities);
    }

    public function get(string $name): ?string
    {
        return $this->capabilities[$name] ?? null;
    }

    /**
     * @return array<string, ?string>
     */
    public function all(): array
    {
        return $this->capabilities;
    }

    /**
     * Format capabilities for sending in a want line.
     */
    public function format(): string
    {
        $parts = [];

        foreach ($this->capabilities as $name => $value) {
            $parts[] = $value !== null ? "{$name}={$value}" : $name;
        }

        return implode(' ', $parts);
    }
}
