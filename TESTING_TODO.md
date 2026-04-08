# Internal Testing and Hardening Backlog

Current baseline:
- `468 tests`
- `1209 assertions`
- core checks already green: `composer test`, `composer analyse`, `composer cs`

Purpose:
- catch consumer-shaped regressions before release
- harden Pitmaster against malformed repos, hostile input, and partial-failure states
- make correctness claims rest on repeatable invariants instead of ad hoc happy-path tests

Conventions:
- `[ ]` not started
- `[~]` partially covered
- `[x]` done
- `P0` correctness/safety first
- `P1` high-value expansion
- `P2` depth, scale, and long-tail hardening

## P0 Repository State Machines

- [ ] `P0` Lock down merge in-progress state in [src/Repository.php](src/Repository.php):
  assert `MERGE_HEAD`, `MERGE_MSG`, and stage `1/2/3` index entries are written on conflict and cleaned on abort/continue.
- [ ] `P0` Add integration coverage for merge abort semantics:
  worktree, index, `HEAD`, branch ref, and reflog must all round-trip cleanly after abort.
- [ ] `P0` Add integration coverage for merge continue semantics:
  conflict resolution staged by the caller should produce the expected merge commit and clear merge state.
- [ ] `P0` Add empty-merge and already-up-to-date tests:
  no stray files, no ref movement, no dirty index.
- [ ] `P0` Lock down `cherryPick()` conflict state:
  assert `CHERRY_PICK_HEAD`, conflict markers, index stages, abort, and continue semantics.
- [ ] `P0` Lock down `revert()` conflict state:
  assert `REVERT_HEAD`, conflict markers, index stages, abort, and continue semantics.
- [ ] `P0` Add tests for empty cherry-pick and empty revert results:
  verify no incorrect commit is created and error reporting stays stable.
- [ ] `P0` Expand `reset()` coverage for `soft`, `mixed`, and `hard`:
  verify tracked deletions, staged additions, staged deletions, and unresolved conflicts.
- [ ] `P0` Add detached-HEAD transition tests:
  branch to detached, detached commit creation, branch recreation, and reflog shape.
- [ ] `P0` Add direct tests for `checkout()` switching between:
  branch -> branch, branch -> detached commit, detached -> branch, and branch recreation.
- [ ] `P0` Verify all ref-moving operations append correct reflog entries:
  commit, branch create/delete/rename, reset, checkout, merge, cherry-pick, revert, rebase, and worktree operations where applicable.

## P0 Consumer-Shaped Worktree Coverage

- [ ] `P0` Add linked-worktree tests for symlinked parent directories:
  avoid path alias collisions and ensure `commonGitDir()` is stable.
- [ ] `P0` Add linked-worktree tests for relative `.git` indirection files:
  assert reopen/list/remove still works after process restart.
- [ ] `P0` Add tests for removing worktrees from a reopened linked-worktree handle:
  not just from the main checkout.
- [ ] `P0` Add worktree creation failure-injection tests:
  branch creation fails, checkout materialization fails, metadata write fails, `.git` file write fails.
- [ ] `P0` Add worktree tests for repeated create/remove/create cycles using the same metadata name:
  no stale metadata, no stale branch refs, no stale linked checkout state.
- [ ] `P0` Add worktree tests where the source repository is itself opened via a linked worktree:
  clone, add, list, remove, reopen.
- [ ] `P0` Add worktree tests around uncommitted state:
  reject unsafe operations when the implementation should preserve index/worktree guarantees.

## P0 Storage, Parsing, and Corruption Handling

- [~] `P0` Add malformed loose-object tests in [src/Storage/LooseObjectStore.php](src/Storage/LooseObjectStore.php):
  truncated zlib stream, invalid object header, bad type, bad size, extra trailing bytes.
- [ ] `P0` Add corrupted object database tests in [src/Storage/ObjectDatabase.php](src/Storage/ObjectDatabase.php):
  missing object file, unreadable object, broken alternates, partial writes.
- [ ] `P0` Add tree-parse validation tests in [src/Object/Tree.php](src/Object/Tree.php):
  duplicate names, invalid modes, file/tree path collisions, malformed entries, unexpected ordering.
