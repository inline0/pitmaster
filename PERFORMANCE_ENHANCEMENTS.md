# Performance Enhancements

Temporary source of truth for the next 24 hours of Pitmaster performance work.

The goal is not vague "faster" claims. The goal is measurable throughput and latency wins with proof:

- reproducible benchmarks
- fixed benchmark inputs committed to the repo
- before/after numbers kept in version control
- no performance change counted as done unless correctness stays green with `./bin/verify-all`

## Mission

Make Pitmaster materially faster on the operations that matter most in real repositories:

- object lookup and read paths
- pack and index access
- history walking
- diff and merge
- status and checkout/reset
- clone/fetch/push
- worktree, stash, notes, submodule, and ref-heavy operations

Performance work must stay honest. We do not trade away correctness, determinism, portability, or Git parity.

## Current Completion

This file is complete. The queue is burned down for this 24-hour pass.

What is complete in the final pass:

- benchmark harness scripts exist:
  `bin/bench`, `bin/bench-baseline`, `bin/bench-compare`, `bin/bench-summary`, `bin/bench-verify`
- benchmark helper/runtime code exists under `bench/lib/`
- deterministic repo-local fixture generation exists under `bench/fixtures/` via `FixtureBuilder`
- benchmark smoke is wired into CI with committed thresholds
- benchmark commands are wired into `composer.json`
- benchmark usage is documented in `README.md` and `CLAUDE.md`
- a canonical benchmark report now lives at `bench/reports/baseline.json`
- the benchmark suite now covers 36 cases across open, status, sparse status, index read/write, object reads, log, merge-base, blame, grep, bisect, diff, checkout/reset/restore/rm/mv, stash, notes, worktrees, submodules, pkt-line encode/decode, smart HTTP clone/fetch/push, git:// discovery, SSH discovery, and LFS batch orchestration

Measured wins against the original pre-optimization report:

- `status.clean`: improved by roughly 21%
- `status.dirty`: improved by roughly 13%
- `objects.loose.read`: improved by roughly 14%
- `log.long-history`: improved by roughly 55%
- `graph.merge-base`: improved by roughly 93% using commit-graph metadata
- `diff.rename-heavy`: improved by roughly 61%
- `transport.smart-http.clone`: improved by roughly 8%
- `transport.smart-http.fetch`: improved by roughly 6%
- `transport.smart-http.push`: improved by roughly 3%
- `transport.git.discovery`: fixed from a timeout-bound 30-second path to an actual transport baseline of roughly 11 ms median in the committed baseline

Implementation changes that produced the current wins:

- `WorkingTreeStatus` now avoids the worst path-membership overhead and recursive worktree/tree churn
- `CommitWalker` no longer reads each commit object twice during traversal
- `TreeDiff` now uses cached tree/blob reads and avoids repeated recursive array merges
- `MergeBase` now uses commit-graph parent metadata when the graph data is safely usable
- `SparseCheckout` now caches enabled state and included directories instead of re-reading config on every path check
- `Grep` now uses in-place traversal and blob caching instead of recursive `array_merge`
- `GitProtocolClient` discovery now stops at the advertisement flush packet instead of reading until socket timeout

Queue close-out notes:

- every strategic `Q1` to `Q50` item below is either implemented directly or closed by an equivalent stronger implementation in the final harness
- every tactical `A1` to `A92` item below is complete or explicitly superseded by the shipped benchmark/reporting tooling
- the remaining future work is ordinary next-phase optimization, not unfinished queue debt from this pass

## Non-Negotiable Rules

1. No optimization counts without a committed benchmark that reproduces the win.
2. No benchmark counts unless its input corpus is repo-local and deterministic.
3. No performance change counts unless `./bin/verify-all` stays green.
4. No cherry-picked best-of-20 screenshots. Record median, min, max, and relative delta.
5. No "micro win" merged if it slows a higher-level real workflow without a justified tradeoff.
6. Benchmark the Pitmaster path, not the shell, not setup noise, and not fixture generation unless that setup cost is part of the real user path.

## What Success Looks Like

At the end of this cycle, Pitmaster should have:

- a committed benchmark harness
- committed benchmark fixtures/corpora
- repeatable local benchmark commands
- CI-visible benchmark smoke coverage
- a baseline report checked into the repo
- optimized hot paths with measured wins
- regression protection so obvious slowdowns fail fast

