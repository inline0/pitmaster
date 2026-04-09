# Session Execution Queue

This file is the execution order for the remaining non-`Mapped` rows in
[`ORACLE_PARITY_TODO.md`](ORACLE_PARITY_TODO.md).

Use it for long autonomous passes. The audit file remains the source of truth;
this file is the ordered burn-down plan.

## Current Target

- Remaining audit rows: `40`
- State mix: `38 Weak`, `2 External oracle`
- Goal of this queue: convert the remaining rows into concrete multi-hour work
  waves so a single pass can close many rows without stopping after one fix.

## Done Rule

An item is only done when all of the following land together:

1. implementation or claim correction
2. Git-backed integration coverage under [`tests/Integration`](tests/Integration)
3. oracle scenario coverage under [`scenarios`](scenarios)
4. audit/support-matrix updates
5. green [`./bin/verify-all`](./bin/verify-all) after the wave

## Default Operating Rule

- Work top-to-bottom.
- Target `10+` completed items per autonomous pass.
- Do not stop after a single row unless the repo is red or a real product
  decision blocks the next step.
- Item numbers below are stable IDs. Gaps mean a queue item was completed and
  removed in an earlier pass.

## Wave 1: Repo State And Sequencer Parity

4. Checkout and detach parity
   Rows: `Checkout / switch branch`
   Deliverables: finish branch recreation and unborn-branch behavior on top of
   the now-proven overwrite protection.
   Integration: extend checkout/reflog suites with explicit Git comparisons.
   Oracle: keep the local checkout scenarios and bind
   [`scenarios/upstream/git-test-suite/t2018-checkout-branch/scenario.json`](scenarios/upstream/git-test-suite/t2018-checkout-branch/scenario.json),
   [`scenarios/upstream/git-test-suite/t2060-switch/scenario.json`](scenarios/upstream/git-test-suite/t2060-switch/scenario.json),
   [`scenarios/checkout/checkout-overwrite-tracked/scenario.json`](scenarios/checkout/checkout-overwrite-tracked/scenario.json),
   [`scenarios/checkout/checkout-overwrite-untracked/scenario.json`](scenarios/checkout/checkout-overwrite-untracked/scenario.json),
   and [`scenarios/upstream/git-test-suite/t2015-checkout-unborn/scenario.json`](scenarios/upstream/git-test-suite/t2015-checkout-unborn/scenario.json).

6. Restore parity
   Rows: `git restore`
   Deliverables: file/index restore parity, safety checks, and patch-mode
   truthfulness.
   Integration: add restore parity integration.
   Oracle: add local restore scenarios and bind
   [`scenarios/upstream/git-test-suite/t2070-restore/scenario.json`](scenarios/upstream/git-test-suite/t2070-restore/scenario.json)
   and [`scenarios/upstream/git-test-suite/t2071-restore-patch/scenario.json`](scenarios/upstream/git-test-suite/t2071-restore-patch/scenario.json).

7. Remove and move parity
   Rows: `git rm (unstage/remove)`, `git mv (rename)`
   Deliverables: explicit path removal, recursive removal, rename,
   worktree/index state, and follow-up commit parity.
   Integration: add dedicated `rm` / `mv` parity tests.
   Oracle: add local scenarios and bind
   [`scenarios/upstream/git-test-suite/t3600-rm/scenario.json`](scenarios/upstream/git-test-suite/t3600-rm/scenario.json)
   and [`scenarios/upstream/git-suite/rename-detection/scenario.json`](scenarios/upstream/git-suite/rename-detection/scenario.json).

8. Stash command parity closure
   Rows: `git stash`
   Deliverables: staged-only, include-untracked, conflict-on-apply, and
   linked-worktree stash semantics under direct Git comparison.
   Integration: extend [`tests/Integration/StashParityTest.php`](tests/Integration/StashParityTest.php).
   Oracle: add/expand local stash scenarios and bind
   [`scenarios/upstream/git-test-suite/t3903-stash/scenario.json`](scenarios/upstream/git-test-suite/t3903-stash/scenario.json),
   [`scenarios/upstream/git-test-suite/t3905-stash-include-untracked/scenario.json`](scenarios/upstream/git-test-suite/t3905-stash-include-untracked/scenario.json),
   and [`scenarios/upstream/git-suite/stash/scenario.json`](scenarios/upstream/git-suite/stash/scenario.json).

