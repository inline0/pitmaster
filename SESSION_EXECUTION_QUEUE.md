# Session Execution Queue

This file is the endgame execution order for the remaining non-`Mapped` rows in
[`ORACLE_PARITY_TODO.md`](ORACLE_PARITY_TODO.md).

Use it for long autonomous passes. The audit file remains the source of truth;
this file is the ordered burn-down plan to finish the remaining parity tail.

## Current Target

- Remaining audit rows: `36`
- State mix: `34 Weak`, `2 External oracle`
- Goal of this queue: close or honestly resolve all `36` remaining rows so
  [`ORACLE_PARITY_TODO.md`](ORACLE_PARITY_TODO.md) can be emptied.
- Required finish condition: every row becomes either `Mapped` or explicitly
  labeled with the correct external oracle, and all new mappings are covered by
  Git-backed integration and scenario sweeps.

## Done Rule

An item is only done when all of the following land together:

1. implementation or honest scope correction
2. Git-backed integration coverage under
   [`tests/Integration`](tests/Integration)
3. oracle scenario coverage under [`scenarios`](scenarios)
4. audit and support-matrix updates
5. green [`./bin/verify-all`](./bin/verify-all)

## Default Operating Rule

- Work top-to-bottom.
- Treat this as a completion queue, not a sketch.
- Do not skip ahead unless a lower item is genuinely blocked by a product
  decision or external oracle dependency.
- When an item can be finished honestly only by narrowing scope, do that
  immediately instead of leaving an overclaim in place.

## Completed This Pass

- `git rm (unstage/remove)` is now Git-backed for cached, tracked, and
  recursive removal through
  [`tests/Integration/MoveAndRemoveParityTest.php`](tests/Integration/MoveAndRemoveParityTest.php)
  and the local `scenarios/staging/remove-*` family.
- `git restore` is now Git-backed for worktree, staged, and source restores
  through
  [`tests/Integration/RestoreParityTest.php`](tests/Integration/RestoreParityTest.php)
  and the local `scenarios/restore/*` family.

## Wave 1: Porcelain And History Closure

1. `git stash`
   Deliverables: staged-only, include-untracked, conflict-on-apply, and
   linked-worktree stash truthfulness.
   Integration: extend
   [`tests/Integration/StashParityTest.php`](tests/Integration/StashParityTest.php).
   Oracle: add local stash scenarios and bind
   [`scenarios/upstream/git-test-suite/t3903-stash/scenario.json`](scenarios/upstream/git-test-suite/t3903-stash/scenario.json)
   and [`scenarios/upstream/git-test-suite/t3905-stash-include-untracked/scenario.json`](scenarios/upstream/git-test-suite/t3905-stash-include-untracked/scenario.json).

2. `git rebase`
   Deliverables: interactive/edit flow, merge-preserving rebase, and full
   reflog truthfulness for sequencer state.
   Integration: extend
   [`tests/Integration/RebaseParityTest.php`](tests/Integration/RebaseParityTest.php)
   and [`tests/Integration/ReflogTest.php`](tests/Integration/ReflogTest.php).
   Oracle: extend local rebase scenarios and bind
   [`scenarios/upstream/git-test-suite/t3400-rebase/scenario.json`](scenarios/upstream/git-test-suite/t3400-rebase/scenario.json),
   [`scenarios/upstream/git-test-suite/t3418-rebase-continue/scenario.json`](scenarios/upstream/git-test-suite/t3418-rebase-continue/scenario.json),
   and [`scenarios/upstream/git-test-suite/t3430-rebase-merges/scenario.json`](scenarios/upstream/git-test-suite/t3430-rebase-merges/scenario.json).

3. `git show`
   Deliverables: diff-text parity, merge-commit display, and annotated-tag
   target truthfulness.
   Integration: extend
   [`tests/Integration/LogShowParityTest.php`](tests/Integration/LogShowParityTest.php).
   Oracle: extend local show scenarios and bind
   [`scenarios/upstream/git-test-suite/t7007-show/scenario.json`](scenarios/upstream/git-test-suite/t7007-show/scenario.json)
   and [`scenarios/upstream/git-test-suite/t4063-diff-blobs/scenario.json`](scenarios/upstream/git-test-suite/t4063-diff-blobs/scenario.json).

