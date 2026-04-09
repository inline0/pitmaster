<?php

declare(strict_types=1);

namespace Pitmaster\Encoding;

use RuntimeException;

/**
 * Low-level byte stream with position tracking.
 *
 * Wraps a string buffer and provides typed read operations for parsing
 * Git binary formats (pack files, index files, objects).
 */
final class BinaryReader
{
    private int $position = 0;
    private int $length;

    public function __construct(private readonly string $data)
    {
        $this->length = strlen($data);
    }

    public static function fromFile(string $path): self
    {
        $data = file_get_contents($path);

        if ($data === false) {
            throw new RuntimeException("Unable to read file: {$path}");
        }

        return new self($data);
    }

    public function position(): int
    {
        return $this->position;
    }

    public function length(): int
    {
        return $this->length;
    }

    public function remaining(): int
    {
        return $this->length - $this->position;
    }

    public function isEof(): bool
    {
        return $this->position >= $this->length;
    }

    public function seek(int $position): void
    {
        if ($position < 0 || $position > $this->length) {
            throw new RuntimeException("Seek position {$position} out of bounds (0..{$this->length})");
        }

        $this->position = $position;
    }

    public function skip(int $bytes): void
    {
        $this->seek($this->position + $bytes);
    }

    public function read(int $bytes): string
    {
        if ($bytes < 0) {
            throw new RuntimeException("Cannot read negative bytes: {$bytes}");
        }

        if ($this->position + $bytes > $this->length) {
            throw new RuntimeException(
                "Cannot read {$bytes} bytes at position {$this->position} (length {$this->length})"
            );
        }

        $data = substr($this->data, $this->position, $bytes);
        $this->position += $bytes;

        return $data;
    }

    public function readByte(): int
    {
        return ord($this->read(1));
    }

    /**
     * Read a 4-byte big-endian unsigned integer.
     */
    public function readUint32(): int
    {
        $data = $this->read(4);
        $unpacked = unpack('N', $data);

        return (int) $unpacked[1];
    }

    /**
     * Read a 3-byte big-endian unsigned integer.
     */
    public function readUint24(): int
    {
        $data = $this->read(3);

        return (ord($data[0]) << 16) | (ord($data[1]) << 8) | ord($data[2]);
    }

    /**
     * Read an 8-byte big-endian unsigned integer.
     */
    public function readUint64(): int
    {
        $data = $this->read(8);
        $parts = unpack('Nhigh/Nlow', $data);

        return ((int) $parts['high'] << 32) | (int) $parts['low'];
    }

    /**
     * Read a 2-byte big-endian unsigned integer.
     */
    public function readUint16(): int
    {
        $data = $this->read(2);
        $unpacked = unpack('n', $data);

        return (int) $unpacked[1];
    }

    /**
     * Read bytes until a null byte is found. Returns the string before the null.
     * The null byte is consumed but not included in the result.
     */
    public function readNullTerminated(): string
    {
        $nullPos = strpos($this->data, "\0", $this->position);

        if ($nullPos === false) {
            throw new RuntimeException("No null terminator found from position {$this->position}");
        }

        $str = substr($this->data, $this->position, $nullPos - $this->position);
        $this->position = $nullPos + 1;

        return $str;
    }

    /**
     * Read a raw binary hash and return as hex.
     */
    public function readHash(int $bytes): string
    {
        return bin2hex($this->read($bytes));
    }

    /**
     * Read a raw binary SHA-1 hash (20 bytes) and return as hex.
     */
    public function readHash20(): string
    {
        return $this->readHash(20);
    }

    /**
     * Read a raw binary SHA-256 hash (32 bytes) and return as hex.
     */
    public function readHash32(): string
    {
        return $this->readHash(32);
    }

    /**
     * Peek at bytes without advancing the position.
     */
    public function peek(int $bytes): string
    {
        if ($this->position + $bytes > $this->length) {
            throw new RuntimeException(
                "Cannot peek {$bytes} bytes at position {$this->position} (length {$this->length})"
            );
        }

        return substr($this->data, $this->position, $bytes);
    }

    /**
     * Return the raw underlying data.
     */
    public function rawData(): string
    {
        return $this->data;
    }

    /**
     * Return remaining data from current position without advancing.
     */
    public function remainingData(): string
    {
        return substr($this->data, $this->position);
    }
}
