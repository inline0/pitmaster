<?php

declare(strict_types=1);

namespace Pitmaster\Ref;

use Pitmaster\Encoding\BinaryReader;
use Pitmaster\Encoding\VarInt;
use Pitmaster\Object\ObjectId;

/**
 * Reftable format reader.
 *
 * Reftable is a newer binary format for storing refs, replacing loose + packed refs.
 * Structure: header + blocks (restart-compressed ref entries) + footer.
 *
 * Header: "REFT" + version (1 byte) + block_size (3 bytes)
 * Each block: sorted ref entries with restart points for binary search.
 * Footer: stats + offsets + checksum.
 */
final class Reftable
{
    private const MAGIC = "REFT";

    /** @var array<string, ObjectId> */
    private array $refs = [];
    /** @var array<string, string> */
    private array $symrefs = [];

    private int $blockSize = 4096;
    private int $hashBytes = 20;

    public static function open(string $path): ?self
    {
        if (!is_file($path)) {
            return null;
        }

        $data = file_get_contents($path);

        if ($data === false || strlen($data) < 28) {
            return null;
        }

        $reader = new BinaryReader($data);

        return self::parse($reader);
    }

    public static function parse(BinaryReader $reader): self
    {
        $reftable = new self();
        $fileLength = $reader->length();

        $magic = $reader->read(4);

        if ($magic !== self::MAGIC) {
            return $reftable;
        }

        $version = $reader->readByte();
        $headerSize = $version === 2 ? 28 : 24;

        // Block size: 3 bytes, big-endian
        $reftable->blockSize = $reader->readUint24();

        if ($reftable->blockSize === 0) {
            $reftable->blockSize = 4096;
        }

        $reader->readUint64(); // min_update_index
        $reader->readUint64(); // max_update_index

        if ($version === 2) {
            $hashId = $reader->read(4);
            $reftable->hashBytes = $hashId === 's256' ? 32 : 20;
        }

        $blockOffset = $headerSize;
        $firstBlock = true;

        while ($blockOffset + 4 <= $fileLength) {
            $reader->seek($blockOffset);
            $blockType = $reader->readByte();
            $blockLen = $reader->readUint24();

            if ($blockType === ord('r')) {
                $logicalStart = $firstBlock ? 0 : $blockOffset;
                $blockEnd = $firstBlock ? $blockLen : ($blockOffset + $blockLen);

                if ($blockEnd > $fileLength || $blockEnd - 2 < $reader->position()) {
                    break;
                }

                $restartReader = new BinaryReader(substr($reader->rawData(), $blockEnd - 2, 2));
                $restartCount = $restartReader->readUint16();
                $restartTableStart = $blockEnd - 2 - ($restartCount * 3);
                $recordPos = $blockOffset + 4;
                $previousName = '';

                while ($recordPos < $restartTableStart) {
                    $reader->seek($recordPos);
                    $prefixLength = VarInt::decodeOfsOffset($reader);
                    $nameAndType = VarInt::decodeOfsOffset($reader);
                    $suffixLength = $nameAndType >> 3;
                    $valueType = $nameAndType & 0x7;
                    $suffix = $reader->read($suffixLength);
                    $name = substr($previousName, 0, $prefixLength) . $suffix;
                    $previousName = $name;
                    VarInt::decodeOfsOffset($reader); // update_index_delta

                    switch ($valueType) {
                        case 0x0:
                            unset($reftable->refs[$name], $reftable->symrefs[$name]);
                            break;

                        case 0x1:
                            $reftable->refs[$name] = $reftable->hashBytes === 32
                                ? ObjectId::fromHex($reader->readHash32())
                                : ObjectId::fromHex($reader->readHash20());
                            unset($reftable->symrefs[$name]);
                            break;

                        case 0x2:
                            $oid = $reftable->hashBytes === 32
                                ? ObjectId::fromHex($reader->readHash32())
                                : ObjectId::fromHex($reader->readHash20());
                            if ($reftable->hashBytes === 32) {
                                $reader->readHash32();
                            } else {
                                $reader->readHash20();
                            }
                            $reftable->refs[$name] = $oid;
                            unset($reftable->symrefs[$name]);
                            break;

                        case 0x3:
                            $targetLength = VarInt::decodeOfsOffset($reader);
                            $reftable->symrefs[$name] = $reader->read($targetLength);
                            unset($reftable->refs[$name]);
                            break;
                    }

                    $recordPos = $reader->position();
                }
            }

            if ($reftable->blockSize <= 0) {
                break;
            }

            $blockOffset = $firstBlock ? $reftable->blockSize : ($blockOffset + $reftable->blockSize);
            $firstBlock = false;
        }

        return $reftable;
    }

    /**
     * @return array<string, ObjectId>
     */
    public function refs(): array
    {
        return $this->refs;
    }

    public function resolve(string $name): ?ObjectId
    {
        return $this->refs[$name] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function symrefs(): array
    {
        return $this->symrefs;
    }

    public function resolveSymbolic(string $name): ?string
    {
        return $this->symrefs[$name] ?? null;
    }
}
