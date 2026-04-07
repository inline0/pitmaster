<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="./.github/logo-dark.svg">
    <source media="(prefers-color-scheme: light)" srcset="./.github/logo-light.svg">
    <img alt="Pitmaster" src="./.github/logo-light.svg" height="50">
  </picture>
</p>

<p align="center">
  Pure PHP Git implementation
</p>

<p align="center">
  <a href="https://github.com/inline0/pitmaster/actions/workflows/ci.yml"><img src="https://github.com/inline0/pitmaster/actions/workflows/ci.yml/badge.svg" alt="CI"></a>
  <a href="https://packagist.org/packages/pitmaster/pitmaster"><img src="https://img.shields.io/packagist/v/pitmaster/pitmaster.svg" alt="Packagist"></a>
  <a href="https://github.com/inline0/pitmaster/blob/main/LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="license"></a>
</p>

---

## What is Pitmaster?

Pitmaster reads and writes Git repositories in pure PHP. No `exec('git ...')`, no FFI, no extensions beyond what ships with every PHP install (`zlib`, `mbstring`, `json`). The canonical `git` binary is the oracle: if `git` accepts what Pitmaster writes, and Pitmaster can read what `git` writes, it is correct.

**The problem:** PHP applications that need to interact with Git repositories either shell out to the `git` binary (requires `exec()`, hard to deploy, security surface) or use FFI/extension bindings (complex setup, version coupling). There's no way to read a pack file, create a commit, or diff two trees from pure PHP.

**Pitmaster solves this** by implementing the Git object model, binary formats, and protocols natively:

- Read and write loose objects (blob, tree, commit, tag)
- Read and write pack files with full delta chain resolution
- Read and write the index (staging area)
- Compute diffs (Myers O(ND) algorithm, byte-exact with `git diff`)
- Three-way merge with conflict markers
- Walk commit graphs, compute merge bases
- Speak the Git smart HTTP protocol (clone, fetch, push)

## Quick Start

```bash
composer require pitmaster/pitmaster
```

```php
use Pitmaster\Pitmaster;

// Open an existing repository
$repo = Pitmaster::open('/path/to/project');

// Read
$head = $repo->head();                    // Current HEAD commit
$log  = $repo->log(10);                   // Last 10 commits
$refs = $repo->allRefs();                 // All branches and tags
$obj  = $repo->readObject($hash);         // Any object by hash

// Write
$repo->add('src/main.php');               // Stage a file
$repo->commit("Fix the bug\n");           // Create a commit
$repo->createBranch('feature');           // Create a branch
$repo->merge('feature');                  // Merge a branch

// Diff
$diffs = $repo->diff();                   // Unstaged changes
$diffs = $repo->diffStaged();             // Staged changes
$diffs = $repo->diffTree($treeA, $treeB); // Tree-to-tree

// Status
$status = $repo->status();                // WorkingTreeStatus
foreach ($status as $entry) {
    echo $entry->shortFormat() . "\n";    // "M  src/main.php"
}

// Network
$repo->fetch('origin');                   // Fetch from remote
$repo->push('origin', 'main');            // Push to remote

// Init and clone
$repo = Pitmaster::init('/path/to/new');
$repo = Pitmaster::clone('https://github.com/user/repo.git', '/path');
```

## CLI

Pitmaster ships with a CLI that mirrors a subset of `git` commands:

```bash
./vendor/bin/pitmaster log
./vendor/bin/pitmaster status
./vendor/bin/pitmaster diff
./vendor/bin/pitmaster show HEAD
./vendor/bin/pitmaster add file.txt
./vendor/bin/pitmaster commit -m "message"
./vendor/bin/pitmaster branch feature
./vendor/bin/pitmaster checkout feature
./vendor/bin/pitmaster merge feature
./vendor/bin/pitmaster stash push
./vendor/bin/pitmaster blame file.txt
./vendor/bin/pitmaster grep "pattern"
./vendor/bin/pitmaster tag v1.0 -m "Release"
./vendor/bin/pitmaster reset --hard HEAD~1
./vendor/bin/pitmaster refs
./vendor/bin/pitmaster init
```

## Testing

Pitmaster is verified against the canonical `git` binary using an oracle-driven approach: set up a repo, capture `git`'s output, run Pitmaster on the same repo, diff the results.

```bash
# Unit tests (no git binary needed)
composer test:unit

# Integration tests (verified against git)
composer test:integration

# All tests
composer test

# Static analysis
composer analyse

# Coding standards
composer cs
```

### Test coverage

```
440 tests, 1,091 assertions
101/101 classes tested
521 oracle scenarios from 8 upstream sources:

  32 Pitmaster (own scenarios)
  17 libgit2       (C)
  46 go-git        (Go)
  71 isomorphic-git (JavaScript)
   5 dulwich       (Python)
  19 git test suite (hand-picked)
   7 JGit          (Java)
 324 git test suite (extracted from t/*.sh)
```

Every scenario runs `git` as the oracle and compares Pitmaster's output for objects, refs, and commit history.

## Requirements

- PHP 8.2+
- `ext-zlib` (built-in)
- `ext-mbstring` (built-in)
- `ext-json` (built-in)

No other extensions. No `exec()`. No FFI.

## Features

See [SUPPORT_MATRIX.md](SUPPORT_MATRIX.md) for the full feature list. Highlights:

| Category | Features |
|----------|----------|
| Objects | Blob, tree, commit, tag (SHA-1 + SHA-256) |
| Storage | Loose objects, pack files (v1/v2 index, OFS/REF delta), MIDX, commit-graph |
| Index | v2/v3/v4 with extensions (TREE, REUC, FSMN) |
| Refs | Loose, packed, symbolic, reftable, reflog |
| Diff | Myers O(ND), patience, histogram, word diff, rename detection |
| Merge | Three-way, recursive, ours, octopus, fast-forward, conflict markers |
| Network | Smart HTTP (v1/v2), SSH, git://, dumb HTTP, bundles |
| Operations | add, commit, status, diff, merge, checkout, reset, stash, cherry-pick, revert, rebase, blame, grep, bisect, notes |
| Advanced | Submodules, worktrees, sparse checkout, hooks, LFS, rerere, fsmonitor |

## Architecture

```
src/
├── Pitmaster.php              # Static facade (open, init, clone)
├── Repository.php             # All operations
├── Object/                    # Blob, Tree, Commit, Tag, ObjectId
├── Storage/                   # LooseObjectStore, PackFileStore, ObjectDatabase
├── Pack/                      # PackFile, PackIndex, DeltaApplier, PackWriter
├── Index/                     # Index reader/writer
├── Ref/                       # LooseRefStore, PackedRefStore, RefDatabase
├── Diff/                      # MyersDiff (O(ND)), TreeDiff, DiffResult
├── Merge/                     # ThreeWayMerge, MergeBase, ConflictMarker
├── Graph/                     # CommitWalker, Blame, Grep, Bisect, Rebase
├── Status/                    # WorkingTreeStatus, GitIgnore, Fsmonitor
├── Protocol/                  # SmartHttpClient, PktLine, UploadPackClient
├── Stash/                     # Stash (push/pop/apply/list/drop)
├── Config/                    # GitConfig, GitAttributes
├── Encoding/                  # BinaryReader, VarInt, Leb128
├── Hooks/                     # HookRunner
├── Lfs/                       # LfsClient, LfsPointer
├── Submodule/                 # SubmoduleManager
├── Worktree/                  # WorktreeManager
├── Checkout/                  # SparseCheckout
└── Exceptions/                # Typed exceptions
```

## License

MIT