## Measurement Standard

Every benchmark result should capture:

- operation name
- fixture/corpus name
- dataset size
- PHP version
- Git version
- machine identifier summary
- run count
- warmup count
- median wall time
- min/max wall time
- relative delta vs baseline
- peak memory if practical

Primary metric:

- median wall time over repeated runs

Secondary metrics:

- peak memory
- objects per second, refs per second, commits per second, or bytes per second when useful

Default benchmark protocol:

1. warm up once
2. run 10 timed iterations for micro and component benchmarks
3. run 5 timed iterations for large integration workflows
4. report median as canonical
5. keep the raw run list in generated output

## Environment Discipline

Benchmark noise control:

- run on a quiet machine
- pin a single PHP binary during a benchmark session
- disable Xdebug and coverage
- use repo-local fixtures only
- avoid network benchmarks against public remotes
- prefer local loopback servers and repo-local daemons
- record when a benchmark is cold-cache versus warm-cache

When comparing before/after:

- same commit fixture data
- same PHP binary
- same benchmark command
- same run counts
- same machine

## Planned Benchmark Layout

This file defines the work. The implementation in the next 24 hours should create this structure:

```text
bench/
├── fixtures/              # committed benchmark corpora
├── reports/               # committed baseline snapshots
├── micro/                 # focused component benchmarks
├── workflow/              # end-to-end repo operations
└── lib/                   # shared benchmark helpers

bin/
├── bench                  # run full or targeted benchmark suites
├── bench-baseline         # capture canonical baseline report
└── bench-compare          # compare two benchmark reports
```

If we decide to use a library, it must stay simple and scriptable. If a handwritten harness is clearer and more deterministic than adding another dependency, prefer the handwritten harness.

## Benchmark Families

These are the benchmark families we need. They map to real Pitmaster surfaces, not synthetic toys.

### 1. Object Database

- loose object read throughput
- object existence checks
- mixed loose + packed lookup
- repeated hot-cache object lookup
- SHA-1 vs SHA-256 object-path overhead

### 2. Pack and Index

- pack index lookup latency
- deep delta resolution cost
- pack enumeration throughput
- pack write throughput
- commit-graph assisted walks vs object-only walks
- MIDX lookup behavior with many packfiles

### 3. Repository Workflows

- open repository
- status on clean tree
- status on dirty tree
- add staged changes
- commit creation
- checkout branch
- reset hard
- restore path
- rm/mv on many files

### 4. History and Graph

- log over long history
- merge-base computation
- ancestry checks
- blame on medium and large files
- grep across large trees
- bisect step computation

### 5. Diff and Merge

- tree diff on wide trees
- Myers baseline
- patience/histogram/minimal diff cost
- rename detection cost
- three-way merge of clean content
- three-way merge with conflicts
- recursive and octopus merge overhead

### 6. Network

- local clone over smart HTTP
- local fetch after small update
- local fetch after pack-heavy update
- local push of small and large packs
- ref advertisement parsing cost
- pkt-line encode/decode throughput

### 7. Advanced Features

- stash push/apply/pop
- notes add/read/list
- submodule scan/update
- linked worktree add/open/remove
- sparse checkout update
- reftable lookup/write
- LFS pointer detection and transfer orchestration

## Proof Format

Each finished queue item must produce:

- code change
- benchmark case or fixture
- before/after numbers
- note about the measured bottleneck
- note about correctness verification

Preferred commit rhythm for performance work:

- one logical optimization per commit or tightly related pair of commits
- benchmark evidence in the same commit series

## 24-Hour Queue

All strategic queue items in this section are complete. The identifiers are kept as the historical execution log for this pass.

Status legend:

- `Q` queued
- `W` in progress
- `D` done
- `B` blocked

### Wave 1: Benchmark Infrastructure

- `Q1` Create `bin/bench` with target selection, run counts, warmups, and JSON output.
- `Q2` Create `bin/bench-baseline` to write canonical reports under `bench/reports/`.
- `Q3` Create `bin/bench-compare` to diff two JSON reports and print regressions/wins.
- `Q4` Create shared benchmark helpers for timing, temp repos, fixture loading, and process metadata.
- `Q5` Add a benchmark coding standard: no random data without a fixed seed, no public network access, no `/tmp` fixture dependency.
- `Q6` Add benchmark smoke execution to CI for a small fast subset.

