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
    private const FAST_INFLATE_WINDOW_BYTES = 8192;
    private const INITIAL_INFLATE_CHUNK_BYTES = 4096;
    private const MAX_INFLATE_CHUNK_BYTES = 65536;
    private const DEFAULT_RESOLVED_CACHE_ENTRIES = 4096;
    private const DEFAULT_RESOLVED_CACHE_BYTES = 33554432;

    private BinaryReader $reader;
    private int $objectCount;
    private PackIndex $index;
    private int $hashBytes = 20;
    /** @var array<int, array{type: ObjectType, content: string, bytes: int}> */
    private array $resolvedCache = [];
    /** @var list<int> */
    private array $resolvedCacheOrder = [];
    private int $resolvedCacheBytes = 0;
    /** @var array<int, int> */
    private array $inflateCountsByOffset = [];
    private int $maxCompressedChunkReadBytes = 0;

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

        if (isset($this->resolvedCache[$offset])) {
            $cached = $this->resolvedCache[$offset];

            return ['type' => $cached['type'], 'content' => $cached['content']];
        }

        $entry = $this->readEntryHeader($offset);

        if (!$entry->isDelta()) {
            $type = $entry->objectType();

            if ($type === null) {
                throw PackParseException::invalidDeltaBase("invalid base type at offset {$offset}");
            }

            $content = $this->readCompressedData($entry->dataOffset, $entry->uncompressedSize);

            return $this->cacheResolvedObject($offset, $type, $content);
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

        return $this->cacheResolvedObject($offset, $base['type'], $content);
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
        $decompressed = $this->decodeBoundedWindow($offset, $uncompressedSize);

        if ($decompressed !== null) {
            $this->inflateCountsByOffset[$offset] = ($this->inflateCountsByOffset[$offset] ?? 0) + 1;

            return $decompressed;
        }

        foreach ([ZLIB_ENCODING_DEFLATE, ZLIB_ENCODING_RAW] as $encoding) {
            $decompressed = $this->inflateFromOffset($offset, $uncompressedSize, $encoding);

            if ($decompressed !== null) {
                $this->inflateCountsByOffset[$offset] = ($this->inflateCountsByOffset[$offset] ?? 0) + 1;

                return $decompressed;
            }
        }

        throw PackParseException::truncated($this->path, "zlib decompression failed at offset {$offset}");
    }

    private function decodeBoundedWindow(int $offset, int $uncompressedSize): ?string
    {
        $this->reader->seek($offset);
        $windowBytes = min(self::FAST_INFLATE_WINDOW_BYTES, $this->reader->remaining());
        $window = $this->reader->read($windowBytes);
        $this->maxCompressedChunkReadBytes = max($this->maxCompressedChunkReadBytes, strlen($window));
        $decompressed = @zlib_decode($window, $uncompressedSize);

        if ($decompressed !== false && strlen($decompressed) === $uncompressedSize) {
            return $decompressed;
        }

        $decompressed = @zlib_decode($window);

        if ($decompressed !== false && strlen($decompressed) === $uncompressedSize) {
            return $decompressed;
        }

        return null;
    }

    private function inflateFromOffset(int $offset, int $uncompressedSize, int $encoding): ?string
    {
        $context = @inflate_init($encoding);

        if ($context === false) {
            return null;
        }

        $this->reader->seek($offset);
        $decompressed = '';
        $chunkBytes = self::INITIAL_INFLATE_CHUNK_BYTES;

        while (!$this->reader->isEof()) {
            $readBytes = min($chunkBytes, $this->reader->remaining());
            $chunk = $this->reader->read($readBytes);
            $this->maxCompressedChunkReadBytes = max($this->maxCompressedChunkReadBytes, strlen($chunk));

            $decoded = @inflate_add($context, $chunk, ZLIB_NO_FLUSH);

            if ($decoded === false) {
                return null;
            }

            $decompressed .= $decoded;

            if (strlen($decompressed) > $uncompressedSize) {
                return null;
            }

            if (inflate_get_status($context) === ZLIB_STREAM_END) {
                return strlen($decompressed) === $uncompressedSize ? $decompressed : null;
            }

            if ($chunkBytes < self::MAX_INFLATE_CHUNK_BYTES) {
                $chunkBytes = min($chunkBytes * 2, self::MAX_INFLATE_CHUNK_BYTES);
            }
        }

        $decoded = @inflate_add($context, '', ZLIB_FINISH);

        if ($decoded === false) {
            return null;
        }

        $decompressed .= $decoded;

        return strlen($decompressed) === $uncompressedSize ? $decompressed : null;
    }

    /**
     * @return array{type: ObjectType, content: string}
     */
    private function cacheResolvedObject(int $offset, ObjectType $type, string $content): array
    {
        $result = ['type' => $type, 'content' => $content];
        $bytes = strlen($content);
        $maxBytes = $this->maxResolvedCacheBytes();

        if ($bytes > $maxBytes) {
            return $result;
        }

        if (isset($this->resolvedCache[$offset])) {
            $this->resolvedCacheBytes -= $this->resolvedCache[$offset]['bytes'];
            $this->removeResolvedCacheOrder($offset);
        }

        $this->resolvedCache[$offset] = ['type' => $type, 'content' => $content, 'bytes' => $bytes];
        $this->resolvedCacheOrder[] = $offset;
        $this->resolvedCacheBytes += $bytes;
        $this->pruneResolvedCache();

        return $result;
    }

    private function pruneResolvedCache(): void
    {
        $maxEntries = $this->maxResolvedCacheEntries();
        $maxBytes = $this->maxResolvedCacheBytes();

        while (
            ($maxEntries > 0 && count($this->resolvedCacheOrder) > $maxEntries)
            || ($maxBytes > 0 && $this->resolvedCacheBytes > $maxBytes)
        ) {
            $oldest = array_shift($this->resolvedCacheOrder);

            if ($oldest === null || !isset($this->resolvedCache[$oldest])) {
                continue;
            }

            $this->resolvedCacheBytes -= $this->resolvedCache[$oldest]['bytes'];
            unset($this->resolvedCache[$oldest]);
        }
    }

    private function removeResolvedCacheOrder(int $offset): void
    {
        $this->resolvedCacheOrder = array_values(array_filter(
            $this->resolvedCacheOrder,
            static fn (int $cachedOffset): bool => $cachedOffset !== $offset,
        ));
    }

    private function maxResolvedCacheEntries(): int
    {
        return $this->configuredPositiveInt('PITMASTER_PACK_RESOLVED_CACHE_ENTRIES', self::DEFAULT_RESOLVED_CACHE_ENTRIES);
    }

    private function maxResolvedCacheBytes(): int
    {
        if (defined('PITMASTER_PACK_RESOLVED_CACHE_BYTES')) {
            return $this->configuredBytes('PITMASTER_PACK_RESOLVED_CACHE_BYTES', self::DEFAULT_RESOLVED_CACHE_BYTES);
        }

        if (defined('PITMASTER_MAX_PACK_MEMORY')) {
            return $this->configuredBytes('PITMASTER_MAX_PACK_MEMORY', self::DEFAULT_RESOLVED_CACHE_BYTES);
        }

        return self::DEFAULT_RESOLVED_CACHE_BYTES;
    }

    private function configuredPositiveInt(string $constant, int $default): int
    {
        if (!defined($constant)) {
            return $default;
        }

        $value = constant($constant);

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
            return (int) $value;
        }

        return $default;
    }

    private function configuredBytes(string $constant, int $default): int
    {
        $value = constant($constant);

        return is_int($value) || is_string($value)
            ? $this->parseBytes((string) $value, $default)
            : $default;
    }

    private function parseBytes(string $value, int $default): int
    {
        $value = trim($value);

        if ($value === '') {
            return $default;
        }

        if (ctype_digit($value)) {
            return max(1, (int) $value);
        }

        if (!preg_match('/^(\d+)([kmg])$/i', $value, $matches)) {
            return $default;
        }

        $bytes = (int) $matches[1];
        $unit = strtolower($matches[2]);

        if ($unit === 'k') {
            return $bytes * 1024;
        }

        if ($unit === 'm') {
            return $bytes * 1024 * 1024;
        }

        if ($unit === 'g') {
            return $bytes * 1024 * 1024 * 1024;
        }

        return $default;
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
