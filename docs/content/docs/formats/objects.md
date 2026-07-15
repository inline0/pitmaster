---
title: "Git Objects"
description: "Blob, Tree, Commit, Tag objects, ObjectId, loose object format, and serialization."
path: "formats/objects"
order: 9
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
echo $blob->size;       // 13
echo $blob->id->hex;    // SHA-1 hash
echo $blob->type;       // ObjectType::Blob
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

// Create a tree
$entries = [
    new TreeEntry('100644', 'README.md', $blobId),
    new TreeEntry('100755', 'deploy.sh', $execBlobId),
    new TreeEntry('040000', 'src', $subtreeId),
];

$tree = Tree::fromEntries($entries);
```

#### TreeEntry

`TreeEntry` is a readonly value object:

```php
use Pitmaster\Object\TreeEntry;

$entry->mode;   // '100644', '100755', '040000', '120000', '160000'
$entry->name;   // Filename
$entry->hash;   // ObjectId

// Type checks
$entry->isBlob();        // mode 100644
$entry->isExecutable();  // mode 100755
$entry->isTree();        // mode 040000
$entry->isSymlink();     // mode 120000
$entry->isGitlink();     // mode 160000 (submodule)
```

Mode values:
| Mode | Meaning |
|------|---------|
| `100644` | Regular file |
| `100755` | Executable file |
| `040000` | Directory (tree) |
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
tree <40-char hex>\n
parent <40-char hex>\n     (zero or more)
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

$tag->objectId;      // Target object hash
$tag->objectType;    // 'commit', 'tree', etc.
$tag->name;          // Tag name
$tag->tagger;        // 'Name <email> timestamp timezone'
$tag->message;       // Tag message
```

Raw tag format:

```
object <40-char hex>\n
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

// From binary (20 bytes for SHA-1)
$id = ObjectId::fromBinary($rawBytes);

// Compute from content
$id = ObjectId::compute(ObjectType::Blob, 'Hello, world!');

// Zero hash
$id = ObjectId::zero();

// Properties
$id->hex;      // 40-character hex string
$id->binary;   // 20-byte raw binary

// Comparison
$id->equals($otherId);
```

The hash is computed over the uncompressed data: `sha1("{type} {size}\0{content}")`.

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
2. Hash: `sha1(serialized)`
3. Compress: `zlib_encode(serialized)`
4. Write to `.git/objects/{hash[0..1]}/{hash[2..39]}`
5. Atomic write (write to temp file, then rename)

## ObjectSerializer

The `ObjectSerializer` handles encoding and decoding the object wire format.

```php
use Pitmaster\Storage\ObjectSerializer;

// Encode
$raw = ObjectSerializer::encode($object);
// "blob 5\0Hello"

// Decode
$object = ObjectSerializer::decode($raw);
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

Pitmaster abstracts the hash algorithm through `ObjectId`. SHA-1 is the default (and what every existing repository uses). SHA-256 support is available but deferred for post-v1.

```php
define('PITMASTER_HASH_ALGO', 'sha256');
```

When SHA-256 is active, `ObjectId` uses 64-character hex strings and 32-byte binary hashes.

## Tree entry binary format

Inside a tree object's content, each entry is encoded as:

```
<octal mode as ASCII> <space> <filename> \0 <20-byte binary hash>
```

Note: the hash is raw binary, not hex. This is the only place in the format where hashes are stored in binary rather than hex.

Entries are sorted by comparing names as if directories have a trailing `/`. This sort order must be exact for the tree's SHA-1 to match git's computation.