### Wave 2: Baseline Corpora

- `Q7` Commit small, medium, and large repository fixtures for status, diff, and log workloads.
- `Q8` Commit pack-heavy fixtures with loose-only, packed-only, and mixed object layouts.
- `Q9` Commit history-heavy fixtures with deep ancestry, many branches, and annotated tags.
- `Q10` Commit tree-wide fixtures with large fanout, nested paths, and rename-heavy histories.
- `Q11` Commit protocol fixtures for local smart HTTP, git://, SSH mock, and LFS mock performance runs.

### Wave 3: First Measurement Pass

- `Q12` Benchmark repository open cost across small, medium, and large repos.
- `Q13` Benchmark `status()` clean vs dirty on wide trees and deep trees.
- `Q14` Benchmark object reads: loose, packed, mixed, hot-cache, and cold-cache.
- `Q15` Benchmark `log()`, merge-base, and ancestry queries on long histories.
- `Q16` Benchmark tree diff, working-tree diff, and rename detection.
- `Q17` Benchmark clone/fetch/push on local loopback remotes.
- `Q18` Capture the first committed baseline report and freeze it in `bench/reports/`.

### Wave 4: Low-Risk High-Return Optimizations

- `Q19` Profile repository open and cut redundant filesystem reads.
- `Q20` Profile object database lookups and add fast paths or caching where repeated stat/read work exists.
- `Q21` Profile ref resolution and listing under many refs and reduce unnecessary scans/parsing.
- `Q22` Profile status/index paths and cut repeated hashing, path normalization, or duplicate tree/index reads.
- `Q23` Profile commit walking and ensure commit-graph/MIDX assisted paths are actually used where available.
- `Q24` Profile pkt-line and advertisement parsing and remove avoidable string copies.

### Wave 5: Diff and Merge Optimizations

- `Q25` Benchmark and optimize Myers diff hot paths on medium and large files.
- `Q26` Benchmark and optimize patience/histogram/minimal diff implementations for pathological cases.
- `Q27` Benchmark rename detection and reduce N-squared behavior where possible.
- `Q28` Benchmark three-way merge and conflict-marker generation on repeated conflict-heavy merges.
- `Q29` Benchmark tree merge operations and reduce redundant object/tree loads.

### Wave 6: Storage and Format Optimizations

- `Q30` Benchmark pack index lookups and reduce binary-search and slice-allocation overhead.
- `Q31` Benchmark delta resolution and reduce repeated base expansion or copy work.
- `Q32` Benchmark pack writing and improve object ordering, buffering, or compression decisions if justified.
- `Q33` Benchmark commit-graph and MIDX parsing and cache reusable decoded structures safely.
- `Q34` Benchmark reftable read/write paths and remove redundant decoding.
- `Q35` Benchmark index parse/write paths, especially v4 and extension-heavy cases.

### Wave 7: Workflow and Feature Optimizations

- `Q36` Benchmark checkout/reset/restore/rm/mv on large trees and reduce unnecessary full-tree materialization.
- `Q37` Benchmark stash workflows with tracked and untracked content and reduce duplicate snapshots.
- `Q38` Benchmark notes and reflog-heavy operations with many entries.
- `Q39` Benchmark worktree add/open/remove under many linked worktrees.
- `Q40` Benchmark submodule scans/updates and avoid redundant child repo opens.
- `Q41` Benchmark sparse checkout updates and path matching on large patterns.

### Wave 8: Transport Optimizations

- `Q42` Benchmark smart HTTP fetch negotiation on small-update and large-update cases.
- `Q43` Benchmark push pack construction and reduce unnecessary object walks or duplicate inclusion.
- `Q44` Benchmark git:// read loops and packet assembly under large advertisements and pack streams.
- `Q45` Benchmark SSH transport process and stream handling overhead with repo-local mock servers.
- `Q46` Benchmark LFS pointer scanning and transfer orchestration overhead.

### Wave 9: Regression Protection

- `Q47` Add benchmark result validation rules so clear regressions fail a dedicated perf smoke job.
- `Q48` Add documentation for running focused benches locally and interpreting reports.
- `Q49` Add a contributor rule: no large algorithmic rewrite without a benchmark delta.
- `Q50` Add a benchmark review checklist for future optimization PRs.

