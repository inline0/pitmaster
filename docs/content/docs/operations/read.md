---
title: "Reading Repositories"
description: "Open repositories and read objects, refs, commits, and history."
path: "operations/read"
order: 4
section: "Operations"
meta_title: "Reading Repositories"
meta_description: "Open repositories and read objects, refs, commits, and history."
---

# Reading Repositories

Pitmaster reads git repositories by parsing the on-disk formats directly: loose objects (zlib-compressed files in `.git/objects/`), pack files (`.git/objects/pack/`), refs (files in `.git/refs/` and `packed-refs`), and the index (`.git/index`).

## Opening a repository

```php
use Pitmaster\Pitmaster;

$repo = Pitmaster::open('/path/to/project');
```

Pitmaster supports three repository layouts:

1. **Regular repositories**: `.git` is a directory at the project root.
2. **Linked worktrees**: `.git` is a file containing `gitdir: <path>` pointing to `.git/worktrees/<name>/`.
3. **Bare repositories**: the path itself contains `HEAD`, `objects/`, etc.

```php
// Detection helpers
Pitmaster::isRepository('/path');  // true for any of the above
Pitmaster::isWorktree('/path');    // true only for linked worktrees
```

## Reading objects

Every piece of data in a git repository is stored as an object with a SHA-1 hash. There are four object types: blob, tree, commit, and tag.

### readObject

```php
use Pitmaster\Object\Blob;
use Pitmaster\Object\Tree;
use Pitmaster\Object\Commit;
use Pitmaster\Object\Tag;

$object = $repo->readObject('a1b2c3d4e5f6...');

match (true) {
    $object instanceof Blob   => handleBlob($object),
    $object instanceof Tree   => handleTree($object),
    $object instanceof Commit => handleCommit($object),
    $object instanceof Tag    => handleTag($object),
};
```

Objects are searched in loose storage first, then in pack files. Pack file lookups use the `.idx` fanout table for O(log n) binary search.

### catFile

For simple content retrieval without type checking:

```php
$content = $repo->catFile('a1b2c3d4e5f6...');
echo $content;
```

### objectExists

```php
if ($repo->objectExists('a1b2c3d4e5f6...')) {
    // Object is in the repository
}
```

### listObjects

```php
$hashes = $repo->listObjects();
// ['a1b2c3d4...', 'f6e5d4c3...', ...]
```

Returns hex hashes for all objects (loose and packed).

## Working with blobs

A blob holds raw file content.

```php
$blob = $repo->readObject($hash);
echo $blob->content;     // Raw file bytes
echo $blob->size;        // Content length
echo $blob->id->hex;     // SHA-1 hash
```

## Working with trees

A tree is a directory listing. Each entry has a mode, name, and hash.

```php
$tree = $repo->readObject($hash);

foreach ($tree->entries as $entry) {
    echo "{$entry->mode} {$entry->name} {$entry->hash->hex}\n";
    // 100644 README.md abc123...
    // 040000 src       def456...
    // 100755 deploy.sh 789012...

    if ($entry->isTree()) {
        // Recurse into subdirectory
    } elseif ($entry->isBlob()) {
        // Read file content
    } elseif ($entry->isExecutable()) {
        // Executable file
    } elseif ($entry->isSymlink()) {
        // Symbolic link
    } elseif ($entry->isGitlink()) {
        // Submodule (mode 160000)
    }
}
```

## Working with commits

A commit links a tree snapshot to its parents and metadata.

```php
$commit = $repo->head();

echo $commit->id->hex;      // Full SHA-1 hash
echo $commit->tree->hex;    // Tree object hash
echo $commit->author;       // "Name <email> timestamp tz"
echo $commit->committer;    // "Name <email> timestamp tz"
echo $commit->message;      // Full commit message

// Parent commits
foreach ($commit->parents as $parentId) {
    $parent = $repo->readObject($parentId->hex);
}

// Merge detection
if ($commit->isMerge()) {
    // Two or more parents
}
```

## Working with tags

Annotated tags are objects with a target, type, name, tagger, and message.

```php
$tag = $repo->readObject($hash);

echo $tag->objectId->hex;   // Target object hash
echo $tag->objectType;      // 'commit', 'tree', 'blob', or 'tag'
echo $tag->name;            // Tag name
echo $tag->tagger;          // "Name <email> timestamp tz"
echo $tag->message;         // Tag message
```

## Reading refs

### Current HEAD

```php
$commit = $repo->head();
```

Resolves HEAD (following symbolic refs like `HEAD -> refs/heads/main -> commit hash`) and returns the commit object.

### Current branch

```php
$branch = $repo->branch();  // 'main' or null if detached
```

### Listing branches and tags

```php
$branches = $repo->branches();  // ['bugfix/typo', 'feature/login', 'main']
$tags = $repo->tags();          // ['v1.0.0', 'v1.1.0']
```

### All refs

```php
$refs = $repo->allRefs();
// [
//     'refs/heads/main' => 'abc123...',
//     'refs/heads/feature' => 'def456...',
//     'refs/tags/v1.0.0' => '789012...',
//     'refs/remotes/origin/main' => 'abc123...',
// ]
```

### Resolving revisions

```php
$id = $repo->resolve('HEAD');
$id = $repo->resolve('main');
$id = $repo->resolve('v1.0.0');
$id = $repo->resolve('HEAD~3');
$id = $repo->resolve('main^2');
$id = $repo->resolve('abc123def456...');
```

The revision parser supports `~N` (ancestor) and `^N` (parent) operators, chained arbitrarily.

## Walking commit history

### log

```php
// Last 20 commits from HEAD
$commits = $repo->log(20);

foreach ($commits as $commit) {
    $short = substr($commit->id->hex, 0, 7);
    echo "{$short} {$commit->message}\n";
}
```

### log from a specific commit

```php
$from = $repo->resolve('feature/login');
$commits = $repo->log(10, $from);
```

### log filtered by file path

```php
$commits = $repo->logPath('src/Repository.php', 20);
```

Returns only commits where the specified file was changed (added, modified, or deleted).

### show

```php
$result = $repo->show('HEAD');
$commit = $result['commit'];
$diffs = $result['diff'];

echo "Author: {$commit->author}\n";
echo "Message: {$commit->message}\n\n";

foreach ($diffs as $diff) {
    echo $diff->format();
}
```

## Default branch detection

```php
$default = $repo->defaultBranch();
// Checks: remote HEAD symref -> local HEAD -> main/master fallback
```

## Merge ancestry

```php
// Is feature fully merged into main?
$merged = $repo->isBranchMerged('feature/done', 'main');

// Find the merge base of two commits
$base = $repo->mergeBase(
    $repo->resolve('main'),
    $repo->resolve('feature/login'),
);
```
