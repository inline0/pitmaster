<?php

declare(strict_types=1);

namespace Pitmaster\Pack;

use Pitmaster\Object\GitObject;
use Pitmaster\Object\ObjectId;
use Pitmaster\Storage\ObjectSerializer;

/**
 * Pack file writer.
 *
 * Creates .pack and .idx files from a set of objects.
 * Format: PACK header + zlib-compressed entries + SHA-1 trailer.
 */
final class PackWriter
{
    /**
     * Write objects into a pack file + index.
     *
     * @param array<int, GitObject> $objects
     * @return array{packPath: string, idxPath: string, hash: string}
     */
    public static function write(string $packDir, array $objects): array
    {
        if (!is_dir($packDir)) {
            mkdir($packDir, 0777, true);
        }

        $packData = self::buildPack($objects);
        $hash = sha1($packData);
        $packPath = $packDir . "/pack-{$hash}.pack";
        $idxPath = $packDir . "/pack-{$hash}.idx";

        file_put_contents($packPath, $packData);

        $idxData = self::buildIndex($objects, $packData);
        file_put_contents($idxPath, $idxData);

        return ['packPath' => $packPath, 'idxPath' => $idxPath, 'hash' => $hash];
    }

    /**
     * Build the raw pack file data.
     */
    private static function buildPack(array $objects): string
    {
        // Header: PACK + version(2) + object count
        $header = 'PACK' . pack('N', 2) . pack('N', count($objects));
        $body = '';

        foreach ($objects as $object) {
            $raw = $object->content;
            $type = $object->type->toPackType();
            $size = strlen($raw);

            // Type+size header
            $byte = ($type << 4) | ($size & 0x0F);
            $size >>= 4;

            while ($size > 0) {
                $body .= chr($byte | 0x80);
                $byte = $size & 0x7F;
                $size >>= 7;
            }

            $body .= chr($byte);

            // Zlib-compressed content
            $body .= zlib_encode($raw, ZLIB_ENCODING_DEFLATE);
        }

        $content = $header . $body;

        // Trailer: SHA-1 of everything
        $content .= sha1($content, true);

        return $content;
    }

    /**
     * Build a v2 pack index.
     */
    private static function buildIndex(array $objects, string $packData): string
    {
        // Collect entries: hash, offset, crc32
        $entries = [];
        $offset = 12; // Skip PACK header (4+4+4)

        foreach ($objects as $object) {
            $hash = $object->id->hex;
            $entries[] = [
                'hash' => $hash,
                'binary' => $object->id->binary,
                'offset' => $offset,
                'crc32' => 0,
            ];

            // Calculate entry size to find next offset
            $raw = $object->content;
            $type = $object->type->toPackType();
            $size = strlen($raw);

            // Header size
            $headerSize = 1;
            $s = $size >> 4;

            while ($s > 0) {
                $headerSize++;
                $s >>= 7;
            }

            $compressed = zlib_encode($raw, ZLIB_ENCODING_DEFLATE);
            $offset += $headerSize + strlen($compressed);
        }

        // Sort by hash
        usort($entries, fn ($a, $b) => strcmp($a['hash'], $b['hash']));

        // Build index
        $idx = '';

        // Magic + version
        $idx .= "\xFF\x74\x4F\x63";
        $idx .= pack('N', 2);

        // Fanout table
        $fanout = array_fill(0, 256, 0);

        foreach ($entries as $entry) {
            $firstByte = (int) hexdec(substr($entry['hash'], 0, 2));

            for ($i = $firstByte; $i < 256; $i++) {
                $fanout[$i]++;
            }
        }

        foreach ($fanout as $count) {
            $idx .= pack('N', $count);
        }

        // SHA-1 names (sorted)
        foreach ($entries as $entry) {
            $idx .= $entry['binary'];
        }

        // CRC32 values
        foreach ($entries as $entry) {
            $idx .= pack('N', $entry['crc32']);
        }

        // 4-byte offsets
        foreach ($entries as $entry) {
            $idx .= pack('N', $entry['offset']);
        }

        // Pack checksum
        $idx .= substr($packData, -20);

        // Index checksum
        $idx .= sha1($idx, true);

        return $idx;
    }
}