- [ ] `P0` Add commit-parse validation tests in [src/Object/Commit.php](src/Object/Commit.php):
  missing tree line, malformed parent lines, broken author/committer blocks, oversized headers.
- [ ] `P0` Add tag-parse validation tests in [src/Object/Tag.php](src/Object/Tag.php):
  missing target, invalid type, malformed tagger, weird message framing.
- [ ] `P0` Add corruption tests for [src/Pack/PackFile.php](src/Pack/PackFile.php):
  truncated pack, invalid signature, bad version, invalid object count, impossible object headers.
- [~] `P0` Add corruption tests for [src/Pack/PackIndexer.php](src/Pack/PackIndexer.php):
  truncated input, invalid offsets, bad delta base, duplicate offsets, malformed trailer.
- [~] `P0` Add corruption tests for [src/Pack/PackIndex.php](src/Pack/PackIndex.php) and [src/Pack/PackIndexV1.php](src/Pack/PackIndexV1.php):
  invalid fanout table, unsorted OIDs, missing trailer, inconsistent object count.
- [ ] `P0` Add corruption tests for [src/Pack/MultiPackIndex.php](src/Pack/MultiPackIndex.php):
  invalid chunk table, bad pack count, bad object offsets, unsupported chunk shapes.
- [ ] `P0` Add corruption tests for [src/Pack/CommitGraph.php](src/Pack/CommitGraph.php):
  broken fanout, malformed edge lists, truncated extra edge data.
- [ ] `P0` Add corruption tests for [src/Ref/Reftable.php](src/Ref/Reftable.php):
  malformed blocks, bad checksums, truncated footer, invalid varints.
- [ ] `P0` Ensure all parser failures are side-effect free:
  no partial ref writes, no partial index writes, no partial worktree updates, no leftover temp files.

## P0 Network and Transport Semantics

- [~] `P0` Expand [src/Protocol/SmartHttpClient.php](src/Protocol/SmartHttpClient.php) coverage:
  non-`200` status, wrong content type, truncated response body, malformed pkt-line stream.
- [~] `P0` Expand [src/Protocol/UploadPackClient.php](src/Protocol/UploadPackClient.php) coverage:
  advertised refs edge cases, peeled tags, shallow lines, hidden refs, malformed capability lists.
- [~] `P0` Expand [src/Protocol/ReceivePackClient.php](src/Protocol/ReceivePackClient.php) coverage:
  push rejection, non-fast-forward rejection, unpack failure, hook rejection, side-band error framing.
- [ ] `P0` Add failure-path coverage for [src/Protocol/SshClient.php](src/Protocol/SshClient.php):
  non-zero exit, stderr-only responses, quoting/escaping edge cases, malformed command output.
- [ ] `P0` Add protocol tests for partial clone and shallow fetch behavior:
  deepen, unshallow, missing-base objects, and unsupported server responses.
- [ ] `P0` Add tests for remote refs with symbolic `HEAD` and unusual ref layouts:
  ensure clone/fetch resolution remains deterministic.

## P0 Hostile-Input and Security-Oriented Cases

- [ ] `P0` Add path traversal tests anywhere untrusted paths are accepted:
  worktree paths, hook paths, submodule paths, object paths, ref names.
- [ ] `P0` Add symlink escape tests:
  operations should not accidentally write outside the intended checkout through crafted symlinks.
- [ ] `P0` Add tests for hostile `.git` indirection files:
  missing target, non-repository target, relative traversal, broken `commondir`.
- [ ] `P0` Add shell escaping tests around hook execution in [src/Hooks/HookRunner.php](src/Hooks/HookRunner.php):
  spaces, quotes, unicode, and special shell characters in paths and args.
- [ ] `P0` Add size-guard tests for pack/object parsing:
  refuse or fail predictably on pathological size declarations or delta chains.
- [ ] `P0` Add denial-of-service style tests for recursive deltas and deeply nested trees:
  verify parser/runtime limits fail predictably rather than exhausting memory or time.

## P1 Repository Semantics Expansion

- [ ] `P1` Add mode-only change tests:
  executable bit flips, regular file to symlink, symlink to regular file.
- [ ] `P1` Add tests for commit metadata fidelity:
  author vs committer changes, explicit timestamps, timezone offsets, multiline messages, trailers.