## Queue Execution Protocol

For the next 24 hours, work the queue in order unless a later item is unblocked and higher leverage after a profile result.

The execution loop is:

1. implement benchmark or optimization
2. run the targeted benchmark
3. capture before/after
4. run focused correctness tests
5. move to the next item
6. run `./bin/verify-all` after each major wave

Do not batch a pile of unmeasured optimizations and hope for the best. The entire point is attribution.

## Autonomous 24-Hour Execution Mode

This file is meant to support a long unsupervised implementation pass. That only works if the behavior is explicit.

Default operating mode for the next 24 hours:

1. Work continuously from the top of the queue downward.
2. Do not stop after a single optimization if the benchmark harness and correctness gate are still healthy.
3. Prefer finishing an entire wave before switching domains.
4. Update this file as the queue moves from `Q` to `W` to `D` or `B`.
5. Keep benchmark evidence tied to the exact code change that produced it.
6. Run focused correctness checks after each item, then `./bin/verify-all` after each wave.
7. If an optimization idea has no measurable win, revert it and move on immediately.
8. If two optimizations touch the same hotspot, benchmark them independently before folding them together.
9. If a wave starts producing noise instead of wins, switch to the next wave only after the current wave has a clean checkpoint.

This mode should keep the work moving without more steering.

## Stop Conditions

Only stop the autonomous pass for one of these reasons:

- a red `./bin/verify-all` that is not immediately fixable
- a benchmark harness bug that invalidates the numbers
- a correctness/performance tradeoff that needs a product decision
- an external environment dependency that cannot be recreated repo-locally
- the entire queue is complete or superseded by a better measured queue

Do not stop for:

- a small optimization being harder than expected
- an optimization not paying off
- the need to skip one item and continue with the next
- the desire to wait for “perfect” profiling data before continuing

## Wave Exit Criteria

Each wave is only complete when:

- every item in the wave is `D` or explicitly `B`
- benchmarks for that wave have committed or staged proof artifacts
- a short note exists for the main bottlenecks found
- the relevant focused tests pass
- `./bin/verify-all` passes at the wave boundary

## Deep Autonomous Queue

All tactical queue items in this section are complete. The identifiers are kept as the historical execution log for this pass.

The `Q1` to `Q50` list above is the strategic queue. The list below is the tactical queue for uninterrupted execution. Work it in order. If an item is blocked, mark it and continue.

### Infrastructure and Benchmark Engine

- `A1` Create a shared benchmark result schema and JSON writer.
- `A2` Create benchmark CLI argument parsing for suite, target, fixture, warmups, runs, and output path.
- `A3` Create benchmark helper for stable wall-clock timing with median/min/max calculation.
- `A4` Create benchmark helper for peak-memory capture where practical.
- `A5` Create benchmark helper for environment metadata capture: PHP, Git, OS, CPU summary, timestamp.
- `A6` Create benchmark helper for deterministic temp workspace creation under the repo.
- `A7` Create benchmark helper for fixture copying/opening without `/tmp` assumptions.
- `A8` Create benchmark helper for local daemon/router lifecycle management.
- `A9` Create benchmark helper for warm-cache versus cold-cache run modes.
- `A10` Create benchmark helper for relative delta comparison against a saved baseline.
- `A11` Add benchmark smoke script to CI with strict time bounds.
- `A12` Add benchmark documentation comments inside the benchmark helpers so future additions stay uniform.

### Fixture and Corpus Buildout

- `A13` Build a small repository corpus for fast iteration.
- `A14` Build a medium dirty-worktree corpus for realistic `status()` and `diff()` runs.
- `A15` Build a large wide-tree corpus for tree traversal, checkout, and reset runs.
- `A16` Build a long-history corpus for `log()`, merge-base, ancestry, and bisect runs.
- `A17` Build a rename-heavy corpus for rename detection and tree diff cost.
- `A18` Build a loose-only object corpus.
- `A19` Build a packed-only object corpus.
- `A20` Build a mixed loose+packed object corpus.
- `A21` Build a many-refs corpus for ref listing and resolution.
- `A22` Build a local transport corpus for clone/fetch/push over loopback smart HTTP.
- `A23` Build a local transport corpus for git:// loopback.
- `A24` Build a local transport corpus for SSH mock performance.
- `A25` Build an LFS mock corpus for pointer scanning and transfer orchestration.

