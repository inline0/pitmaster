# Session Execution Queue

This file turns [`ORACLE_PARITY_TODO.md`](ORACLE_PARITY_TODO.md) into a concrete long-pass work queue.

It exists to stop the next session from burning a full work window on one isolated slice while the larger parity backlog barely moves.

## Rules

1. Work top to bottom unless an item is blocked by a dependency from an earlier item.
2. Default autonomous target: close at least 10 items before pausing, unless a full verification gate fails or an external oracle is genuinely unavailable.
3. An item is complete only when all of the following are true:
   - implementation or claim correction landed
   - Git-backed integration coverage landed under `tests/Integration`
   - oracle scenario coverage exists under `scenarios` and is part of regression
   - [`ORACLE_PARITY_TODO.md`](ORACLE_PARITY_TODO.md) was updated
   - [`SUPPORT_MATRIX.md`](SUPPORT_MATRIX.md) was updated if the row status or public claim changed
4. Verification rhythm:
   - during an item: targeted `vendor/bin/phpunit ...` plus `./bin/test-scenario ...`
   - after each wave: `./bin/verify-all`
   - at the end of the pass: `./bin/verify-all` again if any code changed after the last wave gate
5. Do not silently skip blocked items. Record the blocker inline in this file and move only if the dependency order requires it.

## Wave 1: Conflict and Sequencer State

- [ ] 01. Merge unmerged-index parity
  Rows: `Conflict stages (1/2/3)`, `MergeConflictException`
  Deliverables: write real stage `1/2/3` index entries on merge conflicts, persist `MERGE_HEAD`, `MERGE_MODE`, `MERGE_MSG`, `ORIG_HEAD`, and make `git ls-files --stage` plus `git status` observe the same state.
  Evidence target: new or expanded `tests/Integration/MergeParityTest.php`
  Oracle target: add `scenarios/merge/merge-conflict-state`, `scenarios/merge/merge-conflict-continue`, `scenarios/merge/merge-conflict-abort`
  Upstream anchors: `t1015-read-index-unmerged`, `t7607-merge-state`, `t7611-merge-abort`

- [ ] 02. Merge continue and abort parity
  Rows: `Conflict stages (1/2/3)`, `MergeConflictException`
  Deliverables: public API and CLI support for `merge --continue` and `merge --abort`, proper cleanup of merge state, correct reflog movement, and no stale sequencer files.
  Evidence target: extend `tests/Integration/MergeParityTest.php`
  Oracle target: extend the merge scenarios from item 01
  Upstream anchors: `t7607-merge-state`, `t7611-merge-abort`

- [ ] 03. Cherry-pick conflict lifecycle parity
  Rows: `git cherry-pick`, `Conflict stages (1/2/3)`, `MergeConflictException`
  Deliverables: `CHERRY_PICK_HEAD`, conflict stages, sequencer state, continue/abort behavior, and reflog parity.
  Evidence target: new `tests/Integration/CherryPickParityTest.php`
  Oracle target: add `scenarios/cherry-pick/cherry-pick-conflict-state`, `...-continue`, `...-abort`
  Upstream anchors: `libgit2/cherrypick`, `t3507-cherry-pick-conflict`

- [ ] 04. Revert conflict lifecycle parity
  Rows: `git revert`, `Conflict stages (1/2/3)`, `MergeConflictException`
  Deliverables: `REVERT_HEAD`, conflict stages, sequencer state, continue/abort behavior, and reflog parity.
  Evidence target: new `tests/Integration/RevertParityTest.php`
  Oracle target: add `scenarios/revert/revert-conflict-state`, `...-continue`, `...-abort`
  Upstream anchors: `libgit2/revert`, `t3507-cherry-pick-conflict`

- [ ] 05. Index extension parity for `TREE` and `REUC`
  Rows: `Index extensions (TREE, REUC)`
  Deliverables: parse and preserve Git-generated `TREE`/`REUC` extensions or downgrade the row honestly if full write support is not in scope.
  Evidence target: new `tests/Integration/IndexExtensionParityTest.php`
  Oracle target: add `scenarios/index/index-tree-extension` and `scenarios/index/index-reuc-extension`
  Upstream anchors: `t0090-cache-tree`, `t1015-read-index-unmerged`

- [ ] 06. Rebase parity completion
  Rows: `git rebase`
  Deliverables: keep the new linear rebase lifecycle, then add remaining parity for reflog entries, in-progress state cleanup, and any missing `--continue`/`--abort`/`--skip` edge cases discovered by Git comparison.
  Evidence target: expand `tests/Integration/RebaseParityTest.php`
  Oracle target: extend the new `scenarios/rebase/*`
  Upstream anchors: `libgit2/rebase`, `rebase-submodule`, `git rebase` upstream scenarios already vendored

