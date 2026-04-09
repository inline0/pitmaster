# Pitmaster Support Matrix

Git feature coverage for Pitmaster. **93/146 rows Git-oracle-mapped today.**

Generated: 2026-04-09 00:58

## Legend

| Status | Meaning |
|--------|---------|
| `DONE` | Implemented and Git-oracle-mapped |
| `PART` | Implemented or partially implemented, but not yet fully Git-oracle-backed or dependent on an external oracle |
| `TODO` | In scope, not yet implemented |
| `DEFER` | Post-v1, not yet planned |
| `N/A` | Out of scope (violates pure-PHP or outside agent-IDE scope) |

## Summary

| Category | Done | Partial | Todo | Deferred | N/A | Total |
|----------|------|---------|------|----------|-----|-------|
| Object Model | 10 | 1 | 0 | 0 | 0 | 11 |
| Object Storage | 4 | 0 | 0 | 0 | 0 | 4 |
| Pack Files | 9 | 2 | 0 | 0 | 0 | 11 |
| Index (Staging Area) | 7 | 0 | 0 | 0 | 0 | 7 |
| References | 7 | 2 | 0 | 0 | 0 | 9 |
| Repository Operations | 2 | 4 | 0 | 0 | 0 | 6 |
| Staging and Commits | 4 | 8 | 0 | 0 | 0 | 12 |
| Working Tree Status | 5 | 1 | 0 | 0 | 0 | 6 |
| Diff | 5 | 6 | 0 | 0 | 0 | 11 |
| Merge | 2 | 8 | 0 | 0 | 0 | 10 |
| Commit Graph | 2 | 5 | 0 | 0 | 0 | 7 |
| Branch and Tag Operations | 7 | 2 | 0 | 0 | 0 | 9 |
| Network Protocol | 12 | 2 | 0 | 0 | 0 | 14 |
| Encoding | 0 | 5 | 0 | 0 | 0 | 5 |
| Error Handling | 9 | 0 | 0 | 0 | 0 | 9 |
| Advanced Features | 8 | 7 | 0 | 0 | 0 | 15 |
| **Total** | **93** | **53** | **0** | **0** | **0** | **146** |

## Details

### Object Model
*10/11 Git-oracle-mapped, 1 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Blob read | `DONE` | `Object\Blob` |  |
| Blob write | `DONE` | `Object\Blob` |  |
| Tree read | `DONE` | `Object\Tree` |  |
| Tree write | `DONE` | `Object\Tree` | Needed for commit() |
| Commit read | `DONE` | `Object\Commit` |  |
| Commit write | `DONE` | `Object\Commit` | Needed for commit() |
| Annotated tag read | `DONE` | `Object\Tag` |  |
| Annotated tag write | `DONE` | `Object\Tag` |  |
| Lightweight tag | `DONE` | `Ref\RefDatabase` | Just a ref pointing to a commit |
| ObjectId SHA-1 | `DONE` | `Object\ObjectId` | 40-char hex, 20-byte binary |
| ObjectId SHA-256 | `PART` | `Object\ObjectId` | Abstractable from day one, impl post-v1 |

### Object Storage
*4/4 Git-oracle-mapped*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Loose object read | `DONE` | `Storage\LooseObjectStore` | zlib decompress + header parse |
| Loose object write | `DONE` | `Storage\LooseObjectStore` | Atomic write via temp+rename |
| Object serialization | `DONE` | `Storage\ObjectSerializer` | type size\0content format |
| Object database (composite) | `DONE` | `Storage\ObjectDatabase` | Loose first, then packs |

