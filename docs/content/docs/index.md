---
title: "Pitmaster"
description: "Pure PHP Git implementation. Read and write Git repositories without shelling out to git."
path: "."
order: 0
section: "Getting Started"
meta_title: "Pitmaster"
meta_description: "Pure PHP Git implementation. Read and write Git repositories without shelling out to git."
---

# Pitmaster

Pitmaster is a pure PHP library that reads and writes Git repositories natively. Core repository operations do not shell out to the `git` binary or use FFI. The Git object model, pack file format, index format, and smart HTTP transport are implemented in PHP.

## Why Pitmaster exists

PHP applications that interact with Git repositories typically shell out to the `git` binary. This works, but it creates a hard dependency on `git` being installed, introduces security concerns around `exec()` and `proc_open()`, and makes the application harder to deploy in restricted environments (containers without git, shared hosting, serverless functions).

Pitmaster eliminates the `git` dependency entirely. Your PHP application can read commit history, create commits, compute diffs, check status, and push to remotes using only PHP's built-in functions.

## What it does

- Reads and writes loose objects (blob, tree, commit, tag)
- Reads pack files and resolves delta chains (OFS_DELTA and REF_DELTA)
- Writes pack files for network operations
- Reads and writes the index/staging area (v2, v3, v4 formats)
- Reads and writes refs (branches, tags, HEAD, packed-refs, reftable)
- Computes diffs (Myers, Patience, Histogram, and Minimal algorithms)
- Performs three-way merges with conflict detection
- Walks commit graphs (log, ancestry, merge base)
- Speaks the Git smart HTTP protocol (clone, fetch, push)
- Supports worktrees, submodules, stash, hooks, and Git LFS

## What it requires

- PHP 8.2 or later
- `zlib` extension (built-in on virtually all PHP installs)
- `mbstring` extension (for UTF-8 path handling)
- `json` extension

Core repository operations do not require the `git` binary. Optional features may need extra runtime capabilities: hook execution uses `proc_open()`, and SSH transport requires `ext-ssh2`.

## Quick example

```php
use Pitmaster\Pitmaster;

$repo = Pitmaster::open('/path/to/project');

// Read the current HEAD commit
$head = $repo->head();
echo $head->message;

// List branches
foreach ($repo->branches() as $branch) {
    echo $branch . "\n";
}

// Check status
foreach ($repo->status() as $entry) {
    echo "{$entry->path}: {$entry->index->value}{$entry->worktree->value}\n";
}

// Create a commit
$repo->add('src/Feature.php');
$commitId = $repo->commit('Add new feature');
```

Start with [Getting Started](/docs/getting-started), then use [API](/docs/api), [CLI](/docs/cli), and [Operations](/docs/operations/read) for the rest of the surface area.
