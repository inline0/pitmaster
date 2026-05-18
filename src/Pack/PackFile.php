<?php

declare(strict_types=1);

namespace Pitmaster\Pack;

use Pitmaster\Encoding\BinaryReader;
use Pitmaster\Encoding\VarInt;
use Pitmaster\Exceptions\PackParseException;
use Pitmaster\Object\GitObject;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;
use Pitmaster\Storage\ObjectSerializer;

/**
 * Single .pack file reader.
 *
 * Pack format:
 *   Header: PACK (4 bytes) + version (4 bytes) + object count (4 bytes)
 *   Entries: [type+size varint] [delta-base info if delta] [zlib-compressed data]
 *   Trailer: 20-byte SHA-1 checksum
 *
 * Type encoding in first byte: 1TTTSSSS
 *   TTT = type (1=commit, 2=tree, 3=blob, 4=tag, 6=ofs_delta, 7=ref_delta)
 *   SSSS = first 4 bits of uncompressed size
 *   MSB set = more size bytes follow
 */
final class PackFile
{
    private const MAGIC = 'PACK';
    private const SUPPORTED_VERSION = 2;

    private BinaryReader $reader;
    private int $objectCount;
    private PackIndex $index;
    private int $hashBytes = 20;

    private function __construct(
        private readonly string $path,
    ) {
    }

    public static function open(string $packPath, string $indexPath): self
    {
        $pack = new self($packPath);
        $pack->reader = BinaryReader::fromFile($packPath);
        $pack->index = PackIndex::open($indexPath);
        $pack->hashBytes = $pack->index->hashBytes();
        $pack->parseHeader();

        return $pack;
    }

    public function objectCount(): int
    {
        return $this->objectCount;
    }

    public function index(): PackIndex
    {
        return $this->index;
    }

    /**
     * Check if this pack contains an object.
     */
    public function has(string $hex): bool
    {
        return $this->index->findOffset($hex) !== null;
    }

    /**
     * Read an object from this pack by hash.
     */
    public function read(string $hex): ?GitObject
    {
        $offset = $this->index->findOffset($hex);

        if ($offset === null) {
            return null;
        }

        return $this->readAtOffset($offset, $hex);
    }

    /**
     * Read an object at a specific pack offset, resolving delta chains.
     */
    public function readAtOffset(int $offset, ?string $expectedHash = null): GitObject
    {
        $resolved = $this->resolveAtOffset($offset, 0);
        $type = $resolved['type'];
        $content = $resolved['content'];

        $algo = $this->hashBytes === 32 ? 'sha256' : 'sha1';
        $id = $expectedHash !== null
            ? ObjectId::fromHex($expectedHash)
            : ObjectId::compute($type, $content, $algo);

        return ObjectSerializer::parseTyped($type, $content, $id);
    }

    /**
     * Resolve an object at offset, following delta chains.
     *
     * @return array{type: ObjectType, content: string}
     */
    private function resolveAtOffset(int $offset, int $depth): array
    {
        $maxDepth = DeltaResolver::maxChainDepth();

        if ($depth > $maxDepth) {
            throw PackParseException::deltaChainTooDeep($depth, $maxDepth);
        }

        $entry = $this->readEntryHeader($offset);

        if (!$entry->isDelta()) {
            $type = $entry->objectType();

            if ($type === null) {
                throw PackParseException::invalidDeltaBase("invalid base type at offset {$offset}");
            }

            $content = $this->readCompressedData($entry->dataOffset, $entry->uncompressedSize);

            return ['type' => $type, 'content' => $content];
        }

        if ($entry->isOfsDelta()) {
            if ($entry->baseOffset === null) {
                throw PackParseException::invalidDeltaBase("ofs-delta missing baseOffset at {$offset}");
            }

            $baseOffset = $entry->entryOffset - $entry->baseOffset;
            $base = $this->resolveAtOffset($baseOffset, $depth + 1);
        } else {
            if ($entry->baseHash === null) {
                throw PackParseException::invalidDeltaBase("ref-delta missing baseHash at {$offset}");
            }

            $basePackOffset = $this->index->findOffset($entry->baseHash);

            if ($basePackOffset === null) {
                throw PackParseException::invalidDeltaBase(
                    "ref-delta base {$entry->baseHash} not found in pack"
                );
            }

            $base = $this->resolveAtOffset($basePackOffset, $depth + 1);
        }

        $delta = $this->readCompressedData($entry->dataOffset, $entry->uncompressedSize);
        $content = DeltaApplier::apply($base['content'], $delta);

        return ['type' => $base['type'], 'content' => $content];
    }