### Pack Files
*9/11 Git-oracle-mapped, 2 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Pack file read | `DONE` | `Pack\PackFile` | PACK v2 format |
| Pack file write | `DONE` |  | Let git gc repack; write loose instead |
| Pack index v2 read | `DONE` | `Pack\PackIndex` | Fanout + binary search |
| Pack index v1 read | `DONE` |  | v2 covers all modern repos |
| OFS_DELTA resolution | `DONE` | `Pack\DeltaApplier` | Offset-based delta chains |
| REF_DELTA resolution | `DONE` | `Pack\DeltaApplier` | Hash-based delta lookup |
| Delta chain following | `DONE` | `Pack\PackFile` | Up to PITMASTER_MAX_DELTA_CHAIN depth |
| Copy/insert instructions | `DONE` | `Pack\DeltaApplier` | Full delta instruction set |
| Pack enumeration | `DONE` | `Pack\PackEnumerator` | Iterate all objects in pack |
| Multi-pack-index (MIDX) | `PART` |  | Performance optimization, not required |
| Commit-graph file | `PART` |  | Performance optimization, not required |

### Index (Staging Area)
*7/7 Git-oracle-mapped*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Index v2 read | `DONE` | `Index\Index` | Most common format |
| Index v2 write | `DONE` | `Index\IndexWriter` | Required for add/commit |
| Index v3 read (extended flags) | `DONE` |  |  |
| Index v4 read (path prefix compression) | `DONE` |  |  |
| Conflict stages (1/2/3) | `DONE` | `Index\IndexEntry` | Git-shaped unmerged index stages for merge-family conflicts |
| Index extensions (TREE, REUC) | `DONE` |  |  |
| Index diff (vs tree/worktree) | `DONE` | `Index\IndexDiff` | Required for status |

### References
*7/9 Git-oracle-mapped, 2 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Loose ref read | `DONE` | `Ref\LooseRefStore` |  |
| Loose ref write | `DONE` | `Ref\LooseRefStore` |  |
| Packed refs read | `DONE` | `Ref\PackedRefStore` | With peeled values |
| Packed refs write | `DONE` | `Repository` | Explicit `packRefs()` API rewrites `packed-refs` |
| Symbolic ref (HEAD) | `DONE` | `Ref\SymbolicRef` | ref: refs/heads/main |
| Ref database (composite) | `DONE` | `Ref\RefDatabase` | Loose priority over packed |
| Reflog read | `DONE` | `Ref\Reflog` |  |
| Reflog write | `PART` | `Ref\Reflog` | Required for proper ref updates |
| Reftable format | `PART` |  | New format, not yet widespread |

### Repository Operations
*2/6 Git-oracle-mapped, 4 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Open existing repo | `DONE` | `Pitmaster` |  |
| Init new repo | `DONE` | `Pitmaster` | Creates .git structure |
| Clone (remote) | `PART` | `Pitmaster` | Via smart HTTP |
| Read .git/config | `PART` | `Config\GitConfig` | INI-style parser |
| Write .git/config | `PART` | `Config\GitConfig` |  |
| Bare repositories | `PART` | `Repository` | Detected by HEAD presence |

### Staging and Commits
*4/12 Git-oracle-mapped, 8 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| git add (stage files) | `DONE` | `Repository` | Update index entries |
| git rm (unstage/remove) | `PART` | `Repository` |  |
| git mv (rename) | `PART` |  | rm + add |
| git commit | `DONE` | `Repository` | Build tree from index, create commit, update HEAD |
| git reset --soft | `PART` |  | Move HEAD only |
| git reset --mixed | `PART` |  | Move HEAD + reset index |
| git reset --hard | `PART` |  | Move HEAD + reset index + worktree |
| git restore | `PART` |  | Restore files from tree/index |
| git stash | `PART` | `Stash\Stash` | refs/stash + reflog stack |
| git cherry-pick | `DONE` |  | Single-commit cherry-pick with conflict continue/abort |
| git revert | `DONE` |  | Single-commit revert with conflict continue/abort |
| git rebase | `PART` |  | Complex; agents can use merge instead |

