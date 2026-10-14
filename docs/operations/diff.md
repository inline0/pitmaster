---
title: "Diff"
description: "Compute diffs between trees, index, and working tree using Myers O(ND) algorithm."
path: "operations/diff"
order: 40
section: "Operations"
meta_title: "Diff"
meta_description: "Compute diffs between trees, index, and working tree using Myers O(ND) algorithm."
---

# Diff

Pitmaster computes diffs in git's unified format. The diff engine supports multiple algorithms, unified diff formatting, binary file detection, and tree-level recursive comparison.

`format()` includes Git's new/deleted file modes, object IDs, `/dev/null` labels, zero-length hunk ranges, mode changes, binary paths, and rename headers. The CLI emits colour when its output is a terminal and plain text when redirected.

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

Compares the current index against the HEAD commit's tree. Shows what will be included in the next commit. Deleted and added files with similar content are reported as one renamed entry. Before the first commit, the index is compared with an empty tree, so staged files appear as additions.

Rename detection first pairs identical blobs, then compares copied bytes in newline-delimited spans of at most 64 bytes. Repeated spans count only up to their occurrences in both files; a trailing newline does not create an extra empty line. Inexact renames require at least 50% similarity.

### Tree-to-tree diff

```php
$a = $repo->readObject($repo->resolve('HEAD~1')->hex)->tree;
$b = $repo->head()->tree;
$diffs = $repo->diffTree($a, $b);
```

Compares two trees recursively. Pass tree IDs: commit IDs are not peeled to their trees and give an empty result. The same `TreeDiff` engine backs `diffStaged()`, `show()`, and `logPath()`.

### Filter by path

```php
$diffs = $repo->diff('src/specific-file.php');
```

The pathspec is one exact file path. `diffStaged('src/specific-file.php')` returns entries whose old or new path matches that file, including a rename with that endpoint.

### Choose an algorithm

```php
$diffs = $repo->diff(null, 'patience');
$diffs = $repo->diffStaged(null, 'histogram');
$diffs = $repo->diffTree($a, $b, 'minimal');
```

Valid names are `myers` (default), `patience`, `histogram`, and `minimal`. Any other name throws `InvalidArgumentException`.

## DiffResult structure

Each `DiffResult` represents the diff for a single file.

```php
use Pitmaster\Diff\DiffResult;

foreach ($diffs as $diff) {
    echo $diff->oldPath;    // Path in the old tree
    echo $diff->newPath;    // Path in the new tree
    echo $diff->binary;     // true for binary files
    echo $diff->oldHash;    // Hex hash of the old blob (null for added files)
    echo $diff->newHash;    // Hex hash of the new blob (null for deleted files)
    echo $diff->oldMode;    // Old Git file mode (null for added files)
    echo $diff->newMode;    // New Git file mode (null for deleted files)
    echo $diff->similarity; // Rename similarity percentage (null otherwise)

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

Produces unified diff output. For a modified text file it matches `git diff`:

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

Pitmaster treats content as binary when it contains a NUL byte in its first 8192 bytes (git checks the first 8000).

```php
if (MyersDiff::isBinary($content)) {
    // Binary file, skip line-level diff
}
```

Binary files produce a `DiffResult` with `$binary = true` and an empty `$hunks` array. The formatted output is:

```
diff --git a/image.png b/image.png
index <old>..<new> 100644
Binary files a/image.png and b/image.png differ
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
- File headers (`diff --git`, `index`, `---`, `+++`) are shown in bold

`pitmaster diff` always colorizes, also when its output is piped. `pitmaster show` colorizes only when stdout is a terminal.

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
- Rename pairing between deleted and added files (exact hash match, or at least 50% shared lines)

Entries whose hash is unchanged are skipped, so a mode change alone does not produce a result.

## Word diff

For inline word-level changes within lines.

```php
use Pitmaster\Diff\WordDiff;

$result = WordDiff::diff('the quick fox', 'the slow fox');
// 'the [-quick-]{+slow+} fox'
```
