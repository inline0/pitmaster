---
title: "Worktrees"
description: "Linked worktrees for multiple checkouts from a single repository."
path: "advanced/worktrees"
order: 16
section: "Advanced"
meta_title: "Worktrees"
meta_description: "Linked worktrees for multiple checkouts from a single repository."
---

# Worktrees

Git worktrees allow multiple working directories to share a single repository. Each worktree has its own checkout (HEAD, index, working files) but shares the object store, config, and packed-refs with the main repository. Pitmaster fully supports creating, managing, and operating on linked worktrees.

## Concepts

A regular git repository has a single working tree:

```
project/
  .git/          (directory)
  src/
  README.md
```

With worktrees, you can have additional checkouts:

```
project/                          (main worktree)
  .git/                           (the actual git directory)
    worktrees/
      review/                     (metadata for linked worktree)
        HEAD
        commondir
        gitdir

/tmp/review/                      (linked worktree)
  .git                            (file, not directory)
  src/
  README.md
```

The linked worktree's `.git` is a file (not a directory) containing:
```
gitdir: /path/to/project/.git/worktrees/review
```

## Creating a worktree

```php
$repo = Pitmaster::open('/path/to/project');

// Create a linked worktree on a new branch
$wt = $repo->addWorktree('/tmp/review', 'feature/review');

echo $wt->path;        // '/tmp/review'
echo $wt->branch;      // 'feature/review'
echo $wt->gitDir;      // '/path/to/project/.git/worktrees/review'
echo $wt->isMain;      // false
echo $wt->isDetached;  // false
```

`addWorktree()` does the following:
1. Creates the branch if it does not exist (from HEAD or the specified `$from` commit)
2. Creates the metadata directory at `.git/worktrees/<name>/`
3. Writes the `.git` file in the worktree directory
4. Writes `HEAD`, `commondir`, and `gitdir` metadata files
5. Checks out the branch's tree into the worktree directory
6. Writes the index for the worktree

### With an explicit metadata name

Use the optional `name` argument when multiple linked worktrees can share the same checkout basename.

```php
$wt = $repo->addWorktree(
    '/tmp/sandboxes/divine-child',
    'feature/review',
    name: 'sandbox-divine-child',
);
```

That metadata name is used under `.git/worktrees/<name>/` instead of `basename($path)`.

### From a specific commit

```php
$wt = $repo->addWorktree('/tmp/hotfix', 'hotfix/urgent', $repo->resolve('v1.0.0'));
```

## Listing worktrees

```php
$worktrees = $repo->worktrees();

foreach ($worktrees as $wt) {
    $type = $wt->isMain ? 'main' : 'linked';
    $branch = $wt->branch ?? 'detached';
    $locked = $wt->isLocked ? ' [locked]' : '';

    echo "{$wt->path} ({$type}) [{$branch}]{$locked}\n";
}
```

The list always includes the main worktree first, followed by linked worktrees.

## Removing a worktree

```php
$repo->removeWorktree('review');

// Force remove (even if locked)
$repo->removeWorktree('review', force: true);
```

Removing a worktree:
1. Checks if the worktree is locked (fails unless `$force` is true)
2. Removes the `.git` file from the worktree directory
3. Removes the metadata directory from `.git/worktrees/`

The working tree files are not automatically deleted. The branch continues to exist.

## Opening a linked worktree

You can open a linked worktree just like any repository:

```php
$repo = Pitmaster::open('/tmp/review');

// Pitmaster detects the .git file and follows the gitdir reference
echo $repo->isLinkedWorktree();  // true
echo $repo->gitDir();            // '/path/to/project/.git/worktrees/review'
echo $repo->commonGitDir();      // '/path/to/project/.git'
echo $repo->workDir();           // '/tmp/review'
```

All operations work transparently:
- **Objects**: read from and written to the common git dir (`project/.git/objects/`)
- **Config**: read from the common git dir (`project/.git/config`)
- **Packed-refs**: read from the common git dir
- **HEAD**: per-worktree (each worktree tracks its own branch)
- **Index**: per-worktree (each worktree has its own staging area)
- **Loose refs**: per-worktree git dir

## Worktree value object

```php
use Pitmaster\Worktree\Worktree;

$wt->name;          // Metadata name (null for the main worktree)
$wt->path;          // Working directory path
$wt->gitDir;        // Per-worktree git dir
$wt->branch;        // Branch name (null if detached)
$wt->head;          // ObjectId of HEAD commit
$wt->isMain;        // true for the main worktree
$wt->isDetached;    // true if HEAD is detached
$wt->isLocked;      // true if worktree is locked
$wt->lockReason;    // Lock reason string (nullable)
```

## WorktreeManager

For low-level worktree management:

```php
use Pitmaster\Worktree\WorktreeManager;

$manager = new WorktreeManager($gitDir, $workDir);

// List
$worktrees = $manager->list();

// Add
$wt = $manager->add('/tmp/review', 'feature/review');
$wt = $manager->add('/tmp/review', 'feature/review', 'review-app');

// Remove
$manager->remove('review');
$manager->remove('review', force: true);

// Lock/unlock
$manager->lock('review', 'Do not delete, code review in progress');
$manager->unlock('review');
```

## Locking worktrees

```php
// Lock (prevents removal)
$manager->lock('review', 'Code review in progress');

// Attempt to remove locked worktree
try {
    $manager->remove('review');
} catch (\RuntimeException $e) {
    // "Worktree is locked: review (Code review in progress)"
}

// Force remove ignores the lock
$manager->remove('review', force: true);

// Unlock
$manager->unlock('review');
```

The lock is stored as a file at `.git/worktrees/<name>/locked`. The file content is the lock reason (may be empty).

## Detecting worktrees

```php
// Check if a path is a linked worktree
if (Pitmaster::isWorktree('/tmp/review')) {
    echo "This is a linked worktree\n";
}

// Resolve the common git dir from any path
$common = Pitmaster::commonGitDir('/tmp/review');
// '/path/to/project/.git'

// Resolve a .git file to the actual git directory
$gitDir = WorktreeManager::resolveGitFile('/tmp/review');
// '/path/to/project/.git/worktrees/review'
```

## File layout

### Main worktree metadata for linked worktrees

```
.git/worktrees/<name>/
  HEAD         Per-worktree HEAD (symbolic ref or detached hash)
  commondir    Relative path to the shared git dir ("../..")
  gitdir       Absolute path back to the worktree's .git file
  locked       Present if locked (content = reason)
  index        Per-worktree index/staging area
```

### Linked worktree's .git file

```
gitdir: /path/to/project/.git/worktrees/<name>
```

### Resource sharing

| Resource | Location | Shared? |
|----------|----------|---------|
| Objects | `$commonDir/objects/` | Yes |
| Config | `$commonDir/config` | Yes |
| Packed-refs | `$commonDir/packed-refs` | Yes |
| Hooks | `$commonDir/hooks/` | Yes |
| HEAD | `$gitDir/HEAD` | No (per-worktree) |
| Index | `$gitDir/index` | No (per-worktree) |
| Loose refs | `$gitDir/refs/` | Partially (HEAD is local) |
