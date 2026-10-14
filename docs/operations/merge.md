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

Pitmaster performs branch merges with a three-way merge, like `git merge`. It detects fast-forward opportunities, computes merge bases, performs tree-level and content-level merges, and generates standard conflict markers when files conflict.

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
$result->mergedContents;  // array: path => content with conflict markers (if conflicts)
```

When the merge stops on conflicts, the conflict state is written to the index and working tree. Resolve the files, `add()` them, and call `$repo->mergeContinue()`, or call `$repo->mergeAbort()` to restore the pre-merge state.

Before merging, commit or stash local changes: the merge rebuilds the working tree from the result and overwrites uncommitted changes in tracked files ([#2](https://github.com/inline0/pitmaster/issues/2)).

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

The reverse case is not detected yet: merging a branch that HEAD already contains creates a new merge commit with an unchanged tree instead of reporting "Already up to date" ([#6](https://github.com/inline0/pitmaster/issues/6)).

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

The merge base is the most recent common ancestor of two commits. Pitmaster finds it by walking the commit graph. When there are several merge bases (criss-cross history), `merge()` merges them first and uses the result as the base.

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

// All merge bases (criss-cross histories can have more than one)
$bases = $finder->findAll($commitA, $commitB);

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

When a file is modified in both branches and the changes overlap, Pitmaster generates standard conflict markers. `merge()` labels the sides `HEAD` and the merged branch name:

```
<<<<<<< HEAD
    return $this->value + 1;
=======
    return $this->value * 2;
>>>>>>> feature
```

The `ConflictMarker` class wraps two versions in markers:

```php
use Pitmaster\Merge\ConflictMarker;

$marked = ConflictMarker::mark($oursContent, $theirsContent, 'HEAD', 'feature');

// diff3 style, with the base section
$marked = ConflictMarker::mark($oursContent, $theirsContent, 'HEAD', 'feature', $baseContent, 'base');
```

## Content-level three-way merge

The `ThreeWayMerge` class merges blob content (not files):

```php
use Pitmaster\Merge\ThreeWayMerge;

$result = ThreeWayMerge::merge($baseContent, $oursContent, $theirsContent, 'HEAD', 'feature');

$result['content'];   // merged text, with conflict markers if needed
$result['clean'];     // true when nothing conflicted
$result['conflicts']; // number of conflict regions
```

It takes three strings (the base version, our version, and their version) plus optional labels, and a conflict style (`'merge'` by default, or `'diff3'` to include the base section).

## Merge strategies

### Recursive merge

The default strategy (`$repo->merge($branch)`), handles criss-cross merge histories with multiple merge bases. The CLI also accepts `-s ort` and `-s recursive` for it.

### Ours merge

Records a merge commit that keeps HEAD's tree unchanged. Useful for maintaining branch history without incorporating changes.

```php
$result = $repo->merge('feature/old', 'ours');
```

### Octopus merge

Merges two or more branches into a single merge commit. If any branch conflicts, it returns an unclean result without writing merge state or creating a commit.

```php
$result = $repo->mergeOctopus(['feature/a', 'feature/b', 'feature/c']);
```

The `RecursiveMerge`, `OursMerge`, and `OctopusMerge` classes in `Pitmaster\Merge` are content-level helpers; `Repository` implements the strategies above itself.

## Rerere (reuse recorded resolution)

Pitmaster includes a standalone `Rerere` class that stores resolutions in git's `rr-cache` layout:

```php
use Pitmaster\Merge\Rerere;

$rerere = new Rerere($repo->gitDir());

if ($rerere->isEnabled()) {                     // rerere.enabled = true in .git/config
    $rerere->record($conflicted, $resolved);    // remember a resolution
    $resolved = $rerere->resolve($conflicted);  // replay it, or null
}
```

`merge()` does not call it: resolutions are recorded and replayed only when your code does so. `isEnabled()` looks for the literal text `rerere.enabled = true` in `.git/config` and does not recognise the `[rerere]` section that `git config rerere.enabled true` writes ([#20](https://github.com/inline0/pitmaster/issues/20)).

## Tree-level merge flow

The full merge process:

1. `MergeBase::findAll()` locates the common ancestors
2. The base, ours, and theirs trees are compared path by path
3. For files changed only in one branch: take that change
4. For files changed in both branches:
   - If both changed the same way: take either (they agree)
   - If changes differ: run `ThreeWayMerge::merge()` on the content
5. If all merges are clean: build the merged tree and create a merge commit
6. If any conflicts: return `MergeResult` with `clean = false` and the conflict paths

## CLI usage

```bash
./vendor/bin/pitmaster merge feature/login
```

Output on success:
```
Merge successful.
```

Output on conflict:
```
CONFLICT in: src/File.php, src/Other.php
```
