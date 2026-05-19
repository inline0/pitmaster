<?php

declare(strict_types=1);

namespace Pitmaster\Pack;

use Pitmaster\Object\GitObject;
use Pitmaster\Storage\ObjectSerializer;
use RuntimeException;

/**
 * Pack file writer. Creates .pack and .idx files from a set of objects.
 */
final class PackWriter
{
    /**
     * @param array<int, GitObject> $objects
     */
    public static function encode(array $objects): string
    {
        return self::buildPack($objects)['data'];
    }

    /**
     * @param array<int, GitObject> $objects
     * @return array{packPath: string, idxPath: string, hash: string}
     */
    public static function write(string $packDir, array $objects): array
    {
        if (!is_dir($packDir)) {
            mkdir($packDir, 0777, true);
        }

        // Build pack, tracking offsets as we go
        $packResult = self::buildPack($objects);
        $packData = $packResult['data'];
        $entries = $packResult['entries'];

        $packChecksum = substr($packData, -20);
        $hash = bin2hex($packChecksum);

        $packPath = $packDir . "/pack-{$hash}.pack";
        $idxPath = $packDir . "/pack-{$hash}.idx";

        file_put_contents($packPath, $packData);

        $idxData = self::buildIndex($entries, $packChecksum);
        file_put_contents($idxPath, $idxData);

        return ['packPath' => $packPath, 'idxPath' => $idxPath, 'hash' => $hash];
    }

    /**
     * @param array<int, GitObject> $objects
     * @return array{data: string, entries: array<int, array{hash: string, binary: string, offset: int, crc32: int}>}
     */
    private static function buildPack(array $objects): array
    {
        $header = 'PACK' . pack('N', 2) . pack('N', count($objects));
        $body = '';
        $entries = [];
        $offset = 12; // header size

        foreach ($objects as $object) {
            $entryStart = strlen($body);
            $raw = $object->content;
            $type = $object->type->toPackType();
            $size = strlen($raw);

            // Type+size varint header
            $byte = ($type << 4) | ($size & 0x0F);
            $size >>= 4;
            $entryHeader = '';

            while ($size > 0) {
                $entryHeader .= chr(($byte | 0x80) & 0xFF);
                $byte = $size & 0x7F;
                $size >>= 7;
            }

            $entryHeader .= chr($byte & 0xFF);
            $compressed = zlib_encode($raw, ZLIB_ENCODING_DEFLATE);

            if ($compressed === false) {
                throw new RuntimeException("Failed to zlib-encode pack entry for {$object->id->hex}");
            }

            $entryData = $entryHeader . $compressed;

            $crc = crc32($entryData);

            $entries[] = [
                'hash' => $object->id->hex,
                'binary' => $object->id->binary,
                'offset' => $offset,
                'crc32' => $crc,
            ];

            $body .= $entryData;
            $offset += strlen($entryData);
        }

        $content = $header . $body;
        $content .= sha1($content, true);

        return ['data' => $content, 'entries' => $entries];
    }

    /**
     * @param array<int, array{hash: string, binary: string, offset: int, crc32: int}> $entries
     */
    private static function buildIndex(array $entries, string $packChecksum): string
    {
        // Sort by hash
        usort($entries, fn ($a, $b) => strcmp($a['hash'], $b['hash']));

        $idx = '';

        // Magic + version
        $idx .= "\xFF\x74\x4F\x63";
        $idx .= pack('N', 2);

        // Fanout table (cumulative count of objects with first byte <= i)
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

        // SHA-1 names
        foreach ($entries as $entry) {
            $idx .= $entry['binary'];
        }

        // CRC32 values
        foreach ($entries as $entry) {
            $idx .= pack('N', $entry['crc32'] & 0xFFFFFFFF);
        }

        // 4-byte offsets
        foreach ($entries as $entry) {
            $idx .= pack('N', $entry['offset']);
        }

        // Pack checksum (last 20 bytes of pack file = SHA-1 of pack content)
        $idx .= $packChecksum;

        // Index checksum
        $idx .= sha1($idx, true);

        return $idx;
    }
}