- [ ] `P1` Add tests for branch naming edge cases:
  slashes, nested refs, invalid names, symbolic refs, ambiguous names.
- [ ] `P1` Add tests for notes:
  add/update/remove on non-`HEAD` objects and note namespace round-trips.
- [ ] `P1` Add direct tests for [src/Stash/Stash.php](src/Stash/Stash.php):
  staged-only stash, unstaged-only stash, untracked inclusion, conflict on apply/pop.
- [ ] `P1` Add tests for sparse checkout interactions:
  checkout, status, add/remove, merge, reset, and branch switching under sparse patterns.
- [ ] `P1` Add reflog read/write consistency tests for [src/Ref/Reflog.php](src/Ref/Reflog.php):
  branch refs, `HEAD`, linked worktrees, and corruption handling.
- [ ] `P1` Add tests for ref precedence:
  loose refs overriding packed refs, deleting loose refs revealing packed refs, symbolic chains.
- [ ] `P1` Add tests for [src/Ref/RefDatabase.php](src/Ref/RefDatabase.php):
  per-worktree `HEAD`, shared packed refs, path-based resolution, and symbolic cycles.

## P1 Graph and History Tools

- [ ] `P1` Add direct tests for [src/Graph/CommitWalker.php](src/Graph/CommitWalker.php):
  topo order, date order, boundary commits, and merge-heavy traversal.
- [ ] `P1` Add direct tests for [src/Graph/Grep.php](src/Graph/Grep.php):
  path filtering, revision ranges, regex edge cases, and binary/blob behavior.
- [ ] `P1` Add direct tests for [src/Graph/Blame.php](src/Graph/Blame.php):
  rename-ish history, merge history, deleted lines, and boundary commits.
- [ ] `P1` Expand [src/Graph/Rebase.php](src/Graph/Rebase.php) coverage:
  conflict steps, skip, abort, continue, empty commits, and branch movement invariants.
- [ ] `P1` Expand [src/Graph/Bisect.php](src/Graph/Bisect.php) coverage:
  skipped commits, merge-heavy graphs, and deterministic midpoint choice.
- [ ] `P1` Expand [src/Graph/RevisionParser.php](src/Graph/RevisionParser.php) coverage:
  `^`, `~`, range operators, merge parents, `@{-1}`, invalid syntax, and ambiguity errors.

## P1 Index, Status, and Working Tree

- [ ] `P1` Add direct tests for [src/Index/Index.php](src/Index/Index.php):
  stage entries, sort order, duplicate entries, conflict entries, and removal semantics.
- [ ] `P1` Add direct tests for [src/Index/IndexWriter.php](src/Index/IndexWriter.php):
  checksum validation, extension blocks, empty index, and large path sets.
- [ ] `P1` Expand [src/Index/IndexEntry.php](src/Index/IndexEntry.php) coverage:
  executable files, symlinks, path normalization, and stage flags.
- [ ] `P1` Expand status tests for [src/Status/WorkingTreeStatus.php](src/Status/WorkingTreeStatus.php):
  ignored parents, nested gitignores, intent-to-add, filemode-only changes, symlink changes.
- [ ] `P1` Expand [src/Status/Fsmonitor.php](src/Status/Fsmonitor.php) tests:
  stale cookies, unsupported responses, malformed extension blocks.
- [ ] `P1` Add cross-checks against Git status porcelain for tricky trees:
  ignored/untracked mixes, deleted directories, case-only renames, sparse checkouts.

## P1 Object and Ref Storage

- [ ] `P1` Add direct tests for [src/Storage/PackFileStore.php](src/Storage/PackFileStore.php):
  pack discovery, refresh semantics, duplicate objects across packs, missing index behavior.
- [ ] `P1` Add direct tests for [src/Storage/ObjectStore.php](src/Storage/ObjectStore.php):
  loose-vs-pack precedence and missing-object fallback behavior.
- [ ] `P1` Add direct tests for [src/Ref/LooseRefStore.php](src/Ref/LooseRefStore.php):
  nested refs, invalid content, whitespace handling, symbolic refs, and deletion.
- [ ] `P1` Add direct tests for [src/Ref/PackedRefStore.php](src/Ref/PackedRefStore.php):
  peeled refs, comments, sorting, malformed lines, duplicate refs.