### Working Tree Status
*5/6 Git-oracle-mapped, 1 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| HEAD vs index diff | `DONE` | `Status\WorkingTreeStatus` | Staged changes |
| Index vs worktree diff | `DONE` | `Status\WorkingTreeStatus` | Unstaged changes |
| Untracked file detection | `DONE` | `Status\WorkingTreeStatus` |  |
| Porcelain v2 output | `DONE` |  | Machine-readable status |
| .gitignore parsing | `DONE` |  | Required for untracked detection |
| Rename detection | `PART` |  | Content similarity matching |

### Diff
*5/11 Git-oracle-mapped, 6 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Myers diff algorithm | `DONE` | `Diff\MyersDiff` | Default, line-level |
| Patience diff algorithm | `PART` | `Diff\PatienceDiff` | Better structural diffs |
| Histogram diff algorithm | `PART` | `Diff\HistogramDiff` | Extension of patience |
| Minimal diff | `PART` | `Diff\MinimalDiff` | Minimize edit script length |
| Tree-to-tree diff | `DONE` | `Diff\TreeDiff` | Recursive tree comparison |
| Unified diff output | `DONE` | `Diff\DiffResult` | Standard patch format |
| Hunk generation | `DONE` | `Diff\Hunk` | Context lines + ranges |
| Binary file detection | `DONE` |  | NUL byte detection |
| Rename detection (diff) | `PART` |  | Content similarity |
| Word diff | `PART` |  |  |
| Color diff output | `PART` |  | Terminal ANSI colors |

### Merge
*2/10 Git-oracle-mapped, 8 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Merge base (LCA) | `DONE` | `Merge\MergeBase` | Lowest common ancestor |
| Three-way merge (content) | `PART` | `Merge\ThreeWayMerge` | Base/ours/theirs blob merge |
| Conflict markers | `PART` | `Merge\ConflictMarker` | <<<<<<< / ======= / >>>>>>> |
| File-level merge (tree) | `PART` |  | Which blobs to merge via TreeDiff |
| Recursive strategy | `PART` |  | Handle multiple merge bases |
| ORT strategy | `PART` | `Merge\RecursiveMerge` | Implemented via RecursiveMerge (equivalent) |
| Octopus merge | `PART` | `Merge\OctopusMerge` | 3+ branches |
| Ours strategy | `PART` |  | Take all from current branch |
| Fast-forward merge | `DONE` |  | Just move the ref |
| Merge commit creation | `PART` |  | Two-parent commit |

### Commit Graph
*2/7 Git-oracle-mapped, 5 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Commit walk (log) | `PART` | `Graph\CommitWalker` | Topological, newest-first |
| Ancestry check | `DONE` | `Graph\AncestryChecker` | Is A ancestor of B? |
| Revision expressions | `DONE` | `Graph\RevisionParser` | HEAD~3, main^2, tag@{1} |
| Log --all (all branches) | `PART` | `Graph\CommitWalker` | walkAll() from multiple tips |
| Log with path filter | `PART` |  | Only commits touching path |
| Log --oneline format | `PART` |  | Short hash + first line |
| git show | `PART` |  | Commit + diff |

### Branch and Tag Operations
*7/9 Git-oracle-mapped, 2 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| List branches | `DONE` | `Repository` |  |
| Create branch | `DONE` | `Repository` |  |
| Delete branch | `DONE` | `Repository` |  |
| List tags | `DONE` | `Repository` |  |
| Create lightweight tag | `DONE` | `Repository` | Via updateRef |
| Create annotated tag | `DONE` |  | Write tag object + ref |
| Delete tag | `DONE` | `Repository` | Via deleteRef |
| Checkout / switch branch | `PART` |  | Update HEAD + worktree + index |
| Detached HEAD | `PART` |  | HEAD points directly to commit |