9. Rebase closure
   Rows: `git rebase`
   Deliverables: interactive/edit flows, merge-preserving rebases, and full
   reflog parity for sequencer state.
   Integration: extend [`tests/Integration/RebaseParityTest.php`](tests/Integration/RebaseParityTest.php).
   Oracle: add rebase scenario families and bind
   [`scenarios/upstream/git-test-suite/t3400-rebase/scenario.json`](scenarios/upstream/git-test-suite/t3400-rebase/scenario.json),
   [`scenarios/upstream/git-test-suite/t3418-rebase-continue/scenario.json`](scenarios/upstream/git-test-suite/t3418-rebase-continue/scenario.json),
   [`scenarios/upstream/git-test-suite/t3430-rebase-merges/scenario.json`](scenarios/upstream/git-test-suite/t3430-rebase-merges/scenario.json),
   and [`scenarios/upstream/libgit2/rebase/scenario.json`](scenarios/upstream/libgit2/rebase/scenario.json).

10. Show public parity
    Rows: `git show`
    Deliverables: Git-backed diff-text parity for `Repository::show()`,
    including merge commits, tag targets, and formatting details instead of
    only commit metadata and changed-path enumeration.
    Integration: extend [`tests/Integration/LogShowParityTest.php`](tests/Integration/LogShowParityTest.php).
    Oracle: extend [`scenarios/log/log-public-parity/scenario.json`](scenarios/log/log-public-parity/scenario.json)
    and bind [`scenarios/upstream/git-test-suite/t7007-show/scenario.json`](scenarios/upstream/git-test-suite/t7007-show/scenario.json)
    plus [`scenarios/upstream/git-test-suite/t4063-diff-blobs/scenario.json`](scenarios/upstream/git-test-suite/t4063-diff-blobs/scenario.json).

## Wave 2: Diff And Merge Truthfulness

11. Status-side rename detection parity
    Rows: `Rename detection`
    Deliverables: explicit Git-backed status rename heuristics and thresholds.
    Integration: add rename-aware status parity tests.
    Oracle: bind
    [`scenarios/upstream/git-test-suite/t7525-status-rename/scenario.json`](scenarios/upstream/git-test-suite/t7525-status-rename/scenario.json).

12. Diff-side rename detection parity
    Rows: `Rename detection (diff)`
    Deliverables: binary rename, partial rename, and diff-pair parity.
    Integration: extend tree diff parity.
    Oracle: bind
    [`scenarios/upstream/git-test-suite/t4043-diff-rename-binary/scenario.json`](scenarios/upstream/git-test-suite/t4043-diff-rename-binary/scenario.json)
    and [`scenarios/upstream/git-test-suite/t4070-diff-pairs/scenario.json`](scenarios/upstream/git-test-suite/t4070-diff-pairs/scenario.json).

13. Alternative diff algorithms parity
    Rows: `Patience diff algorithm`, `Histogram diff algorithm`, `Minimal diff`
    Deliverables: Git-backed algorithm comparisons through public diff APIs/CLI.
    Integration: add a dedicated diff algorithm parity suite.
    Oracle: add local scenarios and bind upstream diff suites.

14. Word and color diff parity
    Rows: `Word diff`, `Color diff output`
    Deliverables: Git-backed output parity for word segmentation and ANSI
    coloring rules.
    Integration: add dedicated CLI diff rendering tests.
    Oracle: add local word/color diff scenarios and bind
    [`scenarios/upstream/libgit2/userdiff/scenario.json`](scenarios/upstream/libgit2/userdiff/scenario.json)
    plus relevant git-suite diff scenarios.

15. Merge content parity
    Rows: `Three-way merge (content)`, `Conflict markers`, `File-level merge (tree)`
    Deliverables: whitespace, symlink, deletion, diff3 markers, and tree-level
    rename/delete parity.
    Integration: extend merge-family parity tests.
    Oracle: add local merge-content scenarios and bind
    [`scenarios/upstream/git-test-suite/t6427-diff3-conflict-markers/scenario.json`](scenarios/upstream/git-test-suite/t6427-diff3-conflict-markers/scenario.json),
    [`scenarios/upstream/git-test-suite/t6425-merge-rename-delete/scenario.json`](scenarios/upstream/git-test-suite/t6425-merge-rename-delete/scenario.json),
    and [`scenarios/upstream/git-test-suite/t4300-merge-tree/scenario.json`](scenarios/upstream/git-test-suite/t4300-merge-tree/scenario.json).

16. Merge strategy parity
    Rows: `Recursive strategy`, `ORT strategy`, `Ours strategy`
    Deliverables: either implement/publicly expose the claimed strategy or
    narrow the claim immediately.
    Integration: add strategy-specific repository-level parity tests.
    Oracle: bind
    [`scenarios/upstream/git-test-suite/t6430-merge-recursive/scenario.json`](scenarios/upstream/git-test-suite/t6430-merge-recursive/scenario.json),
    [`scenarios/upstream/git-test-suite/t6431-merge-criscross/scenario.json`](scenarios/upstream/git-test-suite/t6431-merge-criscross/scenario.json),
    and [`scenarios/upstream/git-test-suite/t6417-merge-ours-theirs/scenario.json`](scenarios/upstream/git-test-suite/t6417-merge-ours-theirs/scenario.json).

