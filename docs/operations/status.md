---
title: "Status"
description: "Compare HEAD, index, and working tree to determine file status."
path: "operations/status"
order: 60
section: "Operations"
meta_title: "Status"
meta_description: "Compare HEAD, index, and working tree to determine file status."
---

# Status

Pitmaster computes working tree status by comparing three states: the HEAD commit's tree, the index (staging area), and the working tree (actual files on disk). The CLI prints a `git status`-style summary from it, and `statusPorcelainV2()` prints git's porcelain v2 format.

## Basic usage

```php
$entries = $repo->status();

foreach ($entries as $entry) {
    echo $entry->shortFormat() . "\n";
}
```

`shortFormat()` prints git's short format:

```
M  src/Modified.php          (staged modification)
 M src/WorktreeModified.php  (unstaged modification)
A  src/NewFile.php           (staged new file)
 D src/Deleted.php           (deleted in worktree)
?? untracked.txt             (untracked file)
```

## StatusEntry

Each file's status is represented by a `StatusEntry`:

```php
use Pitmaster\Status\StatusEntry;

$entry->path;       // File path relative to repo root
$entry->index;      // FileStatus: HEAD-to-index state
$entry->worktree;   // FileStatus: index-to-worktree state
$entry->origPath;   // Original path for renamed entries, otherwise null
$entry->renameScore; // Similarity score for renamed entries, otherwise null
$entry->shortFormat(); // "XY path", "?? path", or "R  old -> new"
```

## FileStatus enum

```php
use Pitmaster\Status\FileStatus;

FileStatus::Added;      // 'A' - new file
FileStatus::Modified;   // 'M' - content changed
FileStatus::Deleted;    // 'D' - file removed
FileStatus::Renamed;    // 'R' - file renamed
FileStatus::Copied;     // 'C' - file copied
FileStatus::Unmerged;   // 'U' - unresolved merge conflict
FileStatus::Untracked;  // '?' - not in index
FileStatus::Ignored;    // '!' - matched by .gitignore
FileStatus::Unmodified; // ' ' - no change
```

`status()` does not report ignored files, so `Ignored` does not appear in its result.

## Porcelain v2 output

For machine-readable status output matching `git status --porcelain=v2`:

```php
echo $repo->statusPorcelainV2();
```

Output:

```
1 .M N... 100644 100644 100644 ce013625030ba8dba906f756967f9e9ca394464a ce013625030ba8dba906f756967f9e9ca394464a modified-file.txt
? untracked-file.txt
```

Tracked entries come first, then untracked ones. Renames use `2` lines and conflicts `u` lines. The `# branch` headers of `git status --porcelain=v2 --branch` are not printed, and ignored files are not listed.

## How status is computed

The `WorkingTreeStatus` class performs the three-way comparison:

```php
use Pitmaster\Status\WorkingTreeStatus;

$status = new WorkingTreeStatus($repo->objectDatabase(), $repo->workDir(), $repo->gitDir());
$entries = $status->compute($repo->index(), $repo->resolve('HEAD'));
```

### Step 1: Build HEAD tree map

Flatten the HEAD commit's tree into a map of `path => blob hash`. This represents the last committed state.

### Step 2: Compare HEAD to index

For each file in the HEAD tree and the index:
- In HEAD but not in index: `Deleted` in index column
- In index but not in HEAD: `Added` in index column
- In both but different hash: `Modified` in index column
- In both with same hash: `Unmodified` in index column

### Step 3: Compare index to worktree

For each file in the index and the working tree:
- In index but not on disk: `Deleted` in worktree column
- In both but content differs: `Modified` in worktree column
- In both with matching content: `Unmodified` in worktree column

### Step 4: Detect untracked files

Walk the working tree directory, skip `.git/` and ignored paths, and report files not present in the index as `Untracked` in both columns.

## .gitignore support

Pitmaster parses `.gitignore` files to determine which untracked files should be reported as ignored vs untracked.

```php
use Pitmaster\Status\GitIgnore;
```

The `GitIgnore` class parses gitignore patterns from:
- `.gitignore` in the repository root
- `.gitignore` in subdirectories
- `.git/info/exclude`

Pattern syntax supported:
- Glob patterns (`*.log`, `build/`)
- Negation (`!important.log`)
- Directory-only patterns (trailing `/`)
- Leading `/` for root-relative patterns
- `**` for recursive matching

## Categorizing status entries

A common pattern for UI display:

```php
$staged = [];
$unstaged = [];
$untracked = [];

foreach ($repo->status() as $entry) {
    if ($entry->index === FileStatus::Untracked) {
        $untracked[] = $entry;
    } elseif ($entry->index !== FileStatus::Unmodified) {
        $staged[] = $entry;
    }

    if ($entry->worktree !== FileStatus::Unmodified
        && $entry->index !== FileStatus::Untracked
    ) {
        $unstaged[] = $entry;
    }
}
```

## CLI usage

```bash
./vendor/bin/pitmaster status
```

Output:

```
On branch main

Changes to be committed:
	new file:   src/Feature.php

Changes not staged for commit:
	modified:   src/Existing.php

Untracked files:
	notes.txt
```

## Fsmonitor

Pitmaster includes a helper for git's fsmonitor protocol:

```php
use Pitmaster\Status\Fsmonitor;
```

The `Fsmonitor` class can query the `core.fsmonitor` hook (falling back to a modification-time scan) to learn which files changed since a token. It turns itself off on handles opened with `['processes' => false]`. `status()` does not call it at the moment; it compares stat data and content directly.