## Wave 2: Diff And Merge Truthfulness

6. `Rename detection`
   Deliverables: status-side rename heuristics and threshold parity.
   Integration: add rename-aware status parity coverage.
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t7525-status-rename/scenario.json`](scenarios/upstream/git-test-suite/t7525-status-rename/scenario.json).

7. `Patience diff algorithm`
   Deliverables: Git-backed patience diff output parity.
   Integration: add dedicated diff algorithm parity coverage.
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t4065-diff-anchored/scenario.json`](scenarios/upstream/git-test-suite/t4065-diff-anchored/scenario.json).

8. `Histogram diff algorithm`
   Deliverables: Git-backed histogram diff output parity.
   Integration: extend diff algorithm parity coverage.
   Oracle: bind relevant upstream diff scenarios under
   [`scenarios/upstream/libgit2/diff/scenario.json`](scenarios/upstream/libgit2/diff/scenario.json).

9. `Minimal diff`
   Deliverables: Git-backed minimal diff output parity.
   Integration: extend diff algorithm parity coverage.
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t4035-diff-quiet/scenario.json`](scenarios/upstream/git-test-suite/t4035-diff-quiet/scenario.json).

10. `Rename detection (diff)`
    Deliverables: binary rename, partial rename, and diff-pair parity.
    Integration: extend
    [`tests/Integration/TreeDiffTest.php`](tests/Integration/TreeDiffTest.php).
    Oracle: bind
    [`scenarios/upstream/git-test-suite/t4043-diff-rename-binary/scenario.json`](scenarios/upstream/git-test-suite/t4043-diff-rename-binary/scenario.json)
    and [`scenarios/upstream/git-test-suite/t4070-diff-pairs/scenario.json`](scenarios/upstream/git-test-suite/t4070-diff-pairs/scenario.json).

11. `Word diff`
    Deliverables: word segmentation and hunk rendering truthfulness.
    Integration: add dedicated word-diff parity coverage.
    Oracle: add local word-diff scenarios and bind
    [`scenarios/upstream/libgit2/userdiff/scenario.json`](scenarios/upstream/libgit2/userdiff/scenario.json).

12. `Color diff output`
    Deliverables: ANSI colorized diff output parity.
    Integration: add dedicated color-diff parity coverage.
    Oracle: add local color-diff scenarios and bind relevant upstream diff
    output scenarios.

13. `Three-way merge (content)`
    Deliverables: whitespace, symlink, and deletion merge parity.
    Integration: extend
    [`tests/Integration/MergeFamilyParityTest.php`](tests/Integration/MergeFamilyParityTest.php).
    Oracle: bind
    [`scenarios/upstream/libgit2/twowaymerge-git/scenario.json`](scenarios/upstream/libgit2/twowaymerge-git/scenario.json).

14. `Conflict markers`
    Deliverables: diff3 marker styles and conflict-file text parity.
    Integration: extend
    [`tests/Integration/MergeFamilyParityTest.php`](tests/Integration/MergeFamilyParityTest.php).
    Oracle: bind
    [`scenarios/upstream/git-test-suite/t6427-diff3-conflict-markers/scenario.json`](scenarios/upstream/git-test-suite/t6427-diff3-conflict-markers/scenario.json).

15. `File-level merge (tree)`
    Deliverables: rename/delete and subtree merge truthfulness.
    Integration: extend merge-family and tree-diff parity coverage.
    Oracle: bind
    [`scenarios/upstream/git-test-suite/t4300-merge-tree/scenario.json`](scenarios/upstream/git-test-suite/t4300-merge-tree/scenario.json)
    and [`scenarios/upstream/git-test-suite/t6425-merge-rename-delete/scenario.json`](scenarios/upstream/git-test-suite/t6425-merge-rename-delete/scenario.json).

16. `Recursive strategy`
    Deliverables: multi-base and rename-caching parity.
    Integration: extend merge-family parity coverage.
    Oracle: bind
    [`scenarios/upstream/git-test-suite/t6430-merge-recursive/scenario.json`](scenarios/upstream/git-test-suite/t6430-merge-recursive/scenario.json)
    and [`scenarios/upstream/libgit2/merge-recursive/scenario.json`](scenarios/upstream/libgit2/merge-recursive/scenario.json).

17. `ORT strategy`
    Deliverables: implement and prove true ORT semantics, or narrow the claim
    immediately.
    Integration: add strategy-specific parity coverage if implementation lands.
    Oracle: bind
    [`scenarios/upstream/git-test-suite/t6431-merge-criscross/scenario.json`](scenarios/upstream/git-test-suite/t6431-merge-criscross/scenario.json).

18. `Octopus merge`
    Deliverables: repository-level octopus merge creation and state parity.
    Integration: add octopus integration coverage.
    Oracle: add a local octopus scenario and bind
    [`scenarios/upstream/git-test-suite/t7602-merge-octopus-many/scenario.json`](scenarios/upstream/git-test-suite/t7602-merge-octopus-many/scenario.json).

19. `Ours strategy`
    Deliverables: public repository-level strategy path and Git-backed parity.
    Integration: add repository-level strategy coverage.
    Oracle: bind
    [`scenarios/upstream/git-test-suite/t6417-merge-ours-theirs/scenario.json`](scenarios/upstream/git-test-suite/t6417-merge-ours-theirs/scenario.json).

20. `Merge commit creation`
    Deliverables: merge message generation, state files, and continuation
    truthfulness.
    Integration: extend merge-family parity coverage.
    Oracle: add local merge-commit scenarios.

21. `Rerere`
    Deliverables: rr-cache writes, auto-resolution, and repeated-conflict
    reuse parity.
    Integration: extend
    [`tests/Integration/RerereTest.php`](tests/Integration/RerereTest.php).
    Oracle: add local rerere scenarios and bind merge-conflict upstream cases.

## Wave 3: Storage, Formats, And Protocol Depth

22. `Pkt-line encoding/decoding`
    Deliverables: direct Git-backed packet framing roundtrips.
    Integration: add protocol framing parity coverage.
    Oracle: add local pkt-line scenarios and bind
    [`scenarios/upstream/git-test-suite/t5530-upload-pack-error/scenario.json`](scenarios/upstream/git-test-suite/t5530-upload-pack-error/scenario.json).

23. `LEB128 unsigned`
    Deliverables: codec-level Git-backed parity instead of inferred pack
    correctness.
    Integration: add codec parity coverage.
    Oracle: add local codec scenarios built from Git-generated pack data.

24. `Git varint (MSB-continue)`
    Deliverables: direct pack-header parity for Git varint decoding.
    Integration: extend codec parity coverage.
    Oracle: add local pack-header scenarios.

25. `OFS_DELTA offset encoding`
    Deliverables: direct offset-encoding parity instead of inferred delta
    behavior.
    Integration: extend codec parity coverage.
    Oracle: add local delta-offset scenarios.

26. `Binary reader`
    Deliverables: dedicated corruption and reader-boundary parity instead of
    purely transitive parser confidence.
    Integration: extend codec and parser corruption coverage.
    Oracle: bind object, pack, and reftable corruption scenarios.

27. `Pkt-line format`
    Deliverables: format-level error handling and Git-backed roundtrip
    truthfulness.
    Integration: extend protocol framing parity coverage.
    Oracle: bind
    [`scenarios/upstream/git-test-suite/t5704-protocol-violations/scenario.json`](scenarios/upstream/git-test-suite/t5704-protocol-violations/scenario.json).

28. `Multi-pack-index (MIDX)`
    Deliverables: Git-generated MIDX files in recurring sweeps plus corruption
    handling parity.
    Integration: extend
    [`tests/Integration/CommitGraphAndMidxTest.php`](tests/Integration/CommitGraphAndMidxTest.php).
    Oracle: add local MIDX scenarios and bind the upstream MIDX scenarios.

29. `Commit-graph file`
    Deliverables: explicit supported scope plus Git-backed commit-graph chain
    parity.
    Integration: extend commit-graph parity coverage.
    Oracle: add local commit-graph scenarios and bind the upstream
    commit-graph scenarios.

30. `Reftable format`
    Deliverables: real Git-backed reftable parity or immediate claim
    correction.
    Integration: extend
    [`tests/Integration/ReftableTest.php`](tests/Integration/ReftableTest.php).
    Oracle: add local reftable scenarios and bind
    [`scenarios/upstream/git-test-suite/t0612-reftable-jgit-compatibility/scenario.json`](scenarios/upstream/git-test-suite/t0612-reftable-jgit-compatibility/scenario.json),
    [`scenarios/upstream/git-test-suite/t0613-reftable-write-options/scenario.json`](scenarios/upstream/git-test-suite/t0613-reftable-write-options/scenario.json),
    and [`scenarios/upstream/git-test-suite/t0614-reftable-fsck/scenario.json`](scenarios/upstream/git-test-suite/t0614-reftable-fsck/scenario.json).

31. `ObjectId SHA-256`
    Deliverables: true Git-backed SHA-256 repository-mode parity or scope
    correction if repo-mode support is not actually there.
    Integration: add SHA-256 repo-mode coverage.
    Oracle: import and bind Git SHA-256 scenarios.

## Wave 4: Advanced Features And External Oracles

32. `Bisect`
    Deliverables: traversal, skip/good/bad state, and reset truthfulness under
    direct Git comparison.
    Integration: extend [`tests/Integration/BisectTest.php`](tests/Integration/BisectTest.php).
    Oracle: import upstream bisect scenarios and add a local lifecycle
    scenario.

33. `Fsmonitor`
    Deliverables: real Git/fsmonitor oracle coverage or honest partial scope
    correction.
    Integration: extend [`tests/Integration/FsmonitorTest.php`](tests/Integration/FsmonitorTest.php)
    if implementation scope remains.
    Oracle: add a real fsmonitor oracle or keep the row partial.

34. `Hooks`
    Deliverables: env propagation, hook ordering, rejection paths, and push
    hook truthfulness.
    Integration: extend [`tests/Integration/HooksTest.php`](tests/Integration/HooksTest.php).
    Oracle: add local hook scenarios and bind the upstream hook scenarios.

35. `Git LFS`
    Deliverables: either vendor a `git-lfs` parity harness or keep the row
    explicitly external with matching public wording.
    Integration: add `git-lfs` integration coverage if the harness lands.
    Oracle: external to stock Git and requires `git-lfs`.

36. `Shallow clones`
    Deliverables: end-to-end shallow clone and fetch negotiation parity.
    Integration: add shallow remote parity coverage.
    Oracle: add local shallow transport scenarios and bind existing upstream
    shallow scenarios.

37. `Git grep`
    Deliverables: sparse-checkout and submodule-aware grep truthfulness.
    Integration: extend [`tests/Integration/GrepParityTest.php`](tests/Integration/GrepParityTest.php).
    Oracle: add local grep scenarios and bind
    [`scenarios/upstream/git-test-suite/t7817-grep-sparse-checkout/scenario.json`](scenarios/upstream/git-test-suite/t7817-grep-sparse-checkout/scenario.json)
    and [`scenarios/upstream/git-test-suite/t7814-grep-recurse-submodules/scenario.json`](scenarios/upstream/git-test-suite/t7814-grep-recurse-submodules/scenario.json).

38. `SSH transport`
    Deliverables: repo-local Git-over-SSH fixture/server parity, or stable
    external-oracle wording with no Git-oracle overclaim.
    Integration: add SSH parity coverage once the fixture exists.
    Oracle: repo-local SSH scenarios only; stock Git is not enough by itself.

## Closure

- After Waves 1-4, update [`ORACLE_PARITY_TODO.md`](ORACLE_PARITY_TODO.md),
  [`SUPPORT_MATRIX.md`](SUPPORT_MATRIX.md), and public docs immediately.
- Add every new local scenario to recurring sweeps rather than relying on ad
  hoc runs.
- Delete this file only if the queue is truly empty; otherwise regenerate it
  from whatever non-`Mapped` rows remain.