## Wave 2: Transport and Negotiation

- [ ] 07. Protocol v1 parity
  Rows: `Protocol v1`, `Pkt-line format`
  Deliverables: explicit Git-backed parity for v1 fetch/push framing, flush packets, error packets, and capability formatting.
  Evidence target: new `tests/Integration/ProtocolV1ParityTest.php`
  Oracle target: add `scenarios/protocol/v1-upload-pack`, `scenarios/protocol/v1-receive-pack`
  Upstream anchors: `t5530-upload-pack-error`, `t5400-send-pack`, `t5704-protocol-violations`

- [ ] 08. Protocol v2 negotiation parity
  Rows: `Protocol v2`
  Deliverables: explicit v2 `ls-refs` and `fetch` negotiation parity, including symrefs and server option handling where applicable.
  Evidence target: new `tests/Integration/ProtocolV2ParityTest.php`
  Oracle target: add `scenarios/protocol/v2-ls-refs`, `scenarios/protocol/v2-fetch`
  Upstream anchors: `t5555-http-smart-common`, `t5535-fetch-push-symref`

- [ ] 09. Ref discovery and capability parity
  Rows: `Ref discovery`, `Capability negotiation`
  Deliverables: exact advertisement comparison against Git across smart HTTP and `git://`, including HEAD symref and capability sets.
  Evidence target: new `tests/Integration/RefDiscoveryParityTest.php`
  Oracle target: add `scenarios/protocol/ref-discovery-smart-http`, `scenarios/protocol/ref-discovery-git`
  Upstream anchors: `t5535-fetch-push-symref`, `t1403-show-ref`, `t5704-protocol-violations`

- [ ] 10. HTTP clone failure-cleanup and refspec parity
  Rows: `Clone via HTTP`
  Deliverables: clone cleanup on failure, remote config parity, fetch refspec parity, and post-clone remote usability.
  Evidence target: expand `tests/Integration/SmartHttpRemoteParityTest.php`
  Oracle target: add `scenarios/network/http-clone-cleanup`, `scenarios/network/http-clone-refspec`
  Upstream anchors: `t5600-clone-fail-cleanup`, `t5612-clone-refspec`

- [ ] 11. Incremental fetch and no-op negotiation parity
  Rows: `Incremental fetch`
  Deliverables: real Git parity for no-op fetch, incremental object negotiation, negative refspec handling, and unchanged remote heads.
  Evidence target: new `tests/Integration/IncrementalFetchParityTest.php`
  Oracle target: add `scenarios/network/fetch-incremental`, `scenarios/network/fetch-noop`, `scenarios/network/fetch-negative-refspec`
  Upstream anchors: `t5554-noop-fetch-negotiator`, `t5582-fetch-negative-refspec`

- [ ] 12. Push edge-case parity
  Rows: `Push`
  Deliverables: non-fast-forward rejection, force-with-lease/CAS, atomic push, mirror push, and server-side report-status parity.
  Evidence target: new `tests/Integration/PushParityTest.php`
  Oracle target: add `scenarios/network/push-non-fast-forward`, `.../push-cas`, `.../push-atomic`, `.../push-mirror`
  Upstream anchors: `t5529-push-errors`, `t5533-push-cas`, `t5406-remote-rejects`

- [ ] 13. `git://` scenario parity sweep
  Rows: `git:// transport`
  Deliverables: move `git://` support from a single integration test to scenario-backed parity across discovery, fetch, and failure handling.
  Evidence target: expand `tests/Integration/GitProtocolClientTest.php`
  Oracle target: add `scenarios/network/git-protocol-fetch`, `scenarios/network/git-protocol-errors`
  Upstream anchors: `t5400-send-pack`, `t5704-protocol-violations`

- [ ] 14. Dumb HTTP full workflow parity
  Rows: `Dumb HTTP`
  Deliverables: full clone/fetch parity instead of individual ref/object/pack requests only.
  Evidence target: expand `tests/Integration/DumbHttpClientTest.php`
  Oracle target: add `scenarios/network/dumb-http-clone`, `scenarios/network/dumb-http-fetch`
  Upstream anchors: `t5607-clone-bundle`, `t5618-alternate-refs`

## Wave 3: Core Repository Semantics

