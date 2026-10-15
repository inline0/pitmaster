# Changelog

## [0.3.0] - 2026-10-16

### Added
- Git 2.56 fetch-wide/per-remote HEAD following policies, bare mirror behavior and optional warning callbacks
- Git 2.55 oldest-commit limiting through all public log methods and `pitmaster log --max-count-oldest`, including path and all-ref selection
- Git 2.56 `includeIf` worktree conditions, including case-insensitive matching, plus Git-directory and branch conditions when opening repositories
- Git 2.56 conflict-only staging through `Repository::addResolved()` and `pitmaster add --resolved`, with all-path marker validation before the index changes

### Fixed
- Fetch remote-HEAD reflogs honor automatic creation settings and preserve existing logs even when automatic logging is disabled
- Branch deletion protects checked-out branches and active bisects in every worktree; a bare repository can delete the branch named by its symbolic HEAD
- Path history follows Git merge simplification and includes all refs when requested; annotated tags contribute reachable history
- Integration test servers launch directly; scenario workspaces stop only their own marked HTTP servers and Git daemons before removing fixture data
- Conflict resolutions retain Git-compatible resolve-undo (REUC) history across later index writes, so `git checkout -m` can recreate conflicts in SHA-1 and SHA-256 repositories
- Unspecified Git attributes now clear earlier assignments; conflict marker size resolution includes nested and repository attribute overrides

## [0.2.12] - 2026-10-15

### Fixed
- Checkout and fast-forward merge carry unrelated staged and unstaged changes; commit-producing merges, cherry-pick and revert protect the index and unrelated working files, and rebase rejects dirty tracked state
- Stash apply and pop preserve local changes outside the stash and reject touched local changes before any file writes or stash removal
- SSH2 verifies known host keys before authentication, honours the configured identity, and handles discovery and receive-pack advertisements; OpenSSH applies command overrides and key-learning modes
- Diffs include new/deleted file modes, object IDs, binary paths, rename headers and mode-only changes; empty hunk ranges match Git, and redirected CLI output has no ANSI colour
- Rename similarity counts copied bytes with bounded span occurrences, avoiding false matches between unrelated one-line files and repeated lines
- Staged diffs honour an exact old or new path and show staged additions before the first commit
- Merging a branch already contained in HEAD leaves the repository untouched and reports `Already up to date`; explicit merge strategies report conflicts with exit code 1
- Path-filtered history walks until it finds the requested number of matches, including directory paths
- New non-bare repositories record reflogs by default, and numeric reflog revisions can be resolved for recovery
- Multiple stash entries are removed from both the reflog and ref by drop and successful pop
- Hooks use the common worktree directory or `core.hooksPath`, run in the selected checkout, and inherit the PHP process environment
- Linked worktree handles report the main checkout correctly and use shared stash identity and reflog metadata
- Writing an index read as v4 produces a valid uncompressed v3 index with its entries and extended flags preserved
- Fetch requests self-contained packs, updates tracking refs when objects already exist, and imports tag refs only when their objects are available; peeled tag advertisements remain metadata
- HTTPS remotes with credentials and ports stay on the HTTP transport
- Dumb HTTP clone and fetch import missing loose objects after loading the remote packs
- Clone propagates checkout failures and rejects missing checkout objects instead of returning a partial working tree
- Rerere reads Git configuration sections, includes, and boolean spellings

### Changed
- Composer requires `ext-ctype` and no longer requires the unused `ext-mbstring`

## [0.2.11] - 2026-09-24

### Fixed
- `log` accepts git's limit spellings, `-n 3`, `-n3`, `--max-count 3`, `--max-count=3` and `-3`, plus `--` before a path. `-n` used to be read as a path filter, so `log -n 3` printed nothing
- `log` rejects unknown options with git's `fatal: unrecognized argument` message and exit code 128 instead of quietly filtering by them
- `vendor/bin/pitmaster` works when pitmaster is installed as a dependency. The binary only looked for the autoloader in its own `vendor/`

## [0.2.10] - 2026-09-24

### Fixed
- The Composer package no longer ships the scenario corpora, fixtures, tests, benchmarks or docs. Installing pitmaster previously added roughly 236 MB to every consumer's `vendor/` directory, and to anything that bundles `vendor/`

### Changed
- Documentation rewritten as portable Markdown in `docs/`
- Development dependencies raised to the inline0 canonical minimums, including `squizlabs/php_codesniffer` `^3.13.6` for CVE-2026-67434. Dev only, no effect on consumers

## [0.2.9] - 2026-07-04

### Fixed
- Benchmark fixture Git commands now ignore inherited repository-scoping `GIT_*` variables so release verification rebuilds fixtures in isolated repositories
- Includes the packed-object bounded inflate and object-cache performance fixes from `0.2.8` on a CI-green release head

## [0.2.8] - 2026-07-04

### Fixed
- Packed object reads now inflate from bounded windows instead of copying the full pack tail for every object
- Resolved packed objects are cached by pack offset so shared delta bases are not re-inflated for each dependent object
- `ObjectDatabase::read()` now uses a bounded per-repository object cache for repeated history/tree/object reads

### Added
- Regression coverage for bounded packed-object reads, shared delta-base caching, and bounded object database caching

## [0.2.7] - 2026-05-18

### Changed
- Raised PHPStan static analysis from level 5 to level 10 (strictest) across the codebase
- Stdlib failure modes (`unpack`, `zlib_encode`, `hex2bin`) now surface as typed exceptions instead of silently producing wrong data
- Rewrote nested-array tree builders in `Notes`, `Stash`, and `Repository` as flat path-grouping passes
- Replaced `SplPriorityQueue` in `CommitWalker` with a typed array-based heap (preserves newest-first ordering with FIFO tiebreak)

