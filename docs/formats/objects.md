---
title: "Git Objects"
description: "Blob, Tree, Commit, Tag objects, ObjectId, loose object format, and serialization."
path: "formats/objects"
order: 70
section: "Binary Formats"
meta_title: "Git Objects"
meta_description: "Blob, Tree, Commit, Tag objects, ObjectId, loose object format, and serialization."
---

# Git Objects

Everything in a git repository is stored as an object. There are four types: blobs (file content), trees (directory listings), commits (snapshots with metadata), and tags (annotated references). Each object is identified by the SHA-1 hash of its content.

## Object types

### Blob

A blob holds raw file content with no metadata (no filename, no permissions).

```php
use Pitmaster\Object\Blob;

// Create from content
$blob = Blob::fromContent('Hello, world!');
echo $blob->content;    // 'Hello, world!'
echo $blob->size();     // 13
echo $blob->id->hex;    // SHA-1 hash
echo $blob->type->value; // 'blob' (the property is ObjectType::Blob)
```

### Tree

A tree is a directory listing. Each entry has a mode, name, and hash pointing to a blob or subtree.

```php
use Pitmaster\Object\Tree;
use Pitmaster\Object\TreeEntry;

// Read a tree
$tree = $repo->readObject($treeHash);

foreach ($tree->entries as $entry) {
    echo "{$entry->mode} {$entry->name} {$entry->hash->hex}\n";
}

// Look up a specific entry
$entry = $tree->entry('README.md');

// Create a tree (entries already in git's sort order)
$entries = [
    new TreeEntry('100644', 'README.md', $blobId),
    new TreeEntry('100755', 'deploy.sh', $execBlobId),
    new TreeEntry('40000', 'src', $subtreeId),
];

$tree = Tree::fromEntries($entries);
```

`Tree::fromEntries()` writes the entries in the order given and the mode strings exactly as given, so sort them first and write directory modes as `40000`. A tree written with `040000` or in another order gets a different hash from git's, and `git fsck --strict` reports the zero-padded mode.

#### TreeEntry

`TreeEntry` is a readonly value object:

```php
use Pitmaster\Object\TreeEntry;

$entry->mode;   // '100644', '100755', '40000', '120000', '160000'
$entry->name;   // Filename
$entry->hash;   // ObjectId

// Type checks
$entry->isBlob();        // regular or executable file (mode 10xxxx)
$entry->isExecutable();  // mode 100755
$entry->isTree();        // mode 40000 (also accepts 040000)
$entry->isSymlink();     // mode 120000
$entry->isGitlink();     // mode 160000 (submodule)
```

Mode values:
| Mode | Meaning |
|------|---------|
| `100644` | Regular file |
| `100755` | Executable file |
| `40000` | Directory (tree); shown as `040000` by `git ls-tree` |
| `120000` | Symbolic link |
| `160000` | Gitlink (submodule) |

Tree entries are sorted by name, with directory names treated as if they end with `/` for comparison purposes.

### Commit

A commit ties a tree to its history and metadata.

```php
use Pitmaster\Object\Commit;

$commit = $repo->head();

$commit->id;         // ObjectId
$commit->tree;       // ObjectId of the tree
$commit->parents;    // array of ObjectId (zero for root, one for normal, two+ for merge)
$commit->author;     // 'Name <email> timestamp timezone'
$commit->committer;  // 'Name <email> timestamp timezone'
$commit->message;    // Full commit message

$commit->isMerge();  // true if two or more parents
```

#### Building commit content

```php
$content = Commit::buildContent(
    tree: $treeId,
    parents: [$parentId],
    author: 'Jane <jane@example.com> 1700000000 +0000',
    committer: 'Jane <jane@example.com> 1700000000 +0000',
    message: "Add feature\n",
);
```

The raw commit format:

```
tree <hex hash>\n
parent <hex hash>\n       (zero or more)
author <name> <<email>> <timestamp> <tz>\n
committer <name> <<email>> <timestamp> <tz>\n
\n
<message>
```

### Tag

Annotated tags are objects with metadata.

```php
use Pitmaster\Object\Tag;

$tag = $repo->readObject($tagHash);

$tag->object;        // ObjectId of the target
$tag->objectType;    // ObjectType enum (ObjectType::Commit, ...)
$tag->name;          // Tag name
$tag->tagger;        // 'Name <email> timestamp timezone'
$tag->message;       // Tag message
```

Raw tag format:

```
object <hex hash>\n
type commit\n
tag <name>\n
tagger <name> <<email>> <timestamp> <tz>\n
\n
<message>
```

## ObjectId

`ObjectId` is a readonly value object representing a SHA-1 (or SHA-256) hash.