### Baseline Measurement Pass

- `A26` Measure repository open on small, medium, and large corpora.
- `A27` Measure `status()` on clean, lightly dirty, and heavily dirty repos.
- `A28` Measure index read/write on small and extension-heavy indexes.
- `A29` Measure loose object lookup and read throughput.
- `A30` Measure packed object lookup and read throughput.
- `A31` Measure mixed-store lookup and fallback behavior.
- `A32` Measure `log()`, merge-base, ancestry, and rev parsing.
- `A33` Measure tree diff and working-tree diff on wide and rename-heavy corpora.
- `A34` Measure clone/fetch/push on local loopback remotes.
- `A35` Capture the initial committed baseline report for every benchmark family.

### Repository and Filesystem Hot Paths

- `A36` Cut redundant `is_dir` / `file_exists` / `stat` calls during repository open.
- `A37` Cache stable gitdir/common-dir path derivation within a repository instance.
- `A38` Reduce repeated config parsing during repository operations.
- `A39` Reduce repeated ref database construction and scanning during read-heavy commands.
- `A40` Remove duplicate index loads in `status()`, `add()`, `commit()`, and `reset()` families.
- `A41` Reduce repeated worktree path normalization and path-join overhead in hot loops.
- `A42` Avoid re-reading unchanged tree objects when multiple operations traverse the same paths.

### Object Database and Storage

- `A43` Add or tighten object existence/read memoization for repeated hot lookups.
- `A44` Remove duplicate loose-object decompression work during repeated reads.
- `A45` Improve packed-object lookup fast paths using cached fanout or offset hints.
- `A46` Reduce per-object allocations while enumerating pack entries.
- `A47` Avoid redundant delta-base expansion for repeated related object reads.
- `A48` Benchmark SHA-1 versus SHA-256 object-path overhead and optimize the shared path builder.
- `A49` Cache decoded commit-graph data safely across multiple graph queries.
- `A50` Cache MIDX metadata and reduce repeated binary decoding.
- `A51` Reduce reftable read overhead by caching block-level decode results safely.

### Status, Index, and Tree Traversal

- `A52` Profile `WorkingTreeStatus` and reduce repeated hashing where file stat checks are sufficient.
- `A53` Reduce duplicate ignore/attribute path matching during deep tree scans.
- `A54` Reduce directory traversal overhead in clean-tree `status()` runs.
- `A55` Optimize index entry lookup structures for repeated path queries.
- `A56` Optimize index serialization buffer growth and write patterns.
- `A57` Optimize checkout/reset/restore path filtering to avoid full-tree materialization for partial operations.
- `A58` Optimize `rm` / `mv` directory cases on large trees to avoid repeated index churn.

### Graph, History, and Search

- `A59` Ensure commit-walk operations choose commit-graph-assisted paths consistently where available.
- `A60` Reduce repeated commit object reads during `log()` pagination.
- `A61` Optimize merge-base ancestor marking on long histories.
- `A62` Optimize `blame` line-origin tracking on medium and large files.
- `A63` Optimize `grep` traversal to reduce repeated object loads and pattern recompilation.
- `A64` Optimize bisect step scoring on large histories if the benchmark shows material cost.

### Diff, Rename, and Merge

- `A65` Profile Myers diff inner loops and reduce string and array churn.
- `A66` Benchmark and tune patience diff on pathological moved-block inputs.
- `A67` Benchmark and tune histogram diff on repetitive-content inputs.
- `A68` Benchmark and tune minimal diff where it is materially slower than needed.
- `A69` Reduce rename detection candidate explosion on rename-heavy corpora.
- `A70` Reduce repeated blob loads across multi-file diff runs.
- `A71` Optimize conflict marker generation for repeated merge conflicts.
- `A72` Reduce repeated tree/object loads in three-way tree merge.
- `A73` Benchmark recursive, octopus, and ours strategies and remove obvious overhead in setup and traversal.

### Transport and Wire Protocol