## [0.2.6] - 2026-05-12

### Added
- Process-free repository mode via `['processes' => false]` and `Pitmaster::processFreeOptions()`

### Fixed
- Process-free repository handles now disable hooks, fsmonitor hook execution, clone/fetch/push network operations, and SSH process transport paths
- Clone failure cleanup now uses PHP filesystem traversal instead of shelling out to `rm -rf`

## [0.2.5] - 2026-04-14

### Added
- Explicit per-repository hookless mode via `['hooks' => false]` on `Pitmaster::open()`, `Pitmaster::init()`, and `Pitmaster::clone()`

### Fixed
- Hook-triggering repository operations can now be used safely in callers that must avoid executing `.git/hooks/*` scripts for a specific repository handle
- Added integration coverage proving hookless commit, checkout, merge, rebase, and push flows skip installed hooks while normal handles keep hook parity with `git`

## [0.2.4] - 2026-04-08

### Fixed
- Linked worktrees can now use an explicit metadata name instead of always keying `.git/worktrees/<name>` by `basename($path)`
- `Repository::addWorktree()` and `WorktreeManager::add()` now support deterministic metadata slugs for colliding checkout basenames
- Worktree listing now exposes the metadata name, and removal works by either metadata name or checkout path

## [0.2.3] - 2026-04-08

### Changed
- Aligned the CLI and API docs page titles, navigation labels, and code-styled section headings with the shared Rudel and Queuety docs conventions

## [0.2.2] - 2026-04-08

### Added
- Integration coverage for merge-conflict markers and HEAD stability
- Integration coverage for tracked-file pruning during hard reset and detached checkout
- Integration coverage for pack store refresh after new packs are written
- Integration coverage for empty-tree and unchanged-tree commit semantics

## [0.2.1] - 2026-04-08

### Fixed
- Pure-PHP pack indexing for fetched and cloned packs, removing the `git index-pack` dependency from repository reads
- `fetch()` now writes packs into the common object directory and refreshes pack discovery
- `checkout()`, hard reset, clone checkout, and fast-forward merges now keep worktree and index in sync, including tracked file deletions
- Clean non-fast-forward merges now preserve both sides' non-conflicting changes and leave the repository clean
- `cherryPick()` and `revert()` now apply file deletions correctly
- Commits that remove the last tracked path can now produce the empty tree
- `ThreeWayMerge` now reports same-line divergent edits as conflicts instead of silently taking "theirs"

## [0.2.0] - 2026-04-08

### Added
- Linked worktree support: `Pitmaster::open()` handles `.git` files with `gitdir:` indirection
- `Repository::commonGitDir()` for resolving the shared git directory
- `Repository::defaultBranch()` with remote HEAD / local HEAD / fallback resolution
- `Repository::isBranchMerged()` using ancestry / merge-base logic
- `Repository::addWorktree()`, `removeWorktree()`, `worktrees()` lifecycle helpers
- `Pitmaster::isRepository()` and `Pitmaster::isWorktree()` detection helpers
- `Pitmaster::commonGitDir()` static helper
- Clone now materializes the working tree (checked-out files present after clone)
- Worktree add now creates a full checkout with files and index
- Comprehensive OneDocs documentation site with 31 pages covering operations, binary formats, network, testing, and advanced features
- 637 oracle scenarios from 8 upstream sources (libgit2: 63, go-git: 46, isomorphic-git: 71, dulwich: 6, JGit: 7, git test suite: 393, hand-picked: 19, own: 32)
- README with logo and badges matching rudel/queuety style

### Fixed
- `RefDatabase` and `LooseRefStore` now accept separate per-worktree and common git dirs
- Circular symbolic ref detection (depth guard)
- Pack index v1 format auto-detection
- PHPCS PSR-12 formatting

## [0.1.0] - 2026-04-07

### Added
- Pure PHP Git implementation: read and write repositories without shelling out to git
- Object model: Blob, Tree, Commit, Tag with SHA-1 and SHA-256 support
- Object storage: loose objects (read/write), pack files (read/write), delta resolution (OFS/REF)
- Pack formats: v2 index, v1 index, multi-pack-index (MIDX), commit-graph
- Index: v2/v3/v4 read/write with extensions (TREE, REUC, FSMN, EOIE, IEOT)
- References: loose, packed, symbolic, reftable format reader, reflog
- Operations: add, commit, status, diff, merge, checkout, reset, restore, cherry-pick, revert, rebase
- Diff: Myers O(ND) algorithm (byte-exact with git), patience, histogram, minimal, word diff
- Merge: three-way merge, conflict markers, merge base (LCA), recursive/ours/octopus strategies
- Network: smart HTTP (v1/v2), dumb HTTP, SSH, git:// protocol, pkt-line encoding
- Clone, fetch, push via smart HTTP protocol
- Stash: push/pop/apply/list/drop
- Blame, grep, bisect, notes
- Submodules: .gitmodules parsing, gitlink handling
- Worktrees: add/remove/lock/unlock linked worktrees
- Sparse checkout (cone mode), fsmonitor, rerere
- Hooks: detect and invoke .git/hooks/ scripts
- Git LFS: pointer file parsing, batch API client
- Git bundles: read/write v2 format
- .gitignore and .gitattributes parsing
- CLI with 18 commands: log, show, cat-file, status, diff, add, commit, branch, tag, checkout, merge, stash, blame, grep, refs, reset, init
- Oracle-driven testing: 95 scenarios verified against canonical git
- Upstream fixtures: 17 libgit2 + 46 go-git test repositories
- 440 unit and integration tests, 1,091 assertions
- PHPStan level 5, PHPCS PSR-12 clean
