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

Git stash saves working directory and index changes so you can switch to another task, then restore them later. Pitmaster implements the stash using `refs/stash` and the reflog, in git's storage layout. There is no stash method on `Repository`; use the `Stash` class below.

Two known problems: with more than one entry, `drop()` (and therefore `pop()`) leaves the entry in the list ([#10](https://github.com/inline0/pitmaster/issues/10)), and `apply()` overwrites uncommitted changes in files the stash does not touch ([#11](https://github.com/inline0/pitmaster/issues/11)). Apply stashes on a clean working tree.

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
// public function push(string $message = '', bool $includeUntracked = false): ObjectId
$stashId = $stash->push('Work in progress on login');
echo "Saved: {$stashId->hex}\n";

// Also stash untracked files
$stashId = $stash->push('With new files', includeUntracked: true);
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

After push, tracked files match HEAD. Untracked files stay unless `includeUntracked` is set; then they are removed and stored in the stash commit's own tree (git stores them in a third parent instead, so git sees them as tracked changes). `push()` creates an entry even when nothing changed, where git reports "No local changes to save".

### With default message

```php
$stashId = $stash->push();
// Message: "WIP on <branch>: <short hash> <HEAD subject>"
// With a message: "On <branch>: <message>"
```

On a detached HEAD, `<branch>` is `HEAD`.

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

Apply merges the stash into the current HEAD file by file (stash base, current HEAD, stash tree). The stash entry remains in the stack, and the index is not touched. Conflicts leave markers labelled `Updated upstream` and `Stashed changes` in the files and throw `Pitmaster\Exceptions\MergeConflictException`; `pop()` then keeps the entry. Apply does not check for local changes first (see the note at the top).

## List entries

```php
$entries = $stash->listEntries();

foreach ($entries as $entry) {
    echo "stash@{{$entry['index']}}: {$entry['message']}\n";
    // stash@{0}: On main: Work in progress on login
    // stash@{1}: WIP on main: f707601 Add login form
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

If dropping the only entry, `refs/stash` and its reflog are removed. With more entries, `drop()` currently only moves `refs/stash` and leaves the reflog unchanged, so the entry stays in `listEntries()` ([#10](https://github.com/inline0/pitmaster/issues/10)).

## Storage details

### Stash commit structure

```
commit <hash>
tree <worktree-tree-hash>
parent <HEAD-hash>
parent <index-commit-hash>
author <current identity>
committer <current identity>

<stash message>
```

### Index commit structure

```
commit <hash>
tree <index-tree-hash>
parent <HEAD-hash>
author <current identity>
committer <current identity>

index on <branch>: <short hash> <HEAD subject>
```

The identity comes from `GIT_COMMITTER_NAME`, then `GIT_AUTHOR_NAME`, then `user.name` in the repository config, then `Pitmaster` (and the same order for the email, ending in `pitmaster@example.invalid`). The date comes from `GIT_COMMITTER_DATE`, then `GIT_AUTHOR_DATE`, then the current time. Unlike commits, stash does not read the `PITMASTER_*` constants.

### Reflog storage

Stash entries are stored in `.git/logs/refs/stash`:

```
0000000000000000000000000000000000000000 abc123... Name <email> 1700000000 +0000	WIP on main: f707601 Add login form
abc123... def456... Name <email> 1700000001 +0000	On main: Work in progress on login
```

The reflog is read in chronological order. The stash list reverses it (newest first). The stash reflog is written regardless of `core.logallrefupdates`.

## CLI usage

```bash
# Save current changes
./vendor/bin/pitmaster stash
./vendor/bin/pitmaster stash push
./vendor/bin/pitmaster stash push "Work in progress on login"

# Apply and remove top entry
./vendor/bin/pitmaster stash pop

# Apply without removing
./vendor/bin/pitmaster stash apply

# List all entries
./vendor/bin/pitmaster stash list

# Remove top entry without applying
./vendor/bin/pitmaster stash drop
```

The message is the argument after `push` (or `save`); `-m` is not supported. `pop`, `apply`, and `drop` always work on `stash@{0}`; the CLI has no `--include-untracked`.

## Limitations

- Only full working directory stash (no `--keep-index`); untracked files only through `push($message, includeUntracked: true)`
- No `--patch` mode (interactive hunk selection)
- Pop/apply restores the working tree state; it does not restore the index. Staged edits come back as unstaged edits, and newly staged files come back as untracked files
- `stash@{n}` is not accepted by `Repository::resolve()`
- Stash in a linked worktree uses the wrong config and reflog location ([#14](https://github.com/inline0/pitmaster/issues/14))