- [ ] 15. Commit write parity expansion
  Rows: `Commit write`
  Deliverables: Git-backed parity for author/committer env handling, trailers, hook interactions, and any remaining empty-tree or no-op edge cases.
  Evidence target: new `tests/Integration/CommitWriteParityTest.php`
  Oracle target: add `scenarios/commit/commit-author-env`, `.../commit-trailers`, `.../commit-hook-interaction`
  Upstream anchors: `t7501-commit-basic-functionality`, `t7503-pre-commit-and-pre-merge-commit-hooks`, `t7504-commit-msg-hook`

- [ ] 16. Annotated tag read/write/verify parity
  Rows: `Annotated tag read`, `Annotated tag write`
  Deliverables: stronger Git-backed annotated tag coverage, including tag object fields, peeled refs, and verification behavior where unsigned tags are expected to fail or pass consistently.
  Evidence target: new `tests/Integration/AnnotatedTagParityTest.php`
  Oracle target: add `scenarios/tags/tag-annotated-read`, `.../tag-annotated-write`, `.../tag-verify`
  Upstream anchors: `t3800-mktag`, `t7030-verify-tag`, `git-suite/tag-types`

- [ ] 17. Pack v1 and pack enumeration parity
  Rows: `Pack index v1 read`, `Pack enumeration`
  Deliverables: Git-generated v1 pack/index fixtures and an explicit enumeration parity test rather than inference through object lookups.
  Evidence target: expand `tests/Integration/PackIndexV1Test.php` and add `tests/Integration/PackEnumerationParityTest.php`
  Oracle target: add `scenarios/packs/pack-index-v1`, `scenarios/packs/pack-enumeration`
  Upstream anchors: `t0081-find-pack`, `t5300-pack-object`, `git-suite/pack-objects-basic`

- [ ] 18. Index format v3/v4 parity
  Rows: `Index v3 read (extended flags)`, `Index v4 read (path prefix compression)`
  Deliverables: Git-generated v3/v4 index fixtures and direct parity through `git ls-files --stage`.
  Evidence target: new `tests/Integration/IndexFormatParityTest.php`
  Oracle target: add `scenarios/index/index-v3-read`, `scenarios/index/index-v4-read`
  Upstream anchors: `t1701-racy-split-index`, `t2106-update-index-assume-unchanged`, `libgit2/indexv4`

- [ ] 19. Missing-object and index-parse parity
  Rows: `ObjectNotFoundException`, `IndexParseException`
  Deliverables: focused Git-backed error-path parity instead of indirect coverage.
  Evidence target: new `tests/Integration/RepositoryErrorParityTest.php`
  Oracle target: add `scenarios/errors/missing-object`, `scenarios/errors/index-corrupt`
  Upstream anchors: `t6022-rev-list-missing`, `t1015-read-index-unmerged`, `libgit2/indexv4`

## Wave 4: Advanced Feature Parity

- [ ] 20. Submodule lifecycle parity
  Rows: `Submodules`
  Deliverables: Git-backed parity for `init`, `update`, `status`, detached-head behavior, and nested config correctness.
  Evidence target: expand `tests/Integration/SubmoduleTest.php`
  Oracle target: add `scenarios/submodules/submodule-init`, `.../submodule-update`, `.../submodule-status`
  Upstream anchors: `t7406-submodule-update`, `git-suite/submodule-basic`, `isomorphic-git/test-submodules`

- [ ] 21. Stash parity expansion
  Rows: `Stash`
  Deliverables: `stash push/apply/pop` parity for staged-only changes, conflicts, and worktree cleanup.
  Evidence target: new `tests/Integration/StashParityTest.php`
  Oracle target: add `scenarios/stash/stash-apply`, `.../stash-pop-conflict`, `.../stash-staged-only`
  Upstream anchors: `t3903-stash`, `git-suite/stash`, `isomorphic-git/test-stash`

- [ ] 22. Sparse-checkout interaction parity
  Rows: `Sparse checkout`
  Deliverables: Git-backed parity for merge, reset, and status under sparse patterns.
  Evidence target: expand `tests/Integration/SparseCheckoutTest.php`
  Oracle target: add `scenarios/sparse/sparse-merge`, `.../sparse-reset`, `.../sparse-status`
  Upstream anchors: `t1011-read-tree-sparse-checkout`, `t1091-sparse-checkout-builtin`, `t3705-add-sparse-checkout`

- [ ] 23. Notes parity expansion
  Rows: `Git notes`
  Deliverables: note merge behavior, namespace handling, and worktree interaction parity.
  Evidence target: expand `tests/Integration/NotesTest.php`
  Oracle target: add `scenarios/notes/notes-merge`, `.../notes-namespace`, `.../notes-worktree`
  Upstream anchors: `t3301-notes`, `t3320-notes-merge-worktrees`, `git-suite/notes`