```php
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;

// From hex string
$id = ObjectId::fromHex('a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2');

// From binary (20 bytes for SHA-1, 32 for SHA-256)
$id = ObjectId::fromBinary($rawBytes);

// Compute from content
$id = ObjectId::compute(ObjectType::Blob, 'Hello, world!');
$id = ObjectId::compute(ObjectType::Blob, 'Hello, world!', 'sha256');

// Zero hash
$id = ObjectId::zero();
$id = ObjectId::zero('sha256');

// Properties
$id->hex;      // 40-character hex string (64 for SHA-256)
$id->binary;   // 20-byte raw binary (32 for SHA-256)
$id->algo;     // 'sha1' or 'sha256'
$id->isSha256();
$id->isZero();

// Comparison
$id->equals($otherId);
```

The hash is computed over the uncompressed data: `sha1("{type} {size}\0{content}")`, or SHA-256 of the same bytes. `fromHex()` and `fromBinary()` pick the algorithm from the length.

## ObjectType enum

```php
use Pitmaster\Object\ObjectType;

ObjectType::Blob;    // 'blob'
ObjectType::Tree;    // 'tree'
ObjectType::Commit;  // 'commit'
ObjectType::Tag;     // 'tag'
```

## Loose object format

Loose objects are stored as individual files in `.git/objects/`, organized by the first two hex characters of their hash.

```
.git/objects/a1/b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2
```

The file content is zlib-compressed. The uncompressed content is:

```
<type> <decimal size>\0<raw content>
```

For example, a blob containing "Hello" is stored as:

```
zlib_encode("blob 5\0Hello")
```

### Reading loose objects

```php
use Pitmaster\Storage\LooseObjectStore;

$store = new LooseObjectStore('/path/to/.git/objects');
$object = $store->read($objectId);
```

The `LooseObjectStore`:
1. Computes the file path from the hash
2. Reads and zlib-decompresses the file
3. Parses the header to extract type and size
4. Delegates to the appropriate parser (`Blob`, `Tree`, `Commit`, `Tag`)

### Writing loose objects

```php
$store->write($object);
```

The write process:
1. Serialize: `"{type} {size}\0{content}"`
2. Use the object's `id`, computed when the object was built (reads verify the hash)
3. Compress: `zlib_encode(serialized)`
4. Write to `.git/objects/{hash[0..1]}/{rest of hash}`
5. Atomic write (write to temp file, then rename)

## ObjectSerializer

The `ObjectSerializer` handles encoding and decoding the object wire format.

```php
use Pitmaster\Storage\ObjectSerializer;

// Encode
$raw = ObjectSerializer::encodeRaw($object);
// "blob 5\0Hello"
$compressed = ObjectSerializer::encode($object);
// zlib-compressed form, as stored in a loose object file

// Decode
$object = ObjectSerializer::decodeRaw($raw);
$object = ObjectSerializer::decode($compressed, $expectedHex); // throws CorruptObjectException on a hash mismatch
```

## ObjectDatabase

The `ObjectDatabase` is a composite store that searches loose objects first, then pack files.

```php
use Pitmaster\Storage\ObjectDatabase;

$db = new ObjectDatabase('/path/to/.git/objects');

$object = $db->read($objectId);    // Search loose, then packs
$db->write($object);               // Always writes to loose
$exists = $db->exists($objectId);  // Check loose, then packs
$all = $db->listAll();             // All hashes from loose + packs
```

Write-through to loose storage is intentional. Let `git gc` repack into pack files.

## SHA-256 support

Pitmaster abstracts the hash algorithm through `ObjectId`. SHA-1 is the default. SHA-256 is chosen per repository when it is created:

```php
$repo = Pitmaster::init('/path/to/project', 'sha256');
```

```bash
./vendor/bin/pitmaster init --object-format=sha256
```

This writes `extensions.objectformat = sha256` to the config. Repositories opened later read the format from that entry, and every object, tree, index, and ref operation then uses 64-character hex strings and 32-byte binary hashes. There is no global switch or constant for the algorithm.

The object classes take the algorithm as an argument when you build objects yourself: `Blob::fromContent($content, 'sha256')`, `Tree::fromEntries($entries, 'sha256')`, `ObjectId::compute($type, $content, 'sha256')`. `Pitmaster::clone()` creates SHA-1 repositories.

## Tree entry binary format

Inside a tree object's content, each entry is encoded as:

```
<octal mode as ASCII> <space> <filename> \0 <binary hash: 20 bytes SHA-1, 32 bytes SHA-256>
```

Note: the hash is raw binary, not hex, unlike the hex hashes in commit and tag objects.

Entries are sorted by comparing names as if directories have a trailing `/`. This sort order must be exact for the tree's hash to match git's computation.