17. Merge commit parity
    Rows: `Merge commit creation`
    Deliverables: commit message generation, state files, and conflict
    continuation parity.
    Integration: extend merge-family parity tests and public merge API checks.
    Oracle: add local merge-commit scenarios.

18. Octopus parity
    Rows: `Octopus merge`
    Deliverables: real Git-backed octopus merge creation and state validation.
    Integration: add octopus repository-level integration.
    Oracle: add local octopus scenario and bind
    [`scenarios/upstream/git-suite/octopus-merge/scenario.json`](scenarios/upstream/git-suite/octopus-merge/scenario.json)
    and [`scenarios/upstream/git-test-suite/t7602-merge-octopus-many/scenario.json`](scenarios/upstream/git-test-suite/t7602-merge-octopus-many/scenario.json).

19. Rerere parity
    Rows: `Rerere`
    Deliverables: rr-cache writes, auto-resolution, and reuse across repeated
    conflicts under direct Git comparison.
    Integration: extend [`tests/Integration/RerereTest.php`](tests/Integration/RerereTest.php).
    Oracle: add local rerere scenarios and bind merge-conflict upstream cases.

## Wave 3: Protocol, Storage, And Encoding Depth

20. Packet framing parity
    Rows: `Pkt-line encoding/decoding`, `Pkt-line format`
    Deliverables: direct Git-backed pkt-line framing roundtrips and error cases.
    Integration: add protocol framing parity tests.
    Oracle: add local pkt-line scenarios and bind
    [`scenarios/upstream/git-test-suite/t5530-upload-pack-error/scenario.json`](scenarios/upstream/git-test-suite/t5530-upload-pack-error/scenario.json)
    and [`scenarios/upstream/git-test-suite/t5704-protocol-violations/scenario.json`](scenarios/upstream/git-test-suite/t5704-protocol-violations/scenario.json).

21. Binary reader and varint parity
    Rows: `LEB128 unsigned`, `Git varint (MSB-continue)`, `OFS_DELTA offset encoding`, `Binary reader`
    Deliverables: dedicated Git-backed codec assertions instead of purely
    inferred pack coverage.
    Integration: add low-level parity fixtures plus corruption coverage.
    Oracle: add local codec scenarios built from Git-generated pack/index data.

22. MIDX parity
    Rows: `Multi-pack-index (MIDX)`
    Deliverables: prove Git-generated MIDX files in recurring sweeps and harden
    corruption handling.
    Integration: extend [`tests/Integration/CommitGraphAndMidxTest.php`](tests/Integration/CommitGraphAndMidxTest.php).
    Oracle: add local midx scenario family and bind
    [`scenarios/upstream/git-test-suite/t5319-multi-pack-index/scenario.json`](scenarios/upstream/git-test-suite/t5319-multi-pack-index/scenario.json),
    [`scenarios/upstream/git-test-suite/t5334-incremental-multi-pack-index/scenario.json`](scenarios/upstream/git-test-suite/t5334-incremental-multi-pack-index/scenario.json),
    and [`scenarios/upstream/git-test-suite/t5335-compact-multi-pack-index/scenario.json`](scenarios/upstream/git-test-suite/t5335-compact-multi-pack-index/scenario.json).

23. Commit-graph parity
    Rows: `Commit-graph file`
    Deliverables: clarify read/write scope and prove it against Git-generated
    commit-graph chains.
    Integration: extend commit-graph integration coverage.
    Oracle: add local commit-graph scenarios and bind
    [`scenarios/upstream/git-test-suite/t5324-split-commit-graph/scenario.json`](scenarios/upstream/git-test-suite/t5324-split-commit-graph/scenario.json),
    [`scenarios/upstream/git-test-suite/t5328-commit-graph-64bit-time/scenario.json`](scenarios/upstream/git-test-suite/t5328-commit-graph-64bit-time/scenario.json),
    and [`scenarios/upstream/git-test-suite/t5330-no-lazy-fetch-with-commit-graph/scenario.json`](scenarios/upstream/git-test-suite/t5330-no-lazy-fetch-with-commit-graph/scenario.json).

24. Reftable parity
    Rows: `Reftable format`
    Deliverables: either real Git-backed reftable parity or an explicit scope
    downgrade if the feature is only a parser smoke path.
    Integration: extend [`tests/Integration/ReftableTest.php`](tests/Integration/ReftableTest.php).
    Oracle: add local reftable scenarios and bind
    [`scenarios/upstream/git-test-suite/t0612-reftable-jgit-compatibility/scenario.json`](scenarios/upstream/git-test-suite/t0612-reftable-jgit-compatibility/scenario.json),
    [`scenarios/upstream/git-test-suite/t0613-reftable-write-options/scenario.json`](scenarios/upstream/git-test-suite/t0613-reftable-write-options/scenario.json),
    and [`scenarios/upstream/git-test-suite/t0614-reftable-fsck/scenario.json`](scenarios/upstream/git-test-suite/t0614-reftable-fsck/scenario.json).

