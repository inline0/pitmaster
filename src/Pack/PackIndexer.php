<?php

declare(strict_types=1);

namespace Pitmaster\Pack;

use Pitmaster\Encoding\BinaryReader;
use Pitmaster\Encoding\VarInt;
use Pitmaster\Exceptions\PackParseException;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;
use RuntimeException;

/**
 * Builds a v2 .idx file for an existing pack file without shelling out to git.
 */
final class PackIndexer
{
    /**
     * Parse a pack file and write a matching .idx alongside it.
     */
    public static function writeIndex(string $packPath): string
    {
        $packData = file_get_contents($packPath);

        if ($packData === false) {
            throw PackParseException::truncated($packPath, 'unable to read pack file');
        }

        $scan = self::scanPack($packData, $packPath);
        $idxPath = substr($packPath, 0, -5) . '.idx';
        $idxData = self::buildIndex($scan['entries'], $scan['packChecksum']);

        if (file_put_contents($idxPath, $idxData) === false) {
            throw PackParseException::truncated($packPath, 'unable to write pack index');
        }

        return $idxPath;
    }

    /**
     * @return array{
     *   entries: array<int, array{hash: string, binary: string, offset: int, crc32: int}>,
     *   packChecksum: string
     * }
     */
    private static function scanPack(string $packData, string $packPath): array
    {
        try {
            $reader = new BinaryReader($packData);
            $magic = $reader->read(4);

            if ($magic !== 'PACK') {
                throw PackParseException::invalidMagic($packPath);
            }

            $version = $reader->readUint32();

            if ($version !== 2) {
                throw PackParseException::unsupportedVersion($version, $packPath);
            }

            $objectCount = $reader->readUint32();
            $packBodyLength = strlen($packData) - 20;
            $packChecksum = substr($packData, -20);
            $parsed = [];

            for ($i = 0; $i < $objectCount; $i++) {
                $entryOffset = $reader->position();

                if ($entryOffset >= $packBodyLength) {
                    throw PackParseException::truncated($packPath, "missing pack entry {$i}");
                }

                $entry = self::readEntryHeader($reader, $entryOffset);
                $dataOffset = $reader->position();
                [$rawData, $consumedBytes] = self::inflateEntry(
                    substr($packData, $dataOffset, $packBodyLength - $dataOffset),
                    $entry->uncompressedSize,
                    $packPath,
                );

                $reader->seek($dataOffset + $consumedBytes);

                $entryBytes = substr($packData, $entryOffset, ($dataOffset - $entryOffset) + $consumedBytes);
                $parsed[$entryOffset] = [
                    'entry' => $entry,
                    'data' => $rawData,
                    'crc32' => crc32($entryBytes) & 0xFFFFFFFF,
                ];
            }

            $resolved = [];
            $hashToOffset = [];

            foreach (array_keys($parsed) as $offset) {
                self::resolveObjectAtOffset($offset, $parsed, $resolved, $hashToOffset);
            }

            $entries = [];

            foreach ($resolved as $offset => $object) {
                $entries[] = [
                    'hash' => $object['id']->hex,
                    'binary' => $object['id']->binary,
                    'offset' => $offset,
                    'crc32' => $parsed[$offset]['crc32'],
                ];
            }

            return ['entries' => $entries, 'packChecksum' => $packChecksum];
        } catch (PackParseException $e) {
            throw $e;
        } catch (RuntimeException $e) {
            throw PackParseException::truncated($packPath, $e->getMessage());
        }
    }

    private static function readEntryHeader(BinaryReader $reader, int $offset): PackEntry
    {
        $byte = $reader->readByte();
        $packType = ($byte >> 4) & 0x07;
        $size = $byte & 0x0F;
        $shift = 4;

        while (($byte & 0x80) !== 0) {
            $byte = $reader->readByte();
            $size |= ($byte & 0x7F) << $shift;
            $shift += 7;
        }

        $baseOffset = null;
        $baseHash = null;

        if ($packType === PackEntry::TYPE_OFS_DELTA) {
            $baseOffset = VarInt::decodeOfsOffset($reader);
        } elseif ($packType === PackEntry::TYPE_REF_DELTA) {
            $baseHash = $reader->readHash20();
        }

        return new PackEntry(
            packType: $packType,
            uncompressedSize: $size,
            dataOffset: $reader->position(),
            entryOffset: $offset,
            baseOffset: $baseOffset,
            baseHash: $baseHash,
        );
    }

