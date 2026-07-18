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

Pitmaster computes working tree status by comparing three states: the HEAD commit's tree, the index (staging area), and the working tree (actual files on disk). This three-way comparison produces the same output as `git status`.

## Basic usage

```php
$entries = $repo->status();

foreach ($entries as $entry) {
    echo "{$entry->index->value}{$entry->worktree->value} {$entry->path}\n";
}
```

Output matches git's short format:

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
```

## FileStatus enum

```php
use Pitmaster\Status\FileStatus;

FileStatus::Added;      // 'A' - new file
FileStatus::Modified;   // 'M' - content changed
FileStatus::Deleted;    // 'D' - file removed
FileStatus::Renamed;    // 'R' - file renamed
FileStatus::Copied;     // 'C' - file copied
FileStatus::Untracked;  // '?' - not in index
FileStatus::Ignored;    // '!' - matched by .gitignore
FileStatus::Unmodified; // ' ' - no change
```

## Porcelain v2 output

For machine-readable status output matching `git status --porcelain=v2`:

```php
echo $repo->statusPorcelainV2();
```

Output:

```
1 M. N... 000000 000000 000000 0000...0000 0000...0000 modified-file.txt
? untracked-file.txt
! ignored-file.log
```

## How status is computed

The `WorkingTreeStatus` class performs the three-way comparison:

```php
use Pitmaster\Status\WorkingTreeStatus;

$status = new WorkingTreeStatus($repo->objectDatabase(), $repo->workDir());
$entries = $status->compute($repo->index(), $headCommitId);
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
- On disk but not in index: `Untracked` in worktree column
- In both but content differs: `Modified` in worktree column
- In both with matching content: `Unmodified` in worktree column

### Step 4: Detect untracked files

Walk the working tree directory, skip `.git/` and ignored paths, and report files not present in the index as `Untracked`.

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
./bin/pitmaster status
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

For large repositories, Pitmaster supports the fsmonitor extension to skip stat checks on files that have not changed:

```php
use Pitmaster\Status\Fsmonitor;
```

The fsmonitor integrates with the index extension to track which files were modified since the last status check, reducing the number of filesystem stat calls needed.
