---
title: "Index Format"
description: "Git index (staging area) format, versions 2/3/4, extensions, conflict stages, and IndexWriter."
path: "formats"
order: 90
section: "Binary Formats"
meta_title: "Index Format"
meta_description: "Git index (staging area) format, versions 2/3/4, extensions, conflict stages, and IndexWriter."
---

# Index Format

The index file (`.git/index`) is the staging area. It records the files that will be included in the next commit, along with metadata for efficient status computation. Pitmaster reads index versions 2, 3, and 4, and writes versions 2 and 3. An index read as version 4 is safely rewritten in the uncompressed version 3 layout, preserving entries and extended flags.

## Reading the index

```php
use Pitmaster\Index\Index;

$index = Index::open('/path/to/.git/index');   // pass 32 as the second argument for SHA-256

// Or through the repository
$index = $repo->index();

// Entry count (all stages)
echo $index->count();

// Iterate stage 0 entries, keyed by path
foreach ($index->entries() as $path => $entry) {
    echo "{$entry->path} {$entry->hash->hex}\n";
}

// Look up a specific entry
$entry = $index->entry('src/File.php');
```

`Index::open()` returns an empty index when the file does not exist. `entries()` returns only stage 0 entries; `allEntries()` includes conflict stages, and `paths()` lists the tracked paths.

## IndexEntry

Each entry in the index is a readonly value object:

```php
use Pitmaster\Index\IndexEntry;

$entry->path;          // File path relative to repo root
$entry->hash;          // ObjectId of the blob
$entry->mode;          // File mode as an int (0100644, 0100755, 0120000, 0160000)
$entry->fileSize;      // File size in bytes
$entry->ctimeSec;      // Metadata change time (seconds)
$entry->ctimeNsec;     // Metadata change time nanoseconds
$entry->mtimeSec;      // Modification time (seconds)
$entry->mtimeNsec;     // Modification time nanoseconds
$entry->dev;           // Device number
$entry->ino;           // Inode number
$entry->uid;           // User ID
$entry->gid;           // Group ID
$entry->flags;         // Raw flags field
$entry->extendedFlags; // v3 extended flags (skip-worktree, intent-to-add)
$entry->stage();       // Conflict stage (0 = normal, 1 = base, 2 = ours, 3 = theirs)
$entry->assumeValid(); // Assume-valid bit
$entry->skipWorktree(); // Skip-worktree bit
```

### Creating entries

```php
// From file stat
$entry = IndexEntry::fromStat('src/File.php', $blobId, '/full/path/to/src/File.php');

// Minimal (no stat info)
$entry = IndexEntry::create('src/File.php', $blobId);

// With explicit mode
$entry = IndexEntry::create('deploy.sh', $blobId, 0100755);
```

## Modifying the index

```php
// Add or replace an entry
$index->addEntry($entry);

// Remove an entry
$index->removeEntry('src/OldFile.php');

// Replace all stages of a conflicted path with one resolved entry
$index->resolveConflict('src/File.php', $entry);
```

`addEntries()` and `removeEntries()` take several entries or paths in one pass. Any change to the entries drops the stored extensions (see below).

## Writing the index

```php
use Pitmaster\Index\IndexWriter;

IndexWriter::write($index, '/path/to/.git/index');
```

Or through the repository (which handles this automatically):

```php
$repo->add('src/File.php');    // Reads file, creates blob, updates index, writes
$repo->remove('--cached', 'old-file.txt'); // Removes from index only, writes
```

## Binary format

### Header

```
Signature:   DIRC (4 bytes, ASCII)
Version:     4 bytes, big-endian (2, 3, or 4)
Entry count: 4 bytes, big-endian
```

### Entry format (v2)

Each entry is:

```
ctime seconds:     4 bytes, big-endian
ctime nanoseconds: 4 bytes, big-endian
mtime seconds:     4 bytes, big-endian
mtime nanoseconds: 4 bytes, big-endian
dev:               4 bytes, big-endian
ino:               4 bytes, big-endian
mode:              4 bytes, big-endian
uid:               4 bytes, big-endian
gid:               4 bytes, big-endian
file size:         4 bytes, big-endian
object hash:       20 bytes SHA-1 or 32 bytes SHA-256 (raw binary)
flags:             2 bytes, big-endian
path:              variable length, NUL-terminated
padding:           1-8 NUL bytes to align to 8-byte boundary
```

