---
title: "Stash"
description: "Save and restore working directory changes using the stash stack."
path: "advanced/stash"
order: 160
section: "Advanced"
meta_title: "Stash"
meta_description: "Save and restore working directory changes using the stash stack."
---

# Stash

Git stash saves working directory and index changes so you can switch to another task, then restore them later. Pitmaster implements the stash using `refs/stash` and the reflog, matching git's behavior.

## How stash works

Each stash entry is a commit with two parents:
- **Parent 0**: the HEAD commit at the time of stash (the base state)
- **Parent 1**: a commit capturing the index state (staged changes)

The stash commit's own tree captures the working tree state (including unstaged changes).

The stash "stack" is the reflog of `refs/stash`. The most recent stash is `refs/stash` itself. Older entries are reflog entries: `stash@{0}`, `stash@{1}`, etc.

```
refs/stash -> stash commit (worktree state)
                parent[0] -> HEAD at time of stash
                parent[1] -> index state commit
                               parent[0] -> HEAD at time of stash
```

## Creating the Stash object

```php
use Pitmaster\Stash\Stash;

$stash = new Stash(
    $repo->objectDatabase(),
    $repo->refDatabase(),
    $repo->gitDir(),
    $repo->workDir(),
);
```

## Push (save changes)

```php
$stashId = $stash->push('Work in progress on login');
echo "Saved: {$stashId->hex}\n";
```

Push does the following:
1. Reads the current HEAD commit
2. Builds a tree from the current index (staged state)
3. Creates an "index commit" with the HEAD as parent
4. Builds a tree from the current working directory (including unstaged changes)
5. Creates the stash commit (worktree tree, parents = HEAD + index commit)
6. Updates `refs/stash` to point to the stash commit
7. Appends a reflog entry for the stack
8. Resets the working tree and index to HEAD

After push, the working directory is clean (matching HEAD).

### With default message

```php
$stashId = $stash->push();
// Message defaults to "WIP on <branch>"
```

## Pop (apply and remove)

```php
$stash->pop();
```

Applies the top stash entry to the working directory and removes it from the stack. Equivalent to `apply()` followed by `drop()`.

### Pop a specific entry

```php
$stash->pop(1);  // Pop stash@{1}
```

## Apply (restore without removing)

```php
$stash->apply();      // Apply stash@{0}
$stash->apply(2);     // Apply stash@{2}
```

Apply reads the stash commit's tree and writes all files to the working directory. The stash entry remains in the stack.

## List entries

```php
$entries = $stash->listEntries();

foreach ($entries as $entry) {
    echo "stash@{{$entry['index']}}: {$entry['message']}\n";
    // stash@{0}: Work in progress on login
    // stash@{1}: WIP on main
}
```

Each entry contains:
- `index`: the stash position (0 = most recent)
- `message`: the stash message
- `hash`: the stash commit hash

## Drop (remove without applying)

```php
$stash->drop();       // Drop stash@{0}
$stash->drop(1);      // Drop stash@{1}
```

If dropping the only entry, `refs/stash` and its reflog are removed. Otherwise, the reflog is rebuilt without the dropped entry and `refs/stash` is updated to point to the new top.

## Storage details

### Stash commit structure

```
commit <hash>
tree <worktree-tree-hash>
parent <HEAD-hash>
parent <index-commit-hash>
author <committer from HEAD>
committer <committer from HEAD>

<stash message>
```

### Index commit structure

```
commit <hash>
tree <index-tree-hash>
parent <HEAD-hash>
author <committer from HEAD>
committer <committer from HEAD>

index on <branch>: <message>
```

### Reflog storage

Stash entries are stored in `.git/logs/refs/stash`:

```
0000000000000000000000000000000000000000 abc123... Name <email> 1700000000 +0000	WIP on main
abc123... def456... Name <email> 1700000001 +0000	Work in progress on login
```

The reflog is read in chronological order. The stash list reverses it (newest first).

## CLI usage

```bash
# Save current changes
./bin/pitmaster stash
./bin/pitmaster stash push
./bin/pitmaster stash push "Work in progress on login"

# Apply and remove top entry
./bin/pitmaster stash pop

# Apply without removing
./bin/pitmaster stash apply

# List all entries
./bin/pitmaster stash list

# Remove top entry without applying
./bin/pitmaster stash drop
```

## Limitations

- Only full working directory stash (no `--keep-index` or `--include-untracked`)
- No `--patch` mode (interactive hunk selection)
- Pop/apply restores the working tree state; it does not separately restore the index state (staged changes are merged into the working tree)