- [ ] `P1` Add direct tests for [src/Ref/Notes.php](src/Ref/Notes.php):
  namespace isolation, delete/update semantics, and packed-ref interaction.

## P1 Submodules, Hooks, LFS, and Stash

- [ ] `P1` Expand [src/Submodule/SubmoduleManager.php](src/Submodule/SubmoduleManager.php) coverage:
  malformed `.gitmodules`, missing submodule object, nested submodules, relative URLs.
- [ ] `P1` Add submodule status/update tests:
  detached submodule `HEAD`, dirty submodule, missing checkout, and ref drift.
- [ ] `P1` Expand [src/Hooks/HookRunner.php](src/Hooks/HookRunner.php) coverage:
  failing hooks, stdout/stderr handling, env propagation, and missing executable bits.
- [ ] `P1` Add integration tests for [src/Lfs/LfsClient.php](src/Lfs/LfsClient.php):
  pointer detection in real repos, missing remote object, invalid pointer content, round-trip fetch.
- [ ] `P1` Add stash workflow integration tests:
  stash, list, apply, pop, drop, clear, and stash conflicts after branch drift.

## P1 Diff and Merge Algorithms

- [ ] `P1` Add oracle-style comparisons for diff algorithms:
  compare `Myers`, `Histogram`, `Patience`, and `Minimal` outputs on shared fixtures.
- [ ] `P1` Add property-style tests that diffs reconstruct the target text when hunks are applied.
- [ ] `P1` Expand word-diff and color-diff tests:
  unicode, whitespace-only changes, long lines, and empty inputs.
- [ ] `P1` Expand `ThreeWayMerge` and `RecursiveMerge` tests with larger merge forests:
  nested renames, criss-cross merges, directory/file conflicts, and repeated conflict regions.
- [ ] `P1` Expand `Rerere` integration coverage:
  repeated conflict reuse across reopened repos and linked worktrees.

## P1 Oracle and Fixture Expansion

- [ ] `P1` Add more upstream oracle scenarios under `scenarios/upstream`:
  prioritize worktree, merge state, reflog, stash, submodule, and shallow-clone fixtures.
- [ ] `P1` Add consumer-shaped scenarios derived from Rudel/Harness path layouts:
  repeated basename collisions, nested app/plugin/theme trees, cloned-from-linked sources.
- [ ] `P1` Add a scenario runner mode that stress-runs selected scenarios multiple times:
  catch flaky temp-path and ordering issues.
- [ ] `P1` Add negative scenarios:
  intentionally corrupted pack/index/ref/object inputs with expected failure classes.

## P1 CI and Release Hardening

- [x] `P1` Extend GitHub Actions matrix beyond a single Linux/PHP version:
  at least `ubuntu` + `macos`, and multiple supported PHP versions.
- [x] `P1` Add a docs build job to CI:
  `npm ci` and `npm run build` under `docs/`.
- [ ] `P1` Add a nightly or manual heavy-validation workflow:
  upstream scenarios, broader fixture acquisition, and long-running protocol checks.
- [ ] `P1` Add coverage artifact generation:
  even if not gating yet, track changed-lines risk over time.
- [ ] `P1` Add a smoke workflow for release paths:
  tag build, Packagist webhook expectation, docs build, and CLI boot check.
- [ ] `P1` Add a downstream smoke job for Rudel/Harness if practical:
  create linked environment worktrees using Pitmaster exactly as consumers do.

## P2 Property, Fuzz, and Scale Testing

- [ ] `P2` Add property tests for object serialization:
  serialize -> parse -> serialize round-trips for blobs, trees, commits, and tags.
- [ ] `P2` Add property tests for delta application and resolution:
  random base/object combinations with validity constraints.
- [ ] `P2` Add property tests for pkt-line framing in [src/Protocol/PktLine.php](src/Protocol/PktLine.php):
  valid framing round-trips and invalid framing rejection.
- [ ] `P2` Add fuzz harnesses for binary readers and varint/LEB128 parsers:
  [src/Encoding/BinaryReader.php](src/Encoding/BinaryReader.php), [src/Encoding/VarInt.php](src/Encoding/VarInt.php), [src/Encoding/Leb128.php](src/Encoding/Leb128.php).
