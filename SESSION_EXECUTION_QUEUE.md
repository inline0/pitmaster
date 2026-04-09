# Session Execution Queue

This file is the ordered burn-down plan for the remaining non-`Mapped` rows in
[`ORACLE_PARITY_TODO.md`](ORACLE_PARITY_TODO.md).

Use it for long autonomous passes. The audit file remains the source of truth;
this file is the execution order.

## Current Target

- Remaining audit rows: `38`
- State mix: `36 Weak`, `2 External oracle`
- Current prerequisite: keep the just-fixed cross-platform CI slice green while
  burning down parity.
- Goal of this queue: finish large batches without stopping after one feature,
  and convert every remaining row into explicit implementation, proof, or
  honest scope correction.

## Done Rule

An item is only done when all of the following land together:

1. implementation or honest scope correction
2. Git-backed integration coverage under
   [`tests/Integration`](tests/Integration)
3. oracle scenario coverage under [`scenarios`](scenarios)
4. audit and support-matrix updates
5. green [`./bin/verify-all`](./bin/verify-all) after the wave

## Default Operating Rule

- Work top-to-bottom.
- Target `12+` completed items per autonomous pass.
- Do not stop after a single row unless the repo is red or a real product
  decision blocks the next step.
- Finish an entire wave before pausing when feasible.
- If an item turns out to require a scope downgrade instead of implementation,
  do that immediately and keep moving.
- Item numbers are stable IDs. Gaps mean an item was completed in an earlier
  pass and removed from the active queue.

## Wave 1: Repo Porcelain And State Parity

3. Restore worktree parity
   Rows: `git restore`
   Deliverables: tracked-file restore, staged restore, and overwrite safety.
   Integration: add dedicated restore parity integration.
   Oracle: add local restore scenarios and bind
   [`scenarios/upstream/git-test-suite/t2070-restore/scenario.json`](scenarios/upstream/git-test-suite/t2070-restore/scenario.json).

4. Restore patch-mode truthfulness
   Rows: `git restore`
   Deliverables: patch-mode selection and resulting index/worktree state parity.
   Integration: extend the restore parity suite with Git-backed patch flows.
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t2071-restore-patch/scenario.json`](scenarios/upstream/git-test-suite/t2071-restore-patch/scenario.json).

5. Remove command parity
   Rows: `git rm (unstage/remove)`
   Deliverables: tracked removal, recursive removal, and empty tree
   transitions on top of the now-proven cached-remove path.
   Integration: add dedicated `rm` parity coverage.
   Oracle: add local `rm` scenarios and bind
   [`scenarios/upstream/git-test-suite/t3600-rm/scenario.json`](scenarios/upstream/git-test-suite/t3600-rm/scenario.json).

7. Stash staged-only parity
   Rows: `git stash`
   Deliverables: staged-only stash semantics under direct Git comparison.
   Integration: extend
   [`tests/Integration/StashParityTest.php`](tests/Integration/StashParityTest.php).
   Oracle: keep local staged-only stash scenarios in recurring sweeps.

8. Stash include-untracked parity
   Rows: `git stash`
   Deliverables: include-untracked behavior, restore behavior, and follow-up
   status parity.
   Integration: extend
   [`tests/Integration/StashParityTest.php`](tests/Integration/StashParityTest.php).
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t3905-stash-include-untracked/scenario.json`](scenarios/upstream/git-test-suite/t3905-stash-include-untracked/scenario.json).

9. Stash conflict and worktree parity
   Rows: `git stash`
   Deliverables: apply/pop conflict semantics and linked-worktree stash truth.
   Integration: extend
   [`tests/Integration/StashParityTest.php`](tests/Integration/StashParityTest.php).
   Oracle: keep stash conflict/worktree scenarios in regression.

10. Rebase edit-flow parity
   Rows: `git rebase`
   Deliverables: stop-for-edit, amend, and continue semantics.
   Integration: extend
   [`tests/Integration/RebaseParityTest.php`](tests/Integration/RebaseParityTest.php).
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t3418-rebase-continue/scenario.json`](scenarios/upstream/git-test-suite/t3418-rebase-continue/scenario.json).

11. Rebase merges parity
   Rows: `git rebase`
   Deliverables: merge-preserving rebase behavior and state-file truthfulness.
   Integration: extend
   [`tests/Integration/RebaseParityTest.php`](tests/Integration/RebaseParityTest.php).
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t3430-rebase-merges/scenario.json`](scenarios/upstream/git-test-suite/t3430-rebase-merges/scenario.json)
   and [`scenarios/upstream/libgit2/rebase/scenario.json`](scenarios/upstream/libgit2/rebase/scenario.json).