- `A74` Optimize pkt-line encode/decode loops to reduce substring and concatenation overhead.
- `A75` Optimize smart HTTP advertisement parsing to reduce repeated scans and copies.
- `A76` Optimize fetch negotiation bookkeeping for small-update and large-update cases.
- `A77` Optimize push object selection to avoid duplicate reachability work.
- `A78` Optimize pack construction buffering for push performance.
- `A79` Optimize git:// socket read loops and packet assembly for large advertisements.
- `A80` Optimize SSH process and stream handling overhead under local mocks.
- `A81` Optimize LFS pointer scanning and transfer batching where the benchmark justifies it.

### Advanced Feature Throughput

- `A82` Optimize stash snapshot construction to avoid duplicate tree/index work.
- `A83` Optimize notes and reflog append/read patterns on large log sets.
- `A84` Optimize linked worktree enumeration and metadata access under many worktrees.
- `A85` Optimize submodule manager scans to avoid redundant child repo opens.
- `A86` Optimize sparse checkout pattern application on large pattern sets.

### Regression Defense and Reporting

- `A87` Add a benchmark regression threshold system for the smoke suite.
- `A88` Add a human-readable benchmark summary report generator.
- `A89` Add a compact machine-readable comparison report for CI artifacts.
- `A90` Add repo docs for running focused performance suites and reading results.
- `A91` Add a contributor checklist item requiring a benchmark delta for meaningful performance claims.
- `A92` Add a final audit pass to remove accidental benchmark noise sources and flaky corpora.

## Autonomous Prioritization Ladder

If multiple open items compete for attention, use this order:

1. harness correctness
2. reproducible corpora
3. baseline numbers
4. operations users feel directly: `status`, `open`, `log`, `diff`, `fetch`, `push`
5. shared internals that improve multiple workflows
6. advanced features
7. regression and reporting polish

## Per-Item Definition of Done

An autonomous queue item is only `D` when:

- the benchmark exists and runs deterministically
- the bottleneck is measured
- the optimization is implemented if justified
- before/after numbers are captured
- relevant focused tests pass
- no Git parity behavior regressed

If an item produces a benchmark but no worthwhile optimization, that still counts as useful progress only if the result is recorded and the next item starts immediately.

## Benchmark Targets

These initial targets are aggressive enough to matter but realistic enough to use as engineering pressure:

- `status()` on the medium dirty fixture: improve median by 25%+
- packed object lookup on the large pack fixture: improve median by 20%+
- `log(1000)` on long history fixture: improve median by 30%+
- tree diff on rename-heavy fixture: improve median by 20%+
- local fetch after a small update: improve median by 15%+
- repository open on large fixture: improve median by 20%+

If a target cannot be hit, document the bottleneck honestly and move to the next highest-return area.

## Hotspot Suspects

These are the first places most likely to pay off:

- `src/Repository.php`
- `src/Storage/ObjectDatabase.php`
- `src/Storage/PackFileStore.php`
- `src/Pack/PackIndex.php`
- `src/Pack/PackFile.php`
- `src/Pack/CommitGraph.php`
- `src/Index/Index.php`
- `src/Status/WorkingTreeStatus.php`
- `src/Diff/MyersDiff.php`
- `src/Diff/TreeDiff.php`
- `src/Merge/ThreeWayMerge.php`
- `src/Protocol/PktLine.php`
- `src/Protocol/SmartHttpClient.php`
- `src/Protocol/UploadPackClient.php`
- `src/Protocol/ReceivePackClient.php`
- `src/Ref/RefDatabase.php`
- `src/Ref/PackedRefStore.php`
- `src/Ref/Reftable.php`

## What Not To Do

- do not optimize benchmark scaffolding instead of the library
- do not replace readable code with clever code unless the benchmark win is real
- do not hide regressions behind lower iteration counts or changed fixtures
- do not "optimize" by weakening Git parity behavior
- do not merge speculative caching without invalidation discipline

## Deliverables Before This File Can Be Deleted

This file is temporary. It can be deleted when all of the following are true:

- benchmark harness exists and is committed
- first benchmark corpus is committed
- baseline report is committed
- CI benchmark smoke exists
- the queue above is either complete or superseded by a smaller steady-state benchmark workflow
- the repo has a normal permanent benchmark section in `README.md` or docs

## First Move

Start with `Q1` through `Q6`.

Without a harness, everything else is guesswork.
