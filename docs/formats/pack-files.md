---
title: "Pack Files"
description: "Pack file format, index files, delta resolution, PackWriter, MultiPackIndex, and CommitGraph."
path: "formats/pack-files"
order: 80
section: "Binary Formats"
meta_title: "Pack Files"
meta_description: "Pack file format, index files, delta resolution, PackWriter, MultiPackIndex, and CommitGraph."
---

# Pack Files

Pack files are git's mechanism for efficient storage and transfer. Instead of storing each object as a separate zlib-compressed file, pack files combine many objects into a single file with optional delta compression. Almost every cloned repository uses pack files.

## Pack file structure

A pack file (`.git/objects/pack/pack-{hash}.pack`) contains:

```
Header:   PACK (4 bytes) + version (4 bytes, big-endian) + count (4 bytes, big-endian)
Entries:  [type+size varint] [delta-base] [zlib-compressed data] ...
Trailer:  checksum of the pack (20 bytes SHA-1, 32 bytes SHA-256)
```

### Object type encoding

Each entry starts with a variable-length integer where the type and size are interleaved:

```
First byte: 1TTTSSSS
  TTT  = object type (1-4 for base objects, 6 for OFS_DELTA, 7 for REF_DELTA)
  SSSS = first 4 bits of uncompressed size
  MSB  = 1 means more size bytes follow
```

Type values:
| Value | Type |
|-------|------|
| 1 | Commit |
| 2 | Tree |
| 3 | Blob |
| 4 | Tag |
| 6 | OFS_DELTA (offset-based delta) |
| 7 | REF_DELTA (hash-based delta) |

## Reading pack files

```php
use Pitmaster\Pack\PackFile;

$pack = PackFile::open(
    '/path/to/.git/objects/pack/pack-abc123.pack',
    '/path/to/.git/objects/pack/pack-abc123.idx',
);

$pack->has($hex);               // Is the object in this pack?
$object = $pack->read($hex);    // Resolved GitObject, or null
$object = $pack->readAtOffset($offset);
$hashes = $pack->allHashes();
```

The `PackFile` class:
1. Validates the `PACK` header and version number
2. Finds the offset through the `.idx` file and parses the type/size varint of the entry
3. Decompresses the zlib-compressed data
4. For base objects: returns the content directly
5. For delta objects: resolves the base (following chains) and applies the delta, caching resolved objects

## Pack index files

Every `.pack` file has a corresponding `.idx` file for fast lookups. Without the index, finding an object requires scanning the entire pack.

### v2 index format

```
Magic:    FF 74 4F 63 (identifies v2)
Version:  00 00 00 02
Fanout:   256 x 4-byte big-endian counts (cumulative)
Names:    N x 20-byte SHA-1 hashes, sorted (32 bytes for SHA-256)
CRC32s:   N x 4-byte CRC32 checksums
Offsets:  N x 4-byte pack offsets (MSB=1 means large offset table)
Large:    M x 8-byte offsets (for packs > 2GB)
Checksums: pack checksum + index checksum (20 bytes each for SHA-1)
```

```php
use Pitmaster\Pack\PackIndex;

$index = PackIndex::open('/path/to/pack-abc123.idx');

// Look up an object
$offset = $index->findOffset($hex);  // Pack file offset, or null

// List all objects in the pack
$hashes = $index->allHashes();
$count = $index->objectCount();
```

The fanout table enables O(log n) binary search: the 256 entries indicate how many hashes start with each possible first byte.

### v1 index format

Older format without CRC32 checksums. Pitmaster supports both: `PackIndex::open()` reads a v1 file when the v2 magic is missing, so the same code works for either.

## Delta resolution

Deltified objects store instructions for reconstructing content from a base object, rather than storing the full content. This achieves significant compression for similar objects (e.g., consecutive versions of the same file).

### Delta types

**OFS_DELTA** (type 6): the base object is identified by a negative offset within the same pack file. More common because it does not require knowing the base object's hash.

**REF_DELTA** (type 7): the base object is identified by its hash. In git the base can live outside the pack (thin packs). Pitmaster looks for REF_DELTA bases inside the same pack and therefore requests self-contained packs from servers.

### Delta instructions

Delta content is a sequence of copy and insert instructions:

```
Copy:   1XXXXXXX [offset bytes] [size bytes]   (copy from base)
Insert: 0LLLLLLL <L bytes of literal data>       (insert new data)
```

Copy instructions: the 7 X bits indicate which offset and size bytes are present. Offset is up to 4 bytes, size is up to 3 bytes. If size is zero, it means 0x10000 (65536) bytes.

Insert instructions: the 7 L bits give the literal data length (1-127 bytes). L=0 is reserved/invalid.