12. Rebase reflog parity
   Rows: `git rebase`
   Deliverables: HEAD and branch reflog entries through the full sequencer
   lifecycle.
   Integration: extend
   [`tests/Integration/RebaseParityTest.php`](tests/Integration/RebaseParityTest.php)
   and [`tests/Integration/ReflogTest.php`](tests/Integration/ReflogTest.php).
   Oracle: extend the local rebase scenarios with reflog assertions.

13. Show diff-text parity
   Rows: `git show`
   Deliverables: public diff output parity for simple commits and blob targets.
   Integration: extend
   [`tests/Integration/LogShowParityTest.php`](tests/Integration/LogShowParityTest.php).
   Oracle: extend
   [`scenarios/log/log-public-parity/scenario.json`](scenarios/log/log-public-parity/scenario.json)
   and bind
   [`scenarios/upstream/git-test-suite/t4063-diff-blobs/scenario.json`](scenarios/upstream/git-test-suite/t4063-diff-blobs/scenario.json).

14. Show merge and tag parity
   Rows: `git show`
   Deliverables: merge-commit display, annotated-tag targets, and formatting
   parity.
   Integration: extend
   [`tests/Integration/LogShowParityTest.php`](tests/Integration/LogShowParityTest.php).
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t7007-show/scenario.json`](scenarios/upstream/git-test-suite/t7007-show/scenario.json).

## Wave 2: Diff And Merge Truthfulness

15. Status rename detection parity
   Rows: `Rename detection`
   Deliverables: explicit Git-backed status rename heuristics and thresholds.
   Integration: add rename-aware status parity coverage.
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t7525-status-rename/scenario.json`](scenarios/upstream/git-test-suite/t7525-status-rename/scenario.json).

16. Diff rename detection parity
   Rows: `Rename detection (diff)`
   Deliverables: binary rename, partial rename, and diff-pair parity.
   Integration: extend
   [`tests/Integration/TreeDiffTest.php`](tests/Integration/TreeDiffTest.php).
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t4043-diff-rename-binary/scenario.json`](scenarios/upstream/git-test-suite/t4043-diff-rename-binary/scenario.json)
   and [`scenarios/upstream/git-test-suite/t4070-diff-pairs/scenario.json`](scenarios/upstream/git-test-suite/t4070-diff-pairs/scenario.json).

17. Patience diff parity
   Rows: `Patience diff algorithm`
   Deliverables: Git-backed patience diff output parity.
   Integration: add a dedicated diff algorithm parity suite.
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t4065-diff-anchored/scenario.json`](scenarios/upstream/git-test-suite/t4065-diff-anchored/scenario.json).

18. Histogram diff parity
   Rows: `Histogram diff algorithm`
   Deliverables: Git-backed histogram diff output parity.
   Integration: extend the diff algorithm parity suite.
   Oracle: bind relevant upstream diff scenarios under
   [`scenarios/upstream/libgit2/diff/scenario.json`](scenarios/upstream/libgit2/diff/scenario.json).

19. Minimal diff parity
   Rows: `Minimal diff`
   Deliverables: Git-backed minimal diff output parity.
   Integration: extend the diff algorithm parity suite.
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t4035-diff-quiet/scenario.json`](scenarios/upstream/git-test-suite/t4035-diff-quiet/scenario.json).

20. Word diff parity
   Rows: `Word diff`
   Deliverables: word segmentation and hunk rendering truthfulness.
   Integration: add dedicated word-diff parity coverage.
   Oracle: add local word-diff scenarios and bind
   [`scenarios/upstream/libgit2/userdiff/scenario.json`](scenarios/upstream/libgit2/userdiff/scenario.json).

21. Color diff parity
   Rows: `Color diff output`
   Deliverables: ANSI colorized diff output parity.
   Integration: add dedicated color-diff parity coverage.
   Oracle: add local color-diff scenarios and bind relevant upstream diff
   output scenarios.

22. Merge content edge-case parity
   Rows: `Three-way merge (content)`
   Deliverables: whitespace, symlink, and deletion merge truthfulness.
   Integration: extend
   [`tests/Integration/MergeFamilyParityTest.php`](tests/Integration/MergeFamilyParityTest.php).
   Oracle: bind
   [`scenarios/upstream/libgit2/twowaymerge-git/scenario.json`](scenarios/upstream/libgit2/twowaymerge-git/scenario.json).

23. Conflict marker parity
   Rows: `Conflict markers`
   Deliverables: diff3 marker styles and conflict-file text parity.
   Integration: extend
   [`tests/Integration/MergeFamilyParityTest.php`](tests/Integration/MergeFamilyParityTest.php).
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t6427-diff3-conflict-markers/scenario.json`](scenarios/upstream/git-test-suite/t6427-diff3-conflict-markers/scenario.json).

