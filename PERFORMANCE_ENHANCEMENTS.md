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

Phase 1 and Phase 2 are complete.

The original 24-hour queue was burned down and the first performance program delivered its baseline, harness, CI smoke, and the first large wave of real wins.

This file remains as the execution log and measurement record for both passes.

Current top baseline hotspots on `main`:

- `transport.ssh.discovery`: `284.503ms`
- `transport.smart-http.clone`: `79.889ms`
- `transport.smart-http.push`: `65.805ms`
- `workflow.checkout.large`: `52.768ms`
- `workflow.reset.hard.large`: `44.033ms`
- `transport.smart-http.fetch`: `40.629ms`
- `workflow.stash.untracked`: `37.573ms`
- `graph.blame.medium`: `35.563ms`
- `graph.grep.large`: `31.463ms`
- `workflow.stash.tracked`: `30.620ms`
- `workflow.submodule.update`: `21.551ms`
- `status.sparse.large`: `16.622ms`

Phase 2 closeout:

- the full `P1` to `P36` queue was worked through as a measurement-first pass
- candidate optimizations for checkout/reset tree flattening caches, sparse include caching, smart HTTP advertisement parsing, transport push selection, stash shortcutting, notes/reflog caching, submodule shortcutting, blame trimming, and grep shortcutting were benchmarked and discarded when they did not beat `HEAD` cleanly enough to justify complexity
- the final direct A/B check against a detached `HEAD` worktree on the same machine showed the last retained checkout/sparse candidates were not real wins:
  `workflow.checkout.large 49.952ms -> 49.755ms`, `workflow.reset.hard.large 48.088ms -> 49.609ms`, `status.sparse.large 12.090ms -> 12.893ms`
- the only retained performance-process change from the closeout pass is workflow guidance:
  `README.md` now documents focused hotspot reruns as the default optimization workflow before refreshing the canonical baseline
- while profiling transport parsing, the pass also fixed SHA-256 ref advertisement parsing in `RefDiscovery`; that is a correctness compatibility fix, not a claimed performance win
- `P36` is now satisfied honestly: the remaining top costs are dominated by transport process startup, protocol round trips, filesystem work, or ordinary workstation benchmark noise rather than obvious low-risk local hot spots

What is complete in the final pass:

- benchmark harness scripts exist:
  `bin/bench`, `bin/bench-baseline`, `bin/bench-compare`, `bin/bench-summary`, `bin/bench-verify`
- benchmark helper/runtime code exists under `bench/lib/`
- deterministic repo-local fixture generation exists under `bench/fixtures/` via `FixtureBuilder`
- benchmark smoke is wired into CI with committed thresholds
- benchmark commands are wired into `composer.json`
- benchmark usage is documented in `README.md` and `CLAUDE.md`
- a canonical benchmark report now lives at `bench/reports/baseline.json`
- the benchmark suite now covers 39 cases across open, status, sparse status, index read/write, object reads, log, merge-base, blame, grep, bisect, diff, checkout/reset/restore/rm/mv, stash, notes, reflog reads, worktree list/remove, submodule status/update, pkt-line encode/decode, smart HTTP clone/fetch/push, git:// discovery, SSH discovery, and LFS batch orchestration

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

Measured wins in the follow-up pass against the committed `baseline.json`:

- `graph.blame.medium`: improved by roughly 92%
- `graph.bisect.long-history`: improved by roughly 97%
- `workflow.checkout.large`: improved by roughly 9%
- `workflow.reset.hard.large`: improved by roughly 9%
- `workflow.stash.tracked`: improved by roughly 29%
- `workflow.stash.untracked`: improved by roughly 43%

Follow-up pass notes:

- the graph wins came from caching file-content/line reuse in `Blame` and replacing repeated ancestry checks in `Bisect` with a single known-good reachability pass
- the checkout/reset wins came from reusing matching index entries and avoiding full-tree rewrites when only a subset of paths actually changed
- the stash wins came from carrying dirtiness information through a single stash push cycle instead of rescanning the working tree for both stash-tree creation and reset cleanup
- `transport.ssh.discovery` was remeasured in this pass but did not show a stable improvement, so no benchmark-only SSH tweak was shipped