### DeltaApplier

```php
use Pitmaster\Pack\DeltaApplier;

$result = DeltaApplier::apply($baseContent, $deltaInstructions);
```

The `DeltaApplier`:
1. Reads the expected base size and result size from the delta header (LEB128 encoded)
2. Iterates through instructions:
   - Copy: extracts offset and size, copies bytes from the base content
   - Insert: extracts length, copies literal bytes from the delta data
3. Verifies the result size matches the expected size

### Delta chains

A deltified object's base may itself be deltified. `PackFile::read()` and `readAtOffset()` follow the chain:

1. Read the entry. If it is a base type (commit, tree, blob, tag), return it.
2. If OFS_DELTA: compute the base offset (current offset minus the encoded negative offset), read the base entry, recurse.
3. If REF_DELTA: look up the base by hash in the same pack's index, recurse.
4. Apply delta instructions on the resolved base.
5. Enforce `PITMASTER_MAX_DELTA_CHAIN` (default 50) to prevent runaway chains.

```php
// Configure max chain depth (integer)
define('PITMASTER_MAX_DELTA_CHAIN', 50);

// Read the effective limit
$max = \Pitmaster\Pack\DeltaResolver::maxChainDepth();
```

## PackWriter

Pitmaster can write pack files for network operations (push).

```php
use Pitmaster\Pack\PackWriter;
use Pitmaster\Pack\PackIndexer;

$packData = PackWriter::encode($objects);           // GitObject[] to pack bytes
$paths = PackWriter::write($packDir, $objects);     // ['packPath' => ..., 'idxPath' => ..., 'hash' => ...]

$idxPath = PackIndexer::writeIndex($packPath);      // Build the .idx for a received pack
```

The `PackWriter`:
1. Collects objects to include in the pack
2. Serializes each object with type/size varint header
3. Compresses content with zlib
4. Writes the PACK header, entries, and SHA-1 trailer

Currently writes only base objects (no delta compression). Delta compression is an optimization that can be added later; let `git gc` repack for storage efficiency.

## PackEnumerator

Iterate all objects in a pack, in index order:

```php
use Pitmaster\Pack\PackEnumerator;

$enumerator = new PackEnumerator($packFile); // a PackFile opened with its .idx

foreach ($enumerator->enumerate() as $object) {
    echo "{$object->type->value} {$object->id->hex} {$object->size()}\n";
}
```

Each item is a resolved `GitObject`.

## MultiPackIndex

For repositories with many pack files, the multi-pack-index (MIDX) provides a single lookup across all packs.

```php
use Pitmaster\Pack\MultiPackIndex;
```

The MIDX file (`.git/objects/pack/multi-pack-index`) contains:
- A fanout table (like pack index v2)
- Object IDs sorted across all packs
- Pack file names and per-object pack assignments
- Offsets into the individual pack files

`MultiPackIndex::open($path)` parses a MIDX file and offers `findObject($hex)` and `packNames()`. Object lookups in the repository do not use it: the object database always reads the individual `.idx` files.

## CommitGraph

The commit-graph file accelerates commit history traversal and merge base computation.

```php
use Pitmaster\Pack\CommitGraph;
```

Located at `.git/objects/info/commit-graph`, it contains:
- Commit OIDs in sorted order
- Parent information (indexed, not requiring object reads)
- Generation numbers (for faster reachability queries)
- Commit timestamps

When present, the merge-base computation behind `Repository::merge()`, `mergeBase()`, `isBranchMerged()`, and the push fast-forward check reads parent links from the commit-graph instead of reading individual commit objects. `CommitWalker` (log) does not use it.

## Variable-length integers

Pack files use multiple variable-length integer encodings:

### Git varint (type/size headers)

MSB-continue encoding where the most significant bit indicates more bytes follow:

```php
use Pitmaster\Encoding\VarInt;

$size = VarInt::decodePackSize($reader, $initialBits);  // continues the size from the first header byte
$offset = VarInt::decodeOfsOffset($reader);             // OFS_DELTA base distance
$header = VarInt::encodePackSize($type, $size);
```

### LEB128 (delta headers)

Used in delta instruction headers for base size and result size:

```php
use Pitmaster\Encoding\Leb128;

$value = Leb128::decodeUnsigned($reader);   // a BinaryReader
$bytes = Leb128::encodeUnsigned($value);
```

### BinaryReader

Low-level byte stream with position tracking:

```php
use Pitmaster\Encoding\BinaryReader;

$reader = new BinaryReader($data);
$byte = $reader->readByte();
$int = $reader->readUint32();   // big-endian
$bytes = $reader->read(20);
$hash = $reader->readHash(20);
$reader->position();  // Current offset
$reader->seek(0);
```