The flags field:
- Bit 15: assume-valid flag
- Bits 13-12: merge stage (0-3)
- Bits 11-0: path name length, or 0xFFF when the path is 4095 bytes or longer (the reader then scans for the NUL)

### Version differences

**Version 2**: the base format described above. Most common.

**Version 3**: adds extended flags (2 extra bytes after the regular flags). Supports the `skip-worktree` and `intent-to-add` bits. Used when any entry needs extended flags.

**Version 4**: adds path prefix compression. Instead of storing full paths, each entry stores the number of bytes to remove from the previous entry's path, followed by the new suffix. Significantly reduces index size for deeply nested trees.

## Conflict stages

During a merge conflict, a single path can have multiple index entries at different stages:

| Stage | Meaning |
|-------|---------|
| 0 | Normal (no conflict) |
| 1 | Base version (common ancestor) |
| 2 | Ours (current branch) |
| 3 | Theirs (branch being merged) |

```php
// During a conflict, entries have non-zero stages
foreach ($index->allEntries() as $entry) {
    if ($entry->stage() > 0) {
        echo "Conflict: {$entry->path} (stage {$entry->stage()})\n";
    }
}

$index->hasUnmerged();            // true while conflicts remain
$index->unmergedPaths();          // conflicted paths
$index->stageEntries('a.txt');    // [1 => base, 2 => ours, 3 => theirs]
```

## Index extensions

Extensions are appended after the entries, before the checksum. Each extension has a 4-byte signature, 4-byte size, and variable-length data.

Pitmaster keeps extension blocks as raw bytes (`$index->extensions()` returns `signature` and `data` pairs) and writes them back unchanged when the entries are untouched. It does not interpret or generate any extension, and any change to the entries drops all of them, which git accepts and rebuilds as needed. The extensions git uses are described below for reference.

### TREE extension

Caches pre-computed tree objects for directories. Speeds up `git status` and `git commit` by avoiding tree recomputation when only the index has changed.

Signature: `TREE` (0x54524545)

### REUC extension

Records resolve-undo information. After a conflict is resolved, the original stage 1/2/3 entries are moved here so they can be restored with `git checkout -m`.

Signature: `REUC` (0x52455543)

### Other extensions

git also writes:
- `EOIE` (end of index entry): marks where entries end and extensions begin
- `IEOT` (index entry offset table): enables parallel index loading
- `link` (split index): for very large repositories using split index

In git's format, extensions whose signature starts with an uppercase letter are optional and lowercase ones are required. Pitmaster accepts both kinds without checking. A split index (`link`) is not loaded, so repositories that use one are not supported.

## Checksum

The index file ends with a checksum of all preceding bytes in the repository's hash (20 bytes SHA-1, 32 bytes SHA-256). Pitmaster writes it on save but does not verify it on read.

`Index::open()` and `Index::parse()` throw `IndexParseException` for a missing `DIRC` signature or an unsupported version.

## Index and status

The index is central to status computation. `WorkingTreeStatus` compares:
1. HEAD tree vs index: detects staged changes
2. Index vs working tree: detects unstaged changes

The stat fields (ctime, mtime, dev, ino, size, mode, uid, gid) enable fast "has this file changed?" checks without reading file content. If the stat fields match and the file was last modified before the status scan started, the file is assumed unchanged. A different size marks it modified at once. Otherwise Pitmaster reads the file and computes its hash to check for actual content changes.

## Assume-valid flag

```php
if ($entry->assumeValid()) {
    // Entry has assume-valid set (flags & 0x8000)
}
```

This flag tells git to skip checking the file during status, even if the stat fields have changed (`git update-index --assume-unchanged`). Pitmaster keeps the bit when it rewrites entries, but its status does not honour it and still compares the file.
