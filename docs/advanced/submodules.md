---
title: "Submodules"
description: "Git submodule support with gitlink entries, .gitmodules parsing, and status tracking."
path: "advanced/submodules"
order: 150
section: "Advanced"
meta_title: "Submodules"
meta_description: "Git submodule support with gitlink entries, .gitmodules parsing, and status tracking."
---

# Submodules

Git submodules embed one repository inside another. A submodule is represented in the parent tree as a gitlink entry (mode `160000`) pointing to a specific commit in the submodule repository. The submodule's URL and path are declared in the `.gitmodules` file.

## Concepts

In the parent repository's tree:

```
100644 blob abc123...  README.md
040000 tree def456...  src/
160000 commit 789012... lib/external    <-- gitlink entry (submodule)
```

The gitlink entry records which commit of the submodule should be checked out. The actual submodule repository lives in `.git/modules/<name>/`.

## SubmoduleManager

```php
use Pitmaster\Submodule\SubmoduleManager;

$manager = new SubmoduleManager(
    $repo->objectDatabase(),
    $repo->workDir(),
    $repo->gitDir(),
);
```

### List submodules

```php
$submodules = $manager->list();

foreach ($submodules as $sub) {
    echo "{$sub->name}: {$sub->url} at {$sub->path}\n";
    if ($sub->branch !== null) {
        echo "  tracks branch: {$sub->branch}\n";
    }
}
```

### Submodule value object

```php
use Pitmaster\Submodule\Submodule;

$sub->name;     // Submodule name (from .gitmodules section header)
$sub->path;     // Path in the working tree
$sub->url;      // Remote URL
$sub->branch;   // Tracked branch (nullable)
```

## Parsing .gitmodules

The `.gitmodules` file declares submodule configuration:

```ini
[submodule "lib/external"]
    path = lib/external
    url = https://github.com/vendor/lib.git
    branch = main

[submodule "tools/helper"]
    path = tools/helper
    url = https://github.com/vendor/helper.git
```

```php
$content = file_get_contents('.gitmodules');
$submodules = SubmoduleManager::parseGitmodules($content);

foreach ($submodules as $sub) {
    echo "{$sub->name} -> {$sub->url}\n";
}
```

The parser handles:
- Section headers: `[submodule "<name>"]`
- Key-value pairs: `path = ...`, `url = ...`, `branch = ...`
- Comments (lines starting with `#`)
- Whitespace and indentation

Entries without both `path` and `url` are skipped. Values are not unquoted.

## Gitlink entries

Gitlink entries are tree entries with mode `160000`. They point to a commit (not a tree or blob) in the submodule repository.

```php
$tree = $repo->readObject($treeHash);

foreach ($tree->entries as $entry) {
    if ($entry->isGitlink()) {
        echo "Submodule at {$entry->name}: commit {$entry->hash->hex}\n";
    }
}
```

### TreeEntry detection

```php
$entry->isGitlink();  // true if mode is '160000'
$entry->mode;         // '160000'
```

## Initializing submodules

```php
$manager->init();
```

For each submodule declared in `.gitmodules`, this:
1. Creates an empty `.git/modules/<name>/` directory
2. Creates the submodule path directory if it does not exist
3. Records `submodule.<name>.active = true` and `submodule.<name>.url` in `.git/config`

It does not fetch or check out anything, and it does not write the submodule's `.git` file.

## Updating submodules

```php
$manager->update($repo->head()->tree);
```

For each submodule pinned in the given tree, `update()` copies the source repository's git directory into `.git/modules/<name>/`, replaces the submodule's working files with the source's, writes the `.git` file pointing to the module directory, sets `core.worktree`, and detaches HEAD at the pinned commit.

The submodule URL must be a local path: absolute, or relative to the parent's working tree (git resolves relative URLs against the parent's remote URL instead). A URL with a scheme (`https://...`) throws `RuntimeException('Submodule update currently supports only local paths: ...')`.

After an update, you can open each submodule as a separate repository:

```php
$subRepo = Pitmaster::open($repo->workDir() . '/lib/external');
```

## Checking pinned commits

```php
$headCommit = $repo->head();
$pinnedId = $manager->pinnedCommit('lib/external', $headCommit->tree);

if ($pinnedId !== null) {
    echo "lib/external is pinned to commit {$pinnedId->hex}\n";
}
```

The `pinnedCommit()` method walks the tree hierarchy to find the gitlink entry at the given path and returns its target commit hash.

## Submodule status

```php
$headCommit = $repo->head();
$statuses = $manager->status($headCommit->tree);

foreach ($statuses as $status) {
    $expected = $status['expected'] ?? 'not pinned';
    $actual = $status['actual'] ?? 'not initialized';
    $dirty = $status['dirty'] ? ' (dirty)' : '';

    echo "{$status['name']}: expected={$expected}, actual={$actual}{$dirty}\n";
}
```

Status fields:
- `name`: submodule name
- `path`: path in the working tree
- `expected`: commit hash pinned in the parent tree (null if not present)
- `actual`: current HEAD of the submodule (null if not initialized)
- `dirty`: true when both commits are known and differ. Changes inside the submodule's working tree are not checked.

## Directory structure

```
project/
  .git/
    modules/
      lib/
        external/          # Submodule's actual git directory (.git/modules/<name>)
        HEAD
        objects/
        refs/
        ...
  .gitmodules              # Submodule declarations
  lib/
    external/
      .git                 # File: "gitdir: ../../.git/modules/lib/external"
      src/
      README.md
  src/
  README.md
```

## Submodule workflow

```php
// 1. Open the parent repo
$repo = Pitmaster::open('/path/to/project');

// 2. List submodules
$manager = new SubmoduleManager(
    $repo->objectDatabase(),
    $repo->workDir(),
    $repo->gitDir(),
);

$submodules = $manager->list();

// 3. Populate the submodules pinned at HEAD (local source paths only)
$manager->update($repo->head()->tree);

// 4. Check status
$statuses = $manager->status($repo->head()->tree);

// 5. Open a submodule as its own repo
$subRepo = Pitmaster::open($repo->workDir() . '/lib/external');
$subHead = $subRepo->head();
echo "Submodule HEAD: {$subHead->id->hex}\n";
```