- [ ] 24. Blame parity expansion
  Rows: `Git blame`
  Deliverables: moved-line history, format parity, and conflict-history coverage against Git.
  Evidence target: new `tests/Integration/BlameParityTest.php`
  Oracle target: add `scenarios/blame/blame-basic`, `.../blame-moved-lines`, `.../blame-formats`
  Upstream anchors: `t8002-blame`, `t8008-blame-formats`, `libgit2/blametest-git`

- [ ] 25. Grep parity expansion
  Rows: `Git grep`
  Deliverables: regex/options behavior, binary handling, sparse-checkout, and submodule-aware parity.
  Evidence target: new `tests/Integration/GrepParityTest.php`
  Oracle target: add `scenarios/grep/grep-basic`, `.../grep-binary`, `.../grep-sparse`, `.../grep-submodule`
  Upstream anchors: `t7811-grep-open`, `t7815-grep-binary`, `t7817-grep-sparse-checkout`

## Wave 5: Overclaims, Missing Oracles, and Honest Scope

- [ ] 26. Reftable row resolution
  Rows: `Reftable format`
  Deliverables: either import real Git reftable scenarios and add read parity, or downgrade the support-matrix row immediately.
  Evidence target: new `tests/Integration/ReftableParityTest.php` if implemented
  Oracle target: add `scenarios/refs/reftable-read` if implemented
  Upstream anchors: `t0614-reftable-fsck`

- [ ] 27. SHA-256 repository-mode resolution
  Rows: `ObjectId SHA-256`
  Deliverables: either implement/import Git SHA-256 repository-mode parity or downgrade the public claim.
  Evidence target: new `tests/Integration/Sha256RepositoryParityTest.php` if implemented
  Oracle target: add `scenarios/objects/sha256-repository` if implemented
  Upstream anchors: canonical Git SHA-256 object-format coverage

- [ ] 28. Rerere parity
  Rows: `Rerere`
  Deliverables: Git-backed rerere cache behavior, reuse on repeated conflict, and state-file parity.
  Evidence target: expand `tests/Integration/RerereTest.php`
  Oracle target: add `scenarios/rerere/rerere-reuse`
  Upstream anchors: `t7611-merge-abort`, merge-conflict upstream scenarios

- [ ] 29. Bisect parity
  Rows: `Bisect`
  Deliverables: import real bisect scenarios, compare state and traversal against `git bisect`, or downgrade the claim.
  Evidence target: expand `tests/Integration/BisectTest.php`
  Oracle target: add `scenarios/bisect/bisect-basic`, `.../bisect-reset`
  Upstream anchors: upstream Git bisect scenarios to import

- [ ] 30. Fsmonitor resolution
  Rows: `Fsmonitor`
  Deliverables: either prove real canonical fsmonitor parity or downgrade the row to match reality.
  Evidence target: expand `tests/Integration/FsmonitorTest.php` if implemented
  Oracle target: add `scenarios/fsmonitor/fsmonitor-basic` if implemented
  Upstream anchors: canonical Git fsmonitor scenarios if imported

- [ ] 31. Shallow clone and fetch true parity
  Rows: `Shallow clones`
  Deliverables: end-to-end shallow clone/fetch negotiation against a remote, not just `.git/shallow` file semantics.
  Evidence target: new `tests/Integration/ShallowRemoteParityTest.php`
  Oracle target: add `scenarios/network/shallow-clone`, `.../shallow-fetch`
  Upstream anchors: `git-suite/shallow-clone`, `libgit2/shallow-git`

- [ ] 32. SSH transport oracle decision
  Rows: `SSH transport`
  Deliverables: either add a real Git-over-SSH fixture/server oracle or downgrade the row and public claim.
  Evidence target: new `tests/Integration/SshTransportParityTest.php` if implemented
  Oracle target: add `scenarios/network/ssh-fetch`, `scenarios/network/ssh-push` if implemented
  Upstream anchors: real Git-over-SSH server fixtures

- [ ] 33. Git LFS oracle decision
  Rows: `Git LFS`
  Deliverables: either add `git-lfs` client/server oracle coverage or stop describing the row as Git-oracle-verified.
  Evidence target: new `tests/Integration/LfsParityTest.php` if implemented
  Oracle target: add `scenarios/lfs/lfs-fetch`, `scenarios/lfs/lfs-push` if implemented
  Upstream anchors: `git-lfs` integration fixtures, not stock Git alone

## Exit Condition

Delete this file only when every item above is checked off and the non-`Mapped` rows in [`ORACLE_PARITY_TODO.md`](ORACLE_PARITY_TODO.md) are either fully burned down or honestly relabeled with the correct non-Git oracle.