24. Tree merge parity
   Rows: `File-level merge (tree)`
   Deliverables: rename/delete and subtree merge behavior under Git
   comparison.
   Integration: extend merge-family and tree-diff parity suites.
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t4300-merge-tree/scenario.json`](scenarios/upstream/git-test-suite/t4300-merge-tree/scenario.json)
   and [`scenarios/upstream/git-test-suite/t6425-merge-rename-delete/scenario.json`](scenarios/upstream/git-test-suite/t6425-merge-rename-delete/scenario.json).

25. Recursive strategy deep parity
   Rows: `Recursive strategy`
   Deliverables: multiple-base and rename-caching truthfulness.
   Integration: extend merge-family parity coverage.
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t6430-merge-recursive/scenario.json`](scenarios/upstream/git-test-suite/t6430-merge-recursive/scenario.json)
   and [`scenarios/upstream/libgit2/merge-recursive/scenario.json`](scenarios/upstream/libgit2/merge-recursive/scenario.json).

26. ORT strategy decision
   Rows: `ORT strategy`
   Deliverables: either implement and prove real ORT semantics or narrow the
   claim immediately.
   Integration: add strategy-specific parity coverage if implementation lands.
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t6431-merge-criscross/scenario.json`](scenarios/upstream/git-test-suite/t6431-merge-criscross/scenario.json).

27. Octopus merge parity
   Rows: `Octopus merge`
   Deliverables: real repository-level octopus merge creation and state
   parity.
   Integration: add octopus integration coverage.
   Oracle: add a local octopus scenario and bind
   [`scenarios/upstream/git-test-suite/t7602-merge-octopus-many/scenario.json`](scenarios/upstream/git-test-suite/t7602-merge-octopus-many/scenario.json).

28. Ours strategy parity
   Rows: `Ours strategy`
   Deliverables: public repository-level strategy path and Git-backed parity.
   Integration: add repository-level strategy tests.
   Oracle: bind
   [`scenarios/upstream/git-test-suite/t6417-merge-ours-theirs/scenario.json`](scenarios/upstream/git-test-suite/t6417-merge-ours-theirs/scenario.json).

29. Merge commit creation parity
   Rows: `Merge commit creation`
   Deliverables: commit message generation, state files, and continuation
   semantics.
   Integration: extend merge-family parity coverage.
   Oracle: add local merge-commit scenarios.

30. Rerere parity
   Rows: `Rerere`
   Deliverables: rr-cache writes, auto-resolution, and reuse across repeated
   conflicts.
   Integration: extend
   [`tests/Integration/RerereTest.php`](tests/Integration/RerereTest.php).
   Oracle: add local rerere scenarios and bind merge-conflict upstream cases.

## Wave 3: Storage, Formats, And Protocol Framing

31. Pkt-line framing parity
   Rows: `Pkt-line encoding/decoding`, `Pkt-line format`
   Deliverables: direct Git-backed packet framing roundtrips and error cases.
   Integration: add protocol framing parity coverage.
   Oracle: add local pkt-line scenarios and bind
   [`scenarios/upstream/git-test-suite/t5530-upload-pack-error/scenario.json`](scenarios/upstream/git-test-suite/t5530-upload-pack-error/scenario.json)
   and [`scenarios/upstream/git-test-suite/t5704-protocol-violations/scenario.json`](scenarios/upstream/git-test-suite/t5704-protocol-violations/scenario.json).

32. Codec parity
   Rows: `LEB128 unsigned`, `Git varint (MSB-continue)`,
   `OFS_DELTA offset encoding`, `Binary reader`
   Deliverables: direct Git-backed codec assertions instead of purely inferred
   pack coverage.
   Integration: add codec parity coverage plus corruption cases.
   Oracle: add local codec scenarios built from Git-generated pack/index data.

33. MIDX parity
   Rows: `Multi-pack-index (MIDX)`
   Deliverables: Git-generated MIDX files in recurring sweeps plus corruption
   handling truthfulness.
   Integration: extend
   [`tests/Integration/CommitGraphAndMidxTest.php`](tests/Integration/CommitGraphAndMidxTest.php).
   Oracle: add local MIDX scenarios and bind the upstream MIDX scenarios.

34. Commit-graph parity
   Rows: `Commit-graph file`
   Deliverables: prove actual supported scope against Git-generated
   commit-graph chains.
   Integration: extend
   [`tests/Integration/CommitGraphAndMidxTest.php`](tests/Integration/CommitGraphAndMidxTest.php).
   Oracle: add local commit-graph scenarios and bind the upstream
   commit-graph scenarios.

35. Reftable parity
   Rows: `Reftable format`
   Deliverables: real Git-backed reftable parity or immediate scope correction.
   Integration: extend
   [`tests/Integration/ReftableTest.php`](tests/Integration/ReftableTest.php).
   Oracle: add local reftable scenarios and bind
   [`scenarios/upstream/git-test-suite/t0612-reftable-jgit-compatibility/scenario.json`](scenarios/upstream/git-test-suite/t0612-reftable-jgit-compatibility/scenario.json),
   [`scenarios/upstream/git-test-suite/t0613-reftable-write-options/scenario.json`](scenarios/upstream/git-test-suite/t0613-reftable-write-options/scenario.json),
   and [`scenarios/upstream/git-test-suite/t0614-reftable-fsck/scenario.json`](scenarios/upstream/git-test-suite/t0614-reftable-fsck/scenario.json).

36. SHA-256 repository-mode parity
   Rows: `ObjectId SHA-256`
   Deliverables: true Git-backed SHA-256 repository-mode tests and scenario
   anchors, or a scope correction if repo-mode support is not actually there.
   Integration: add SHA-256 repo-mode integration coverage.
   Oracle: import and bind Git SHA-256 scenarios.

## Wave 4: Advanced Features And External Oracles

37. Shallow transport parity
   Rows: `Shallow clones`
   Deliverables: real shallow clone and fetch negotiation parity.
   Integration: add shallow remote parity coverage.
   Oracle: add local shallow transport scenarios and bind existing upstream
   shallow scenarios.

38. Grep sparse and submodule parity
   Rows: `Git grep`
   Deliverables: sparse-checkout and submodule-aware grep truthfulness.
   Integration: extend
   [`tests/Integration/GrepParityTest.php`](tests/Integration/GrepParityTest.php).
   Oracle: add local grep scenarios and bind
   [`scenarios/upstream/git-test-suite/t7817-grep-sparse-checkout/scenario.json`](scenarios/upstream/git-test-suite/t7817-grep-sparse-checkout/scenario.json)
   and [`scenarios/upstream/git-test-suite/t7814-grep-recurse-submodules/scenario.json`](scenarios/upstream/git-test-suite/t7814-grep-recurse-submodules/scenario.json).

39. Hooks parity
   Rows: `Hooks`
   Deliverables: env propagation, hook ordering, rejection paths, and push hook
   truthfulness.
   Integration: extend
   [`tests/Integration/HooksTest.php`](tests/Integration/HooksTest.php).
   Oracle: add local hook scenarios and bind the upstream hook scenarios.

40. Bisect parity
   Rows: `Bisect`
   Deliverables: Git-backed traversal, skip/good/bad state, and bisect reset
   truthfulness.
   Integration: extend
   [`tests/Integration/BisectTest.php`](tests/Integration/BisectTest.php).
   Oracle: import upstream bisect scenarios and add a local lifecycle
   scenario.

41. Fsmonitor decision
   Rows: `Fsmonitor`
   Deliverables: real Git/fsmonitor oracle coverage or an honest partial scope
   downgrade.
   Integration: extend
   [`tests/Integration/FsmonitorTest.php`](tests/Integration/FsmonitorTest.php)
   if implementation scope remains.
   Oracle: add a real fsmonitor oracle or leave the row partial.

42. SSH transport oracle path
   Rows: `SSH transport`
   Deliverables: repo-local Git-over-SSH fixture/server parity or a stable
   external-oracle label with matching public wording.
   Integration: add SSH parity coverage once the fixture exists.
   Oracle: repo-local SSH scenarios only; stock Git is not enough by itself.

43. Git LFS oracle path
   Rows: `Git LFS`
   Deliverables: decide whether Pitmaster will vendor a `git-lfs` parity
   harness. If yes, build it; if no, keep the row clearly external.
   Integration: add `git-lfs` integration coverage if the harness lands.
   Oracle: external to stock Git and requires `git-lfs`.

## Wave 5: Closure

44. Support-matrix closure pass
   Rows: all rows touched in Waves 1-4
   Deliverables: move every genuinely closed row to `Mapped`; immediately
   narrow any row that is still partial.

45. Compliance sweep expansion
   Rows: all newly closed rows
   Deliverables: ensure every new local scenario is part of recurring
   regression and compliance sweeps.

46. Docs and API claim sweep
   Rows: any row whose public docs still overstate support
   Deliverables: align README, docs, and support matrix with the post-wave
   reality.

47. Queue regeneration or deletion
   Rows: whatever remains after this queue
   Deliverables: delete this file only if every row is `Mapped`; otherwise
   regenerate it from the remaining non-`Mapped` rows so the next autonomous
   pass starts with a fresh execution order.
