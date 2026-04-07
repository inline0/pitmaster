<?php

declare(strict_types=1);

namespace Pitmaster\Object;

/**
 * Immutable object identifier. SHA-1 (40 hex chars) or SHA-256 (64 hex chars).
 *
 * All object references flow through ObjectId, never raw strings.
 */
final readonly class ObjectId
{
    public string $hex;
    public string $binary;

    private function __construct(string $hex, string $binary)
    {
        $this->hex = $hex;
        $this->binary = $binary;
    }

    /**
     * Create from hex string (40 or 64 characters).
     */
    public static function fromHex(string $hex): self
    {
        $hex = strtolower($hex);

        return new self($hex, hex2bin($hex));
    }

    /**
     * Create from raw binary hash (20 or 32 bytes).
     */
    public static function fromBinary(string $binary): self
    {
        return new self(bin2hex($binary), $binary);
    }

    /**
     * Compute the object ID by hashing header + content.
     */
    public static function compute(ObjectType $type, string $content, string $algo = 'sha1'): self
    {
        $header = $type->value . ' ' . strlen($content) . "\0";
        $hex = hash($algo, $header . $content);

        return self::fromHex($hex);
    }

    /**
     * The first two hex characters (used for loose object directory).
     */
    public function prefix(): string
    {
        return substr($this->hex, 0, 2);
    }

    /**
     * The remaining hex characters after the prefix.
     */
    public function suffix(): string
    {
        return substr($this->hex, 2);
    }

    public function equals(self $other): bool
    {
        return $this->hex === $other->hex;
    }

    public function __toString(): string
    {
        return $this->hex;
    }
}
