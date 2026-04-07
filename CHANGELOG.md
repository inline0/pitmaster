# Changelog

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
