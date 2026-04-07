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
    public string $algo;

    private function __construct(string $hex, string $binary, string $algo = 'sha1')
    {
        $this->hex = $hex;
        $this->binary = $binary;
        $this->algo = $algo;
    }

    /**
     * Create from hex string (40 chars for SHA-1, 64 chars for SHA-256).
     */
    public static function fromHex(string $hex): self
    {
        $hex = strtolower($hex);
        $algo = strlen($hex) === 64 ? 'sha256' : 'sha1';
        $binary = hex2bin($hex);

        if ($binary === false) {
            throw new \InvalidArgumentException("Invalid hex string for ObjectId: {$hex}");
        }

        return new self($hex, $binary, $algo);
    }

    /**
     * Create from raw binary hash (20 bytes for SHA-1, 32 bytes for SHA-256).
     */
    public static function fromBinary(string $binary): self
    {
        $algo = strlen($binary) === 32 ? 'sha256' : 'sha1';

        return new self(bin2hex($binary), $binary, $algo);
    }

    /**
     * Compute the object ID by hashing header + content.
     */
    public static function compute(ObjectType $type, string $content, string $algo = 'sha1'): self
    {
        $header = $type->value . ' ' . strlen($content) . "\0";
        $hex = hash($algo, $header . $content);

        return new self($hex, hex2bin($hex), $algo);
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

    /**
     * Hash length in bytes (20 for SHA-1, 32 for SHA-256).
     */
    public function hashLength(): int
    {
        return $this->algo === 'sha256' ? 32 : 20;
    }

    /**
     * Whether this is a SHA-256 hash.
     */
    public function isSha256(): bool
    {
        return $this->algo === 'sha256';
    }

    /**
     * Create a zero/null ObjectId (all zeros).
     */
    public static function zero(string $algo = 'sha1'): self
    {
        $len = $algo === 'sha256' ? 64 : 40;

        return self::fromHex(str_repeat('0', $len));
    }

    public function isZero(): bool
    {
        return $this->hex === str_repeat('0', strlen($this->hex));
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
