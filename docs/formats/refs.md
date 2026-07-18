---
title: "Refs"
description: "Loose refs, packed-refs, RefDatabase, symbolic refs, reflog, and reftable."
path: "formats/refs"
order: 100
section: "Binary Formats"
meta_title: "Refs"
meta_description: "Loose refs, packed-refs, RefDatabase, symbolic refs, reflog, and reftable."
---

# Refs

Refs (references) are named pointers to commits. Branches, tags, HEAD, and remote tracking refs are all refs. Pitmaster reads and writes refs in multiple formats: loose files, packed-refs, and reftable.

## RefDatabase

The `RefDatabase` is the composite layer that searches loose refs first, then packed-refs.

```php
use Pitmaster\Ref\RefDatabase;

$refs = new RefDatabase($gitDir, $commonDir);

// Resolve a ref to an ObjectId
$id = $refs->resolve('refs/heads/main');

// Resolve HEAD (follows symbolic refs)
$headId = $refs->resolveHead();

// Read HEAD as a symbolic ref
$symref = $refs->readHead();
echo $symref->target;  // 'refs/heads/main'

// List all refs
foreach ($refs->list() as $name => $id) {
    echo "{$id->hex} {$name}\n";
}

// Update a ref
$refs->update('refs/heads/main', $newCommitId);

// Delete a ref
$refs->delete('refs/heads/old-branch');
```

For linked worktrees, the `RefDatabase` uses the per-worktree git dir for HEAD and loose refs, but the common dir for packed-refs.

## Loose refs

Loose refs are individual files in `.git/refs/`. Each file contains the 40-character hex hash of the target object, followed by a newline.

```
.git/refs/heads/main           -> abc123def456...\n
.git/refs/tags/v1.0.0          -> 789012abc345...\n
.git/refs/remotes/origin/main  -> abc123def456...\n
```

```php
use Pitmaster\Ref\LooseRefStore;

$store = new LooseRefStore($gitDir);

$id = $store->resolve('refs/heads/main');
$store->update('refs/heads/main', $commitId);
$store->delete('refs/heads/old');
```

### HEAD

HEAD is a special ref that can be either:

**Symbolic**: points to another ref (most common, when on a branch):
```
ref: refs/heads/main\n
```

**Detached**: points directly to a commit hash:
```
abc123def456789012abc345def678901234abcd\n
```

```php
use Pitmaster\Ref\SymbolicRef;

$symref = SymbolicRef::parse('HEAD', $headContent);

if ($symref !== null) {
    echo "On branch: {$symref->target}\n";
    // 'refs/heads/main'
} else {
    echo "Detached HEAD\n";
}
```

## Packed refs

The `packed-refs` file combines many refs into a single file for efficiency. Git creates it during `git pack-refs` or `git gc`.

```
# pack-refs with: peeled fully-peeled sorted
abc123def456789012abc345def678901234abcd refs/heads/main
789012abc345def678901234abcdabc123def456 refs/tags/v1.0.0
^def456789012abc345def678901234abcdabc12
```

Lines starting with `^` are peeled values: the commit that an annotated tag ultimately points to.

```php
use Pitmaster\Ref\PackedRefStore;

$store = new PackedRefStore($commonDir);

$id = $store->resolve('refs/heads/main');

foreach ($store->list() as $name => $id) {
    echo "{$id->hex} {$name}\n";
}
```

### Peeled values

For annotated tags, the packed-refs file may include the peeled (dereferenced) value on the line after the tag ref. The peeled value is the commit that the tag object ultimately points to (through possibly multiple tag objects).

```php
$peeled = $store->peeledValue('refs/tags/v1.0.0');
// Returns the commit ObjectId, or null if not peeled
```

## Ref resolution order

When resolving a ref, Pitmaster checks in this order:

1. Loose ref (file in `.git/refs/`)
2. Packed ref (entry in `.git/packed-refs`)

Loose refs take priority. When a packed ref is updated, the new value is written as a loose ref (the packed-refs file is not rewritten).

## SymbolicRef

Symbolic refs are references that point to other references rather than directly to objects.

```php
use Pitmaster\Ref\SymbolicRef;

// Parse
$ref = SymbolicRef::parse('HEAD', "ref: refs/heads/main\n");
echo $ref->name;    // 'HEAD'
echo $ref->target;  // 'refs/heads/main'

// Update
$refs->updateSymbolic('HEAD', 'refs/heads/feature');
```

## Reflog

The reflog records when refs change, enabling `git reflog` to show the history of HEAD and branch tips.

Reflog files live in `.git/logs/`:
```
.git/logs/HEAD
.git/logs/refs/heads/main
.git/logs/refs/stash
```

Each line records:
```
<old-hash> <new-hash> <author> <timestamp> <tz>\t<message>
```

```php
use Pitmaster\Ref\Reflog;

$reflog = Reflog::open($gitDir, 'HEAD');

// Read entries
$entries = $reflog->entries();
foreach ($entries as $entry) {
    echo "{$entry['old']} -> {$entry['new']}: {$entry['message']}\n";
}

// Append an entry
$reflog->append(
    $oldId,
    $newId,
    'Name <email> 1700000000 +0000',
    'commit: Add feature',
);
```

The stash uses the reflog of `refs/stash` as its stack storage.

## Reftable

Reftable is a newer binary format for storing refs, designed for better performance with many refs (thousands of branches). It replaces both loose refs and packed-refs.

Reftable files are stored in `.git/reftable/` and contain:
- Ref records (sorted by name)
- Log records (reflog entries)
- Block-level indexing for fast lookups

Pitmaster reads reftable files when present but falls back to the traditional loose + packed ref storage.

## Ref naming conventions

| Pattern | Meaning |
|---------|---------|
| `refs/heads/<name>` | Local branch |
| `refs/tags/<name>` | Tag (lightweight or annotated) |
| `refs/remotes/<remote>/<name>` | Remote tracking branch |
| `refs/stash` | Stash stack |
| `refs/notes/<namespace>` | Git notes |
| `HEAD` | Current checkout (symbolic or detached) |
| `FETCH_HEAD` | Last fetch result |
| `MERGE_HEAD` | Branch being merged (during merge) |
| `ORIG_HEAD` | Previous HEAD (before dangerous operations) |

## Working with refs through Repository

```php
// Read
$branch = $repo->branch();          // Current branch name
$branches = $repo->branches();      // All branch names
$tags = $repo->tags();              // All tag names
$refs = $repo->allRefs();           // All refs as name => hash

// Write
$repo->createBranch('feature');     // Create refs/heads/feature
$repo->deleteBranch('feature');     // Delete refs/heads/feature
$repo->createTag('v1.0', 'Release');// Create annotated tag
$repo->updateRef('refs/heads/main', $id); // Direct ref update

// Resolve
$id = $repo->resolve('HEAD');
$id = $repo->resolve('main');
$id = $repo->resolve('v1.0.0');
$id = $repo->resolve('HEAD~3');
```
