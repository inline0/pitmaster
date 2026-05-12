<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="./docs/public/logo-dark.svg">
    <source media="(prefers-color-scheme: light)" srcset="./docs/public/logo-light.svg">
    <img alt="Pitmaster" src="./docs/public/logo-light.svg" height="50">
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

Pitmaster reads and writes Git repositories in pure PHP. Core repository operations do not shell out to the `git` binary or rely on FFI. Objects, refs, pack files, the index, and smart HTTP transport are handled natively in PHP.

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

// Disable Git hook execution for this repository handle
$repo = Pitmaster::open('/path/to/project', ['hooks' => false]);

// Disable host-process and network write paths for restricted runtimes
$repo = Pitmaster::open('/path/to/project', Pitmaster::processFreeOptions());

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

Hooks stay enabled by default. Pass `['hooks' => false]` to `Pitmaster::open()`, `Pitmaster::init()`, or `Pitmaster::clone()` when the caller must avoid executing `.git/hooks/*` scripts for that repository handle.

For hosted runtimes that must not execute host processes, pass `['processes' => false]` or `Pitmaster::processFreeOptions()`. Process-free handles disable hook execution, fsmonitor hook execution, clone/fetch/push network operations, and SSH process transport paths while keeping local repository reads and writes in PHP.

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

Pitmaster is exercised with unit tests, integration tests against the canonical `git` binary, and imported oracle-style scenarios from upstream Git implementations.

```bash
# Unit tests (no git binary needed)
composer test:unit

# Integration tests (verified against git)
composer test:integration

# Oracle regression corpus (vendored upstream fixtures)
composer test:oracle

# Full test matrix: phpunit + upstream oracle regression
composer test

# Full verification: analysis + standards + full test matrix
./bin/verify-all

# Evidence / proof / drift checks
composer verify:evidence
composer verify:drift
composer verify:proof
composer test:mutation
composer audit:composer

# Benchmark smoke
composer bench -- --suite=smoke --runs=1 --warmups=0

# Full benchmark baseline
composer bench:baseline

# Compare two reports
composer bench:compare -- bench/reports/baseline.json bench/reports/candidate.local.json

# Print a sorted human summary
composer bench:summary -- bench/reports/baseline.json

# Verify the smoke report against committed thresholds
composer bench:verify -- bench/reports/ci-smoke.local.json bench/reports/smoke-thresholds.json

# Optional multi-git smoke matrix when multiple git binaries are installed
PITMASTER_TEST_GIT_BINARIES=/path/to/git-2.45:/path/to/git-2.46 \
  phpunit --filter MultiGitVersionSmokeTest

# Static analysis
composer analyse

# Coding standards
composer cs
```

The upstream oracle fixtures are vendored under [`fixtures/upstream`](fixtures/upstream), so the full regression corpus is runnable from a fresh checkout without machine-local `/tmp` dependencies. Use `composer test` for the full matrix and `./bin/verify-all` for release-grade verification. CI also validates upstream drift and publishes a machine-readable proof artifact from [`bin/build-proof-artifact`](bin/build-proof-artifact).

Feature work and fixture changes use the same bar: keep scenarios self-contained inside the repo, avoid absolute-path and machine-local-port snapshots, and do not treat a change as done until `./bin/verify-all` is green.

## Performance

Pitmaster now has a repo-local benchmark harness under [`bench/`](bench). Performance claims should be backed by measured before/after reports, not intuition.

```bash
# Run the fast benchmark subset
composer bench -- --suite=smoke --runs=1 --warmups=0

# Run one focused case when iterating on a hotspot
composer bench -- --suite=all --case=workflow.reset.hard.large --runs=5 --warmups=1

# Run the instrumentation-only suite to split a hotspot into sub-costs
composer bench -- --suite=instrumentation --runs=3 --warmups=0

# Capture the full baseline report
composer bench:baseline

# Compare two benchmark reports
composer bench:compare -- bench/reports/baseline.json bench/reports/post-opt.local.json

# Print a sorted summary for a single report
composer bench:summary -- bench/reports/baseline.json

# Verify a report against the committed smoke thresholds
composer bench:verify -- bench/reports/ci-smoke.local.json bench/reports/smoke-thresholds.json
```

Benchmark fixtures are deterministic and repo-local. They are generated under `bench/fixtures/repos` from committed definitions, not from `/tmp` or public network dependencies. The committed smoke thresholds live in [`bench/reports/smoke-thresholds.json`](bench/reports/smoke-thresholds.json), and CI verifies the smoke report against them. For optimization work, measure the focused case first, keep only wins that hold up across reruns, then refresh the canonical baseline. Do not treat a change as done until the relevant benchmark moves in the right direction and `./bin/verify-all` still passes.

The instrumentation suite is intentionally separate from the canonical baseline and smoke thresholds. Use it when a remaining hotspot needs attribution first, for example splitting smart HTTP fetch into discovery and upload-pack costs or separating SSH command startup from PHP-side parsing.

## Requirements

- PHP 8.2+
- `ext-zlib` (built-in)
- `ext-mbstring` (built-in)
- `ext-json` (built-in)

Core repository operations do not require the `git` binary or FFI. Optional features have extra runtime expectations: hook execution uses `proc_open()`, and SSH transport requires `ext-ssh2`.

## Features

See [SUPPORT_MATRIX.md](SUPPORT_MATRIX.md) for the full feature list. The rendered matrix is generated from [`config/support-matrix.json`](config/support-matrix.json) and backed by [`config/support-evidence.json`](config/support-evidence.json), which CI validates so every `DONE` row resolves to concrete tests and oracle scenarios. Highlights:

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