25. SHA-256 parity
    Rows: `ObjectId SHA-256`
    Deliverables: true Git-backed SHA-256 repository-mode tests and imported
    scenario anchors, or a scope correction if full repo-mode support is not
    actually implemented.
    Integration: add SHA-256 repo-mode integration tests.
    Oracle: import Git SHA-256 scenarios and bind them into compliance.

## Wave 4: Advanced Features And External Oracles

26. Shallow transport parity
    Rows: `Shallow clones`
    Deliverables: real end-to-end shallow clone/fetch negotiation instead of
    only shallow-file semantics.
    Integration: add shallow remote parity tests.
    Oracle: add local shallow transport scenarios and bind existing upstream
    shallow scenarios.

27. Grep closure
    Rows: `Git grep`
    Deliverables: sparse-checkout and submodule-aware grep parity plus any CLI
    option deltas still missing.
    Integration: extend [`tests/Integration/GrepParityTest.php`](tests/Integration/GrepParityTest.php).
    Oracle: add local sparse/submodule grep scenarios and bind
    [`scenarios/upstream/git-test-suite/t7817-grep-sparse-checkout/scenario.json`](scenarios/upstream/git-test-suite/t7817-grep-sparse-checkout/scenario.json)
    and [`scenarios/upstream/git-test-suite/t7814-grep-recurse-submodules/scenario.json`](scenarios/upstream/git-test-suite/t7814-grep-recurse-submodules/scenario.json).

28. Hooks parity
    Rows: `Hooks`
    Deliverables: env propagation, hook ordering, rejection paths, and push hook
    parity under direct Git comparison.
    Integration: extend [`tests/Integration/HooksTest.php`](tests/Integration/HooksTest.php).
    Oracle: add local hook scenarios and bind
    [`scenarios/upstream/git-test-suite/t7503-pre-commit-and-pre-merge-commit-hooks/scenario.json`](scenarios/upstream/git-test-suite/t7503-pre-commit-and-pre-merge-commit-hooks/scenario.json),
    [`scenarios/upstream/git-test-suite/t7504-commit-msg-hook/scenario.json`](scenarios/upstream/git-test-suite/t7504-commit-msg-hook/scenario.json),
    and [`scenarios/upstream/git-test-suite/t5571-pre-push-hook/scenario.json`](scenarios/upstream/git-test-suite/t5571-pre-push-hook/scenario.json).

29. Bisect parity
    Rows: `Bisect`
    Deliverables: Git-backed traversal/state parity and oracle scenarios.
    Integration: extend [`tests/Integration/BisectTest.php`](tests/Integration/BisectTest.php).
    Oracle: import upstream bisect scenarios and add a local lifecycle scenario.

30. Fsmonitor truthfulness
    Rows: `Fsmonitor`
    Deliverables: either real Git/fsmonitor oracle coverage or a truthful scope
    downgrade if the current implementation is only a local helper.
    Integration: extend [`tests/Integration/FsmonitorTest.php`](tests/Integration/FsmonitorTest.php).
    Oracle: add a real fsmonitor oracle or leave partial.

31. SSH transport oracle path
    Rows: `SSH transport`
    Deliverables: stand up a repo-local Git-over-SSH test fixture/server and
    prove fetch/push parity, or explicitly keep the row partial/external.
    Integration: add SSH parity integration once fixture exists.
    Oracle: repo-local SSH scenarios only; stock Git is not enough by itself.

32. Git LFS oracle path
    Rows: `Git LFS`
    Deliverables: decide whether Pitmaster will vendor a `git-lfs` parity
    harness. If yes, build it; if no, keep the row clearly external/partial.
    Integration: add `git-lfs` integration coverage if the harness lands.
    Oracle: external to stock Git; requires `git-lfs`.

## Wave 5: Public Claim Cleanup And Sweep Expansion

33. Support-matrix closure pass
    Rows: all still non-`Mapped` rows after Waves 1-4
    Deliverables: move any genuinely closed row to `Mapped`; immediately correct
    any row that is still only partial.

34. Compliance sweep expansion
    Rows: all newly closed rows
    Deliverables: ensure every new local scenario is in the recurring
    regression/compliance path and no item relies on ad hoc manual runs.

35. Docs/API claim sweep
    Rows: any row whose public docs overstate support
    Deliverables: align README, docs, and matrix wording with the post-wave
    reality.

36. Final queue regeneration
    Rows: whatever remains after this queue
    Deliverables: delete this file only if every row is `Mapped`; otherwise
    regenerate it from the remaining non-`Mapped` rows so the next autonomous
    pass starts with a fresh execution order.