- [ ] `P2` Add scale tests:
  large trees, large indexes, many refs, many packs, long delta chains, and deep histories.
- [ ] `P2` Add randomized filesystem topology tests:
  unicode paths, case-only differences, very deep paths, leading dots, and path separators.

## P2 Cross-Platform and Environment Cases

- [ ] `P2` Add macOS-specific path alias checks:
  `/tmp`, `/var`, `/private/var`, symlinks, and case-insensitive filesystem behavior.
- [ ] `P2` Add Linux/macOS line-ending and permission-mode tests.
- [ ] `P2` Add Windows-compatibility planning tests where feasible:
  path separators, reserved names, executable-bit absence, and CRLF defaults.
- [ ] `P2` Add locale and timezone-sensitive tests:
  commit formatting, log output, config parsing, and reflog timestamps.

## P2 Docs, CLI, and API Contract Tests

- [ ] `P2` Add smoke tests for CLI commands in [docs/content/docs/cli.mdx](docs/content/docs/cli.mdx):
  help text, version output, and representative commands.
- [ ] `P2` Add executable snippet tests for the README and API docs:
  examples should stay in sync with the actual public API.
- [ ] `P2` Add a support-matrix sync check:
  commands and supported features should not drift from [SUPPORT_MATRIX.md](SUPPORT_MATRIX.md).

## Direct Test Surface Gaps To Close

No direct dedicated test file exists yet for these implementation surfaces:

- [ ] `P0` [src/Repository.php](src/Repository.php)
- [ ] `P0` [src/Pitmaster.php](src/Pitmaster.php)
- [ ] `P0` [src/Worktree/WorktreeManager.php](src/Worktree/WorktreeManager.php)
- [ ] `P1` [src/Storage/PackFileStore.php](src/Storage/PackFileStore.php)
- [ ] `P1` [src/Pack/PackIndexer.php](src/Pack/PackIndexer.php)
- [ ] `P1` [src/Pack/PackFile.php](src/Pack/PackFile.php)
- [ ] `P1` [src/Pack/PackIndex.php](src/Pack/PackIndex.php)
- [ ] `P1` [src/Pack/MultiPackIndex.php](src/Pack/MultiPackIndex.php)
- [ ] `P1` [src/Protocol/SmartHttpClient.php](src/Protocol/SmartHttpClient.php)
- [ ] `P1` [src/Protocol/UploadPackClient.php](src/Protocol/UploadPackClient.php)
- [ ] `P1` [src/Ref/RefDatabase.php](src/Ref/RefDatabase.php)
- [ ] `P1` [src/Ref/LooseRefStore.php](src/Ref/LooseRefStore.php)
- [ ] `P1` [src/Ref/PackedRefStore.php](src/Ref/PackedRefStore.php)
- [ ] `P1` [src/Index/Index.php](src/Index/Index.php)
- [ ] `P1` [src/Index/IndexWriter.php](src/Index/IndexWriter.php)
- [ ] `P1` [src/Graph/CommitWalker.php](src/Graph/CommitWalker.php)
- [ ] `P1` [src/Graph/Grep.php](src/Graph/Grep.php)
- [ ] `P1` [src/Graph/Blame.php](src/Graph/Blame.php)
- [ ] `P1` [src/Stash/Stash.php](src/Stash/Stash.php)
- [ ] `P1` [src/Submodule/SubmoduleManager.php](src/Submodule/SubmoduleManager.php)
- [ ] `P1` [src/Hooks/HookRunner.php](src/Hooks/HookRunner.php)

## Highest-Value Next Batch

If the goal is to catch the most expensive bugs early, do these next:

- [ ] Build a `RepositoryStateMachineTest` focused on merge/cherry-pick/revert/reset/reflog semantics.
- [ ] Build a `ParserCorruptionTest` focused on loose objects, packs, pack indexes, commit-graph, and reftable corruption.
- [ ] Build a `ProtocolFailureTest` focused on malformed smart HTTP and receive-pack/upload-pack responses.
- [ ] Build a `ConsumerTopologyTest` expansion pass for worktrees, submodules, and nested app/plugin/theme layouts.
- [ ] Add a CI matrix with `macos-latest` and a docs build job.
