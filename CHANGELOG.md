# Changelog

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
