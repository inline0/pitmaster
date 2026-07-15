---
title: "Diff"
description: "Compute diffs between trees, index, and working tree using Myers O(ND) algorithm."
path: "operations/diff"
order: 6
section: "Operations"
meta_title: "Diff"
meta_description: "Compute diffs between trees, index, and working tree using Myers O(ND) algorithm."
---

# Diff

Pitmaster computes diffs that are byte-exact with `git diff` output. The diff engine supports multiple algorithms, unified diff formatting, binary file detection, and tree-level recursive comparison.

## Diff operations

### Unstaged changes (worktree vs index)

```php
$diffs = $repo->diff();

foreach ($diffs as $diff) {
    echo $diff->format();
}
```

Compares each file in the index against its current state in the working tree. Detects modifications, deletions, and binary files.

### Staged changes (index vs HEAD)

```php
$diffs = $repo->diffStaged();

foreach ($diffs as $diff) {
    echo $diff->format();
}
```

Compares the current index against the HEAD commit's tree. Shows what will be included in the next commit.

### Tree-to-tree diff

```php
$a = $repo->resolve('HEAD~1');
$b = $repo->resolve('HEAD');
$diffs = $repo->diffTree($a, $b);
```

Compares two trees recursively. Used internally by `show()`, `logPath()`, and the merge engine.

### Filter by path

```php
$diffs = $repo->diff('src/specific-file.php');
$diffs = $repo->diffStaged('src/specific-file.php');
```

## DiffResult structure

Each `DiffResult` represents the diff for a single file.

```php
use Pitmaster\Diff\DiffResult;

foreach ($diffs as $diff) {
    echo $diff->oldPath;    // Path in the old tree
    echo $diff->newPath;    // Path in the new tree
    echo $diff->binary;     // true for binary files
    echo $diff->oldHash;    // SHA-1 of old blob (nullable)
    echo $diff->newHash;    // SHA-1 of new blob (nullable)

    foreach ($diff->hunks as $hunk) {
        echo $hunk->header();  // @@ -1,5 +1,7 @@
        foreach ($hunk->lines as $line) {
            echo $line . "\n"; // ' context', '+added', '-removed'
        }
    }
}
```

### Formatted output

```php
echo $diff->format();
```

Produces unified diff output matching `git diff`:

```
diff --git a/src/File.php b/src/File.php
index abc1234..def5678 100644
--- a/src/File.php
+++ b/src/File.php
@@ -10,6 +10,8 @@ class File
     private string $name;

+    private int $count;
+
     public function __construct()
     {
```

### Check for changes

```php
if ($diff->hasChanges()) {
    // File was modified (or is binary with changes)
}
```

## Hunk structure

Each `Hunk` represents a contiguous region of changes with context lines.

```php
use Pitmaster\Diff\Hunk;

$hunk->oldStart;   // Starting line in old file
$hunk->oldCount;   // Number of lines from old file
$hunk->newStart;   // Starting line in new file
$hunk->newCount;   // Number of lines in new file
$hunk->lines;      // Array of diff lines (prefixed with ' ', '+', or '-')
$hunk->header();   // "@@ -10,6 +10,8 @@"
```

## Diff algorithms

Pitmaster implements four diff algorithms. The default is Myers.

### Myers diff

The standard O(ND) algorithm, same as git's default. Produces minimal edit scripts.

```php
use Pitmaster\Diff\MyersDiff;

$hunks = MyersDiff::diff($oldContent, $newContent);
```

The `diff()` method takes two strings and returns an array of `Hunk` objects. It splits the input on newlines, computes the shortest edit script, and groups changes into hunks with 3 lines of context (matching git's default).

### Patience diff

Better structural diffs for code. Matches unique lines first, then fills in between them.

```php
use Pitmaster\Diff\PatienceDiff;

$hunks = PatienceDiff::diff($oldContent, $newContent);
```

### Histogram diff

A variant of patience diff that handles repeated lines better. Used by JGit and Eclipse.

```php
use Pitmaster\Diff\HistogramDiff;

$hunks = HistogramDiff::diff($oldContent, $newContent);
```

### Minimal diff

Produces the smallest possible diff (fewest changed lines), at the cost of more computation.

```php
use Pitmaster\Diff\MinimalDiff;

$hunks = MinimalDiff::diff($oldContent, $newContent);
```

## Binary detection

Pitmaster detects binary files the same way git does: by checking for null bytes in the first 8000 bytes of content.

```php
if (MyersDiff::isBinary($content)) {
    // Binary file, skip line-level diff
}
```

Binary files produce a `DiffResult` with `$binary = true` and an empty `$hunks` array. The formatted output is:

```
diff --git a/image.png b/image.png
Binary files differ
```

## Colorized output

The CLI uses `ColorDiff` for terminal output with ANSI colors.

```php
use Pitmaster\Diff\ColorDiff;

$colorized = ColorDiff::colorize($diff->format());
echo $colorized;
```

- Added lines (`+`) are shown in green
- Removed lines (`-`) are shown in red
- Hunk headers (`@@`) are shown in cyan
- File headers (`diff --git`) are shown in bold

## Tree diff (internal)

The `TreeDiff` class compares two tree objects recursively, producing a `DiffResult` for each changed file.

```php
use Pitmaster\Diff\TreeDiff;

$treeDiff = new TreeDiff($repo->objectDatabase());
$diffs = $treeDiff->diff($oldTreeId, $newTreeId);
```

Tree diff handles:
- Added files (present in new tree only)
- Deleted files (present in old tree only)
- Modified files (same path, different blob hash)
- Recursive subdirectory comparison

## Word diff

For inline word-level changes within lines.

```php
use Pitmaster\Diff\WordDiff;

$result = WordDiff::diff($oldLine, $newLine);
```
