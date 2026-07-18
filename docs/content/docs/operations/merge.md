---
title: "Merge"
description: "Three-way merge with fast-forward detection, conflict markers, and merge base computation."
path: "operations/merge"
order: 50
section: "Operations"
meta_title: "Merge"
meta_description: "Three-way merge with fast-forward detection, conflict markers, and merge base computation."
---

# Merge

Pitmaster performs branch merges using the same three-way merge strategy as `git merge`. It detects fast-forward opportunities, computes merge bases, performs tree-level and content-level merges, and generates standard conflict markers when files conflict.

## Basic merge

```php
$result = $repo->merge('feature/login');

if ($result->clean) {
    echo "Merged successfully: {$result->commitId->hex}\n";
} else {
    echo "Conflicts:\n";
    foreach ($result->conflictPaths as $path) {
        echo "  {$path}\n";
    }
}
```

## MergeResult

The `MergeResult` class is a readonly value object:

```php
use Pitmaster\Merge\MergeResult;

$result->clean;           // bool: true if merge completed without conflicts
$result->commitId;        // ?ObjectId: the merge commit (null if conflicts)
$result->conflictPaths;   // array: list of file paths with conflicts
```

## Fast-forward merges

When the current HEAD is an ancestor of the target branch, Pitmaster performs a fast-forward merge: it simply moves HEAD to the target commit without creating a merge commit.

```
Before:         After fast-forward:

A---B (main)    A---B---C---D (main)
     \
      C---D (feature)
```

```php
$result = $repo->merge('feature');
// $result->clean === true
// $result->commitId points to D
// No merge commit created
```

## Three-way merge

When branches have diverged, Pitmaster performs a three-way merge:

1. Find the merge base (lowest common ancestor)
2. Diff base-to-ours and base-to-theirs at the tree level
3. For each file changed in both branches, perform content-level merge
4. Create a merge commit with two parents

```
Before:              After merge:

      C---D (feature)         C---D
     /                       /     \
A---B---E---F (main)   A---B---E---F---G (main)
```

## Merge base

The merge base is the most recent common ancestor of two commits. Pitmaster finds it using a breadth-first traversal of the commit graph.

```php
$base = $repo->mergeBase(
    $repo->resolve('main'),
    $repo->resolve('feature/login'),
);

if ($base !== null) {
    echo "Common ancestor: {$base->hex}\n";
}
```

For direct access to the `MergeBase` algorithm:

```php
use Pitmaster\Merge\MergeBase;

$finder = new MergeBase($repo->objectDatabase());
$base = $finder->find($commitA, $commitB);

// Check ancestry
$isAncestor = $finder->isAncestor($oldCommit, $newCommit);
```

## Branch merge check

```php
if ($repo->isBranchMerged('feature/done')) {
    // All commits in feature/done are reachable from default branch
    $repo->deleteBranch('feature/done');
}

// Check against a specific target
if ($repo->isBranchMerged('feature/done', 'develop')) {
    // Merged into develop
}
```

## Conflict markers

When a file is modified in both branches and the changes overlap, Pitmaster generates standard conflict markers:

```
<<<<<<< ours
    return $this->value + 1;
=======
    return $this->value * 2;
>>>>>>> theirs
```

The `ConflictMarker` class handles marker generation:

```php
use Pitmaster\Merge\ConflictMarker;

$marked = ConflictMarker::generate($oursContent, $theirsContent, 'main', 'feature');
```

## Content-level three-way merge

The `ThreeWayMerge` class merges blob content (not files):

```php
use Pitmaster\Merge\ThreeWayMerge;

$merger = new ThreeWayMerge();
$result = $merger->merge($baseContent, $oursContent, $theirsContent);
```

It takes three strings (the base version, our version, and their version) and produces:
- A merged string if changes do not overlap
- A string with conflict markers if changes conflict

## Merge strategies

### Recursive merge

The default strategy, handles criss-cross merge histories with multiple merge bases.

```php
use Pitmaster\Merge\RecursiveMerge;
```

### Ours merge

Always takes "our" side. Useful for maintaining branch history without incorporating changes.

```php
use Pitmaster\Merge\OursMerge;
```

### Octopus merge

Merges more than two branches simultaneously. Used when multiple feature branches need to be combined.

```php
use Pitmaster\Merge\OctopusMerge;
```

## Rerere (reuse recorded resolution)

Pitmaster supports git's rerere mechanism for remembering conflict resolutions:

```php
use Pitmaster\Merge\Rerere;
```

When enabled, conflict resolutions are recorded and automatically applied if the same conflict is encountered again.

## Tree-level merge flow

The full merge process:

1. `MergeBase::find()` locates the common ancestor
2. `TreeDiff::diff()` computes base-to-ours and base-to-theirs changes
3. For files changed only in one branch: take that change
4. For files changed in both branches:
   - If both changed the same way: take either (they agree)
   - If changes differ: run `ThreeWayMerge::merge()` on the content
5. If all merges are clean: build the merged tree and create a merge commit
6. If any conflicts: return `MergeResult` with `clean = false` and the conflict paths

## CLI usage

```bash
./bin/pitmaster merge feature/login
```

Output on success:
```
Merge successful.
```

Output on conflict:
```
CONFLICT in: src/File.php, src/Other.php
```