Measured wins in the latest follow-up pass against isolated before/after reruns:

- `graph.grep.large`: `102.877ms -> 36.907ms` (`-64.13%`)
- `workflow.rm.directory.large`: `38.210ms -> 11.678ms` (`-69.44%`)
- `workflow.mv.directory.large`: `39.807ms -> 12.210ms` (`-69.33%`)

Latest follow-up pass notes:

- `Grep` now has a literal-search fast path, uses a full-blob prefilter before line scanning, and avoids regex-per-line work for the common plain-text case
- directory-heavy `rm` and `mv` now batch index mutations instead of repeatedly filtering and sorting the full index for every file in the subtree
- `Index` now exposes bulk add/remove operations so higher-level workflows can amortize index churn into a single pass
- `bin/bench-baseline` now emits live case progress and a final slowest-case summary, which makes the full baseline refresh observable instead of looking hung on long runs
- the canonical `bench/reports/baseline.json` has been refreshed in this pass; isolated before/after reruns should still be treated as the cleaner proof for individual optimization claims because the full-suite baseline remains noisier on a live workstation

Measured wins in the newest follow-up pass against isolated before/after reruns:

- `workflow.checkout.large`: `675.613ms -> 376.342ms` (`-44.30%`)
- `workflow.reset.hard.large`: `626.010ms -> 123.331ms` (`-80.30%`)
- `workflow.stash.tracked`: `101.952ms -> 49.638ms` (`-51.31%`)
- `workflow.stash.untracked`: `53.174ms -> 49.682ms` (`-6.57%`) on the rerun taken after fixing benchmark workspace collisions

Secondary transport movement measured against the current canonical baseline:

- `transport.smart-http.clone`: `188.212ms -> 76.914ms` (`-59.13%`)
- `transport.smart-http.fetch`: `85.617ms -> 48.138ms` (`-43.78%`)
- `transport.smart-http.push`: `136.432ms -> 121.900ms` (`-10.65%`)

Newest follow-up pass notes:

- `resetWorktree()` now builds replacement indexes in memory and writes them once, instead of repeatedly re-sorting a growing index during large checkout/reset operations
- `Stash::resetToHead()` now uses the same bulk index assembly pattern, which is where the tracked stash win came from
- the smart HTTP improvements in this pass were indirect: clone/fetch/push benefit from the faster checkout/reset machinery used at the end of transport workflows
- `BenchmarkRuntime::freshWorkspace()` now uses a high-entropy suffix instead of `time()` plus a per-process counter, which fixed workspace collisions when rerunning the same benchmark case quickly

Measured wins in the current pass against recent isolated reruns:

- `workflow.checkout.large`: `230.691ms -> 159.053ms` (`-31.05%`)

Current pass notes:

- `resetWorktree()` no longer asks the full `status()` pipeline for dirty paths just to decide index-entry reuse; it now checks only the current indexed paths directly against the worktree, which is where the new checkout win came from
- `workflow.reset.hard.large` stayed effectively flat in this pass (`78.122ms -> 78.472ms`), so the retained value is the checkout win rather than a broad "reset got faster again" claim
- the transport benchmark cases now clean up their per-iteration bare remotes correctly, which means warmups no longer poison `transport.smart-http.clone`, `fetch`, or `push`
- an attempted smart HTTP code-path optimization batch was measured and reverted because isolated transport reruns regressed; the benchmark harness fixes were kept, the transport code changes were not

Measured wins in the current canonical-baseline refresh against the previous committed baseline:

- `status.clean`: `16.416ms -> 4.576ms` (`-72.12%`)
- `status.dirty`: `12.382ms -> 4.127ms` (`-66.67%`)
- `status.sparse.large`: `19.997ms -> 16.622ms` (`-16.88%`)
- `workflow.checkout.large`: `171.266ms -> 52.768ms` (`-69.19%`)
- `workflow.reset.hard.large`: `115.475ms -> 44.033ms` (`-61.87%`)
- `workflow.stash.tracked`: `81.849ms -> 30.620ms` (`-62.59%`)
- `workflow.stash.untracked`: `64.004ms -> 37.573ms` (`-41.30%`)
- `workflow.notes.list-heavy`: `3.657ms -> 2.343ms` (`-35.93%`)
- `workflow.worktree.list-many`: `2.902ms -> 1.426ms` (`-50.86%`)
- `transport.smart-http.clone`: `97.764ms -> 79.889ms` (`-18.28%`)
- `transport.smart-http.fetch`: `72.647ms -> 40.629ms` (`-44.07%`)
- `transport.smart-http.push`: `140.466ms -> 65.805ms` (`-53.15%`)
- `transport.ssh.discovery`: `458.025ms -> 284.503ms` (`-37.88%`)

Current canonical pass notes:

- `WorkingTreeStatus` now uses a stat-validated fast path before falling back to blob hashing, which is the main reason the clean/dirty status benchmarks collapsed into single-digit millisecond territory
- `assertSafeCheckout()` now checks only the paths that would actually change and compares those directly against index/worktree state instead of routing through the full status pipeline
- `SparseCheckout` now caches include decisions and walks ancestor segments instead of scanning every configured include directory for every path lookup
- `Stash` now reuses clean index entries for staged-only paths, skips the second ignore pass for already-classified untracked files, and uses in-place worktree scanning
- `WorktreeManager` now reuses shared ref state while listing linked worktrees and resolves worktree paths directly from metadata files instead of rebuilding worktree objects just to match paths
- `SubmoduleManager` now caches `.gitmodules`, resolves submodule HEADs directly, and avoids repository opens in the status path
- `Notes` now flattens note trees into a single accumulator and caches note maps per notes ref; `Reflog` now uses line-based reads instead of full-file splitting
- `SshClient` now uses direct argv-based `proc_open()` when the configured SSH command is simple, which removed an avoidable shell hop from the common benchmark path
- the benchmark suite now has first-class proof cases for reflog reads, linked-worktree removal, and submodule update, so those follow-up optimizations are no longer inferred from adjacent workflows

Measured wins in the current phase-3 follow-up pass against isolated before/after reruns:

- `workflow.stash.tracked`: `42.638ms -> 40.205ms` (`-5.70%`)
- `transport.smart-http.push`: `77.283ms -> 70.213ms` (`-9.15%`)

Current phase-3 follow-up notes:

- `Stash::push()` now carries the exact set of included untracked paths into `resetToHead()` instead of rescanning the whole worktree to prune everything not tracked by `HEAD`
- that stash change is kept primarily because it fixes an actual semantics bug as well as reducing tracked-stash cleanup work: tracked-only stashes no longer delete unrelated untracked files
- `Repository::buildPushPackDataForUpdates()` now has a guarded fast path for ordinary fast-forward-style updates; it walks from the new tips and stops at the old advertised tips instead of first traversing the old graph just to subtract it again
- smart HTTP clone/fetch and SSH discovery were remeasured in this pass but did not justify additional retained code changes beyond the push fast path, so they stay as-is until a cleaner proof shows up

## Phase 2 Mission

Phase 2 is complete.

The objective was to squeeze the remaining meaningful latency out of transport, checkout/reset, stash, sparse status, graph/search, and the auxiliary workflows without sacrificing proof discipline. The queue below is kept as the record of that pass, but it is no longer active.

## Phase 2 Queue

This queue is complete. It remains here as the historical checklist for the pass that closed the second optimization wave. Tasks were either implemented in earlier Phase 2 slices or explicitly closed as measured no-ships in the final pass.

### Wave A: Transport Hotspots

- `P1` Split `transport.ssh.discovery` into process-launch, handshake, and parse costs so the remaining latency is attributable instead of guessed.
- `P2` Reduce SSH command construction overhead further, including escaping/argv decisions and repeated environment setup.
- `P3` Reduce repeated SSH process startup work across discovery-style operations if it can be done without changing protocol truthfulness.
- `P4` Reprofile smart HTTP clone/fetch/push separately after the current baseline refresh so transport fixes are proven against current code, not stale measurements.
- `P5` Cut repeated smart HTTP advertisement parsing, capability scanning, and packet decoding work across clone/fetch/push.
- `P6` Reduce fetch negotiation bookkeeping overhead in small-update and large-update cases without regressing correctness.
- `P7` Reduce push object-selection cost further by tightening reachability exclusion and duplicate object filtering.
- `P8` Reduce push pack construction buffering and copy overhead in the hot path.
- `P9` Revisit `transport.git.discovery` even though it is no longer dominant, to make sure no easy low-risk win is left behind.