### Network Protocol
*12/14 Git-oracle-mapped, 2 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Pkt-line encoding/decoding | `PART` | `Protocol\PktLine` | 4-hex-digit length prefix |
| Smart HTTP transport | `DONE` | `Protocol\SmartHttpClient` | HTTPS only (no exec) |
| Protocol v2 | `DONE` |  | Single round-trip, simpler |
| Protocol v1 | `DONE` |  | v2 preferred |
| Ref discovery | `DONE` | `Protocol\RefDiscovery` | Parse remote ref advertisement |
| Capability negotiation | `DONE` | `Protocol\Capability` |  |
| Upload-pack (fetch) | `DONE` | `Protocol\UploadPackClient` | want/have/done negotiation |
| Receive-pack (push) | `DONE` | `Protocol\ReceivePackClient` | Send pack + ref updates |
| Clone via HTTP | `DONE` |  | Ref discovery + full fetch |
| Incremental fetch | `DONE` |  | Only new objects |
| Push | `DONE` |  | Send objects + update remote refs |
| SSH transport | `PART` | `Protocol\SshClient` | Pure PHP via socket + key exchange |
| git:// transport | `DONE` | `Protocol\GitProtocolClient` | Raw TCP socket, pkt-line framing |
| Dumb HTTP | `DONE` |  | Rare, smart HTTP covers all major hosts |

### Encoding
*0/5 Git-oracle-mapped, 5 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| LEB128 unsigned | `PART` | `Encoding\Leb128` | Delta sizes |
| Git varint (MSB-continue) | `PART` | `Encoding\VarInt` | Pack headers |
| OFS_DELTA offset encoding | `PART` | `Encoding\VarInt` | Non-redundant offset |
| Binary reader | `PART` | `Encoding\BinaryReader` | Position-tracked byte stream |
| Pkt-line format | `PART` | `Protocol\PktLine` | 4-hex-digit length prefix |

### Error Handling
*9/9 Git-oracle-mapped*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| ObjectNotFoundException | `DONE` | `Exceptions\ObjectNotFoundException` |  |
| CorruptObjectException | `DONE` | `Exceptions\CorruptObjectException` | Hash mismatch, bad header |
| PackParseException | `DONE` | `Exceptions\PackParseException` | Bad magic, truncated, deep chain |
| IndexParseException | `DONE` | `Exceptions\IndexParseException` |  |
| MergeConflictException | `DONE` | `Exceptions\MergeConflictException` | Raised for merge-family conflict stops |
| ProtocolException | `DONE` | `Exceptions\ProtocolException` |  |
| Malformed loose object handling | `DONE` |  | Graceful error, not crash |
| Truncated pack handling | `DONE` |  |  |
| Circular delta detection | `DONE` |  | Max depth limit exists |

### Advanced Features
*8/15 Git-oracle-mapped, 7 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Submodules | `DONE` | `Submodule\Submodule` | .gitmodules, gitlink entries, init/update/status |
| Worktrees | `DONE` | `Worktree\Worktree` | Multiple working trees, .git file indirection |
| Rerere | `PART` | `Merge\Rerere` | Reuse recorded resolution of conflicts |
| Bisect | `PART` | `Graph\Bisect` | Binary search for bug-introducing commit |
| Stash | `DONE` | `Stash\Stash` | Save/restore working directory state |
| Sparse checkout | `DONE` | `Checkout\SparseCheckout` | Partial working tree via cone patterns |
| Fsmonitor | `PART` | `Status\Fsmonitor` | Filesystem change tracking for faster status |
| Hooks | `PART` | `Hooks\HookRunner` | Detect and invoke .git/hooks/ scripts |
| Git LFS | `PART` | `Lfs\LfsClient` | Pointer files, batch API, download/upload |
| Git attributes | `DONE` | `Config\GitAttributes` |  |
| Shallow clones | `PART` | `Protocol\ShallowClone` |  |
| Git bundles | `DONE` | `Protocol\Bundle` |  |
| Git notes | `DONE` | `Ref\Notes` |  |
| Git blame | `DONE` | `Graph\Blame` |  |
| Git grep | `PART` | `Graph\Grep` |  |

## Progress

```
[#########################...............] 64%

Done:     93 features
Partial:  53 features
Todo:     0 features
Deferred: 0 features
N/A:      0 features
```
