<?php

declare(strict_types=1);

namespace Pitmaster\Storage;

use Pitmaster\Exceptions\CorruptObjectException;
use Pitmaster\Object\Blob;
use Pitmaster\Object\Commit;
use Pitmaster\Object\GitObject;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;
use Pitmaster\Object\Tag;
use Pitmaster\Object\Tree;

/**
 * Encodes and decodes the git object wire format.
 *
 * Format: <type> <size>\0<content>
 * Storage: zlib_encode(header + content)
 * Hash: the repository object hash over header + content computed on uncompressed data
 */
final class ObjectSerializer
{
    /**
     * Encode an object to its storable format (zlib compressed).
     */
    public static function encode(GitObject $object): string
    {
        $raw = self::encodeRaw($object);

        return zlib_encode($raw, ZLIB_ENCODING_DEFLATE);
    }

    /**
     * Encode an object to its raw format (uncompressed header + content).
     */
    public static function encodeRaw(GitObject $object): string
    {
        return $object->type->value . ' ' . strlen($object->content) . "\0" . $object->content;
    }

    /**
     * Decode a stored object (zlib compressed) into a typed GitObject.
     */
    public static function decode(string $compressed, ?string $expectedHash = null): GitObject
    {
        $raw = @zlib_decode($compressed);

        if ($raw === false) {
            throw CorruptObjectException::invalidHeader(
                $expectedHash ?? 'unknown',
                'zlib decompression failed'
            );
        }

        return self::decodeRaw($raw, $expectedHash);
    }

    /**
     * Decode raw (uncompressed) object data into a typed GitObject.
     */
    public static function decodeRaw(string $raw, ?string $expectedHash = null): GitObject
    {
        $nullPos = strpos($raw, "\0");

        if ($nullPos === false) {
            throw CorruptObjectException::invalidHeader(
                $expectedHash ?? 'unknown',
                'missing null byte in header'
            );
        }

        $header = substr($raw, 0, $nullPos);
        $content = substr($raw, $nullPos + 1);

        $spacePos = strpos($header, ' ');

        if ($spacePos === false) {
            throw CorruptObjectException::invalidHeader(
                $expectedHash ?? 'unknown',
                'missing space in header'
            );
        }

        $typeStr = substr($header, 0, $spacePos);
        $size = (int) substr($header, $spacePos + 1);

        $type = ObjectType::tryFrom($typeStr);

        if ($type === null) {
            throw CorruptObjectException::invalidHeader(
                $expectedHash ?? 'unknown',
                "unknown type: {$typeStr}"
            );
        }

        if (strlen($content) !== $size) {
            throw CorruptObjectException::invalidHeader(
                $expectedHash ?? 'unknown',
                "size mismatch: header says {$size}, content is " . strlen($content)
            );
        }

        $algo = $expectedHash !== null ? ObjectId::fromHex($expectedHash)->algo : 'sha1';
        $id = ObjectId::compute($type, $content, $algo);

        if ($expectedHash !== null && $id->hex !== $expectedHash) {
            throw CorruptObjectException::hashMismatch($expectedHash, $id->hex);
        }

        return self::parseTyped($type, $content, $id);
    }

    /**
     * Parse raw content into a typed object, given the type and pre-computed ID.
     */
    public static function parseTyped(ObjectType $type, string $content, ObjectId $id): GitObject
    {
        return match ($type) {
            ObjectType::Blob => new Blob($content, $id),
            ObjectType::Tree => Tree::parse($content, $id),
            ObjectType::Commit => Commit::parse($content, $id),
            ObjectType::Tag => Tag::parse($content, $id),
        };
    }
}