    /**
     * Read and parse a pack entry header at the given offset.
     */
    private function readEntryHeader(int $offset): PackEntry
    {
        $this->reader->seek($offset);

        $byte = $this->reader->readByte();
        $packType = ($byte >> 4) & 0x07;
        $size = $byte & 0x0F;
        $shift = 4;

        while ($byte & 0x80) {
            $byte = $this->reader->readByte();
            $size |= ($byte & 0x7F) << $shift;
            $shift += 7;
        }

        $baseOffset = null;
        $baseHash = null;

        if ($packType === PackEntry::TYPE_OFS_DELTA) {
            $baseOffset = VarInt::decodeOfsOffset($this->reader);
        } elseif ($packType === PackEntry::TYPE_REF_DELTA) {
            $baseHash = $this->hashBytes === 32
                ? $this->reader->readHash32()
                : $this->reader->readHash20();
        }

        $dataOffset = $this->reader->position();

        return new PackEntry(
            packType: $packType,
            uncompressedSize: $size,
            dataOffset: $dataOffset,
            entryOffset: $offset,
            baseOffset: $baseOffset,
            baseHash: $baseHash,
        );
    }

    /**
     * Read zlib-compressed data at offset and decompress to expected size.
     *
     * Uses incremental inflate to handle the fact that we don't know the
     * compressed size upfront. Feeds data in chunks until we get the
     * expected uncompressed size.
     */
    private function readCompressedData(int $offset, int $uncompressedSize): string
    {
        $this->reader->seek($offset);
        $remaining = $this->reader->remainingData();

        // Try raw deflate first (most common in pack files)
        $context = @inflate_init(ZLIB_ENCODING_RAW);

        if ($context === false) {
            throw PackParseException::truncated($this->path, "inflate_init failed at offset {$offset}");
        }

        $decompressed = @inflate_add($context, $remaining, ZLIB_FINISH);

        if ($decompressed !== false && strlen($decompressed) === $uncompressedSize) {
            return $decompressed;
        }

        // Fall back to zlib_decode with various strategies
        $decompressed = @zlib_decode($remaining, $uncompressedSize);

        if ($decompressed !== false && strlen($decompressed) === $uncompressedSize) {
            return $decompressed;
        }

        // Try without size limit
        $decompressed = @zlib_decode($remaining);

        if ($decompressed !== false) {
            if (strlen($decompressed) >= $uncompressedSize) {
                return substr($decompressed, 0, $uncompressedSize);
            }

            return $decompressed;
        }

        throw PackParseException::truncated($this->path, "zlib decompression failed at offset {$offset}");
    }

    private function parseHeader(): void
    {
        $this->reader->seek(0);

        $magic = $this->reader->read(4);

        if ($magic !== self::MAGIC) {
            throw PackParseException::invalidMagic($this->path);
        }

        $version = $this->reader->readUint32();

        if ($version !== self::SUPPORTED_VERSION) {
            throw PackParseException::unsupportedVersion($version, $this->path);
        }

        $this->objectCount = $this->reader->readUint32();
    }

    /**
     * List all object hashes in this pack.
     *
     * @return array<int, string>
     */
    public function allHashes(): array
    {
        return $this->index->allHashes();
    }
}