    /**
     * @return array{0: string, 1: int}
     */
    private static function inflateEntry(string $packedData, int $expectedSize, string $packPath): array
    {
        foreach ([ZLIB_ENCODING_RAW, ZLIB_ENCODING_DEFLATE] as $encoding) {
            $context = @inflate_init($encoding);

            if ($context === false) {
                continue;
            }

            $decoded = @inflate_add($context, $packedData, ZLIB_FINISH);

            if ($decoded === false) {
                continue;
            }

            $consumedBytes = inflate_get_read_len($context);

            if ($consumedBytes <= 0 || strlen($decoded) < $expectedSize) {
                continue;
            }

            return [substr($decoded, 0, $expectedSize), $consumedBytes];
        }

        throw PackParseException::truncated($packPath, 'zlib decompression failed');
    }

    /**
     * @param array<int, array{entry: PackEntry, data: string, crc32: int}> $parsed
     * @param array<int, array{type: ObjectType, content: string, id: ObjectId}> $resolved
     * @param array<string, int> $hashToOffset
     * @return array{type: ObjectType, content: string, id: ObjectId}
     */
    private static function resolveObjectAtOffset(
        int $offset,
        array $parsed,
        array &$resolved,
        array &$hashToOffset,
        int $depth = 0,
    ): array {
        $maxDepth = DeltaResolver::maxChainDepth();

        if ($depth > $maxDepth) {
            throw PackParseException::deltaChainTooDeep($depth, $maxDepth);
        }

        if (isset($resolved[$offset])) {
            return $resolved[$offset];
        }

        if (!isset($parsed[$offset])) {
            throw PackParseException::invalidDeltaBase("offset {$offset} not found in pack");
        }

        $entry = $parsed[$offset]['entry'];
        $data = $parsed[$offset]['data'];

        if (!$entry->isDelta()) {
            $type = $entry->objectType();

            if ($type === null) {
                throw PackParseException::invalidDeltaBase("invalid base type at offset {$offset}");
            }

            $content = $data;
        } elseif ($entry->isOfsDelta()) {
            $baseOffset = $entry->entryOffset - (int) $entry->baseOffset;
            $base = self::resolveObjectAtOffset($baseOffset, $parsed, $resolved, $hashToOffset, $depth + 1);
            $type = $base['type'];
            $content = DeltaApplier::apply($base['content'], $data);
        } else {
            $base = self::resolveRefDeltaBase($offset, (string) $entry->baseHash, $parsed, $resolved, $hashToOffset, $depth + 1);
            $type = $base['type'];
            $content = DeltaApplier::apply($base['content'], $data);
        }

        $id = ObjectId::compute($type, $content);
        $resolved[$offset] = ['type' => $type, 'content' => $content, 'id' => $id];
        $hashToOffset[$id->hex] = $offset;

        return $resolved[$offset];
    }

    /**
     * @param array<int, array{entry: PackEntry, data: string, crc32: int}> $parsed
     * @param array<int, array{type: ObjectType, content: string, id: ObjectId}> $resolved
     * @param array<string, int> $hashToOffset
     * @return array{type: ObjectType, content: string, id: ObjectId}
     */
    private static function resolveRefDeltaBase(
        int $currentOffset,
        string $baseHash,
        array $parsed,
        array &$resolved,
        array &$hashToOffset,
        int $depth,
    ): array {
        if (isset($hashToOffset[$baseHash])) {
            return self::resolveObjectAtOffset($hashToOffset[$baseHash], $parsed, $resolved, $hashToOffset, $depth);
        }

        foreach (array_keys($parsed) as $offset) {
            if ($offset === $currentOffset) {
                continue;
            }

            $candidate = self::resolveObjectAtOffset($offset, $parsed, $resolved, $hashToOffset, $depth);

            if ($candidate['id']->hex === $baseHash) {
                return $candidate;
            }
        }

        throw PackParseException::invalidDeltaBase("ref-delta base {$baseHash} not found in pack");
    }

    /**
     * @param array<int, array{hash: string, binary: string, offset: int, crc32: int}> $entries
     */
    private static function buildIndex(array $entries, string $packChecksum): string
    {
        usort($entries, fn (array $a, array $b): int => strcmp($a['hash'], $b['hash']));

        $index = "\xFF\x74\x4F\x63" . pack('N', 2);
        $fanout = array_fill(0, 256, 0);

        foreach ($entries as $entry) {
            $firstByte = (int) hexdec(substr($entry['hash'], 0, 2));

            for ($i = $firstByte; $i < 256; $i++) {
                $fanout[$i]++;
            }
        }

        foreach ($fanout as $count) {
            $index .= pack('N', $count);
        }

        foreach ($entries as $entry) {
            $index .= $entry['binary'];
        }

        foreach ($entries as $entry) {
            $index .= pack('N', $entry['crc32']);
        }

        foreach ($entries as $entry) {
            $index .= pack('N', $entry['offset']);
        }

        $index .= $packChecksum;
        $index .= sha1($index, true);

        return $index;
    }
}