### Wave B: Checkout, Reset, and Worktree Paths

- `P10` Reprofile `workflow.checkout.large` and `workflow.reset.hard.large` independently so remaining cost splits cleanly between safety checks, tree flattening, blob reads, file writes, and index writes.
- `P11` Reduce `assertSafeCheckout()` further if it still touches unchanged paths or repeated tree/index state.
- `P12` Reduce reset/checkout tree flattening and path-map construction overhead for large repos.
- `P13` Reduce blob materialization during checkout/reset when file bytes are not needed to decide reuse or skip.
- `P14` Reduce file-write churn for unchanged outputs during large checkout/reset runs.
- `P15` Revisit sparse checkout pattern matching and traversal on large trees and large pattern sets.
- `P16` Revisit linked-worktree enumeration/open/remove under many worktrees and many refs.

### Wave C: Stash, Notes, Reflog, and Submodule Throughput

- `P17` Reprofile `workflow.stash.tracked` and `workflow.stash.untracked` separately so the remaining cost is clearly split across status, tree build, ignore handling, and reset cleanup.
- `P18` Reduce stash untracked snapshot cost further, especially directory scanning and blob creation overhead.
- `P19` Reduce stash tracked snapshot cost further where staged-only paths can be reused more aggressively.
- `P20` Reduce `workflow.submodule.update` overhead by avoiding redundant child repo opens, config reads, and ref resolution.
- `P21` Revisit submodule status/update path normalization and gitdir resolution for repeated operations.
- `P22` Reduce notes listing overhead further on large note sets.
- `P23` Reduce reflog read and append overhead further on large histories and heavy ref churn.

### Wave D: Status, Sparse, Graph, and Search

- `P24` Reprofile `status.clean`, `status.dirty`, and `status.sparse.large` after the current stat-based fast path so the remaining cost is attributed honestly.
- `P25` Reduce repeated ignore and attribute checks during large worktree scans.
- `P26` Reduce sparse-status traversal cost when directories are wholly excluded.
- `P27` Revisit index lookup structures for repeated path queries in status, checkout, reset, and stash.
- `P28` Reprofile `graph.blame.medium` and push it lower only if the remaining cost is stable across reruns.
- `P29` Revisit `graph.grep.large` for any remaining literal-search or blob-read overhead.
- `P30` Revisit `graph.bisect.long-history` and `log.long-history` only if the refreshed baseline still shows worthwhile headroom.

### Wave E: Baseline, Thresholds, and Public Benchmarking

- `P31` Refresh the canonical baseline after every wave that keeps more than one benchmark-affecting optimization.
- `P32` Compare the new baseline against the last committed baseline and record only medians that hold up across reruns.
- `P33` Refresh benchmark smoke thresholds conservatively so CI catches regressions without turning noisy workloads into false positives.
- `P34` Add or refine public benchmark docs so contributors know how to run focused cases, compare reports, and interpret smoke failures.
- `P35` Tighten the internal performance notes in this file so Phase 2 remains an execution log rather than turning into stale prose.
- `P36` End Phase 2 only when the remaining top costs are either protocol-startup dominated, filesystem dominated, or too small/noisy to justify more complexity.

## Phase 2 Execution Rules

Phase 2 kept the original discipline:

1. work in waves, not isolated micro-tweaks
2. keep only measured wins
3. refresh the canonical baseline after meaningful retained changes
4. rerun focused PHPUnit for touched surfaces before the full gate
5. rerun `./bin/verify-all` before ending a wave
6. record failed optimization attempts here if they were measured and reverted

The final closeout pass followed the same rule set and ended by reverting any candidate optimization that failed a same-machine A/B check against `HEAD`.

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
