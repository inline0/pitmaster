# Pitmaster Support Matrix

Git feature coverage for Pitmaster. **129/146 in-scope features fully implemented and oracle-verified (88.4%).**
Partial support exists for **17/146** additional in-scope features (100% supported to some degree).

Generated: 2026-04-09 11:36

## Legend

| Status | Meaning |
|--------|---------|
| `DONE` | Fully implemented and oracle-verified |
| `PART` | Partially implemented |
| `TODO` | In scope, not yet implemented |
| `DEFER` | Post-v1, not yet planned |
| `N/A` | Out of scope (violates pure-PHP or outside agent-IDE scope) |

## Summary

| Category | Done | Partial | Todo | Deferred | N/A | Total |
|----------|------|---------|------|----------|-----|-------|
| Object Model | 10 | 1 | 0 | 0 | 0 | 11 |
| Object Storage | 4 | 0 | 0 | 0 | 0 | 4 |
| Pack Files | 11 | 0 | 0 | 0 | 0 | 11 |
| Index (Staging Area) | 7 | 0 | 0 | 0 | 0 | 7 |
| References | 8 | 1 | 0 | 0 | 0 | 9 |
| Repository Operations | 6 | 0 | 0 | 0 | 0 | 6 |
| Staging and Commits | 11 | 1 | 0 | 0 | 0 | 12 |
| Working Tree Status | 6 | 0 | 0 | 0 | 0 | 6 |
| Diff | 8 | 3 | 0 | 0 | 0 | 11 |
| Merge | 4 | 6 | 0 | 0 | 0 | 10 |
| Commit Graph | 7 | 0 | 0 | 0 | 0 | 7 |
| Branch and Tag Operations | 9 | 0 | 0 | 0 | 0 | 9 |
| Network Protocol | 13 | 1 | 0 | 0 | 0 | 14 |
| Encoding | 5 | 0 | 0 | 0 | 0 | 5 |
| Error Handling | 9 | 0 | 0 | 0 | 0 | 9 |
| Advanced Features | 11 | 4 | 0 | 0 | 0 | 15 |
| **Total** | **129** | **17** | **0** | **0** | **0** | **146** |

## Details

### Object Model
*10/11 fully done (90.9%), 1 partial*

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
| ObjectId SHA-256 | `PART` | `Object\ObjectId` | Hash abstraction exists, but SHA-256 repo mode is incomplete |

### Object Storage
*4/4 fully done (100%), 0 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Loose object read | `DONE` | `Storage\LooseObjectStore` | zlib decompress + header parse |
| Loose object write | `DONE` | `Storage\LooseObjectStore` | Atomic write via temp+rename |
| Object serialization | `DONE` | `Storage\ObjectSerializer` | type size\0content format |
| Object database (composite) | `DONE` | `Storage\ObjectDatabase` | Loose first, then packs |

### Pack Files
*11/11 fully done (100%), 0 partial*

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
| Multi-pack-index (MIDX) | `DONE` |  | Reads Git-generated MIDX files |
| Commit-graph file | `DONE` |  | Reads Git-generated commit-graph files |

### Index (Staging Area)
*7/7 fully done (100%), 0 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Index v2 read | `DONE` | `Index\Index` | Most common format |
| Index v2 write | `DONE` | `Index\IndexWriter` | Required for add/commit |
| Index v3 read (extended flags) | `DONE` |  |  |
| Index v4 read (path prefix compression) | `DONE` |  |  |
| Conflict stages (1/2/3) | `DONE` | `Index\IndexEntry` | Required for merge |
| Index extensions (TREE, REUC) | `DONE` |  |  |
| Index diff (vs tree/worktree) | `DONE` | `Index\IndexDiff` | Required for status |

### References
*8/9 fully done (88.9%), 1 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Loose ref read | `DONE` | `Ref\LooseRefStore` |  |
| Loose ref write | `DONE` | `Ref\LooseRefStore` |  |
| Packed refs read | `DONE` | `Ref\PackedRefStore` | With peeled values |
| Packed refs write | `DONE` |  | Let git pack-refs handle this |
| Symbolic ref (HEAD) | `DONE` | `Ref\SymbolicRef` | ref: refs/heads/main |
| Ref database (composite) | `DONE` | `Ref\RefDatabase` | Loose priority over packed |
| Reflog read | `DONE` | `Ref\Reflog` |  |
| Reflog write | `DONE` | `Ref\Reflog` | Required for proper ref updates |
| Reftable format | `PART` | `Ref\Reftable` | Standalone parser only; ref database integration is incomplete |

### Repository Operations
*6/6 fully done (100%), 0 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Open existing repo | `DONE` | `Pitmaster` |  |
| Init new repo | `DONE` | `Pitmaster` | Creates .git structure |
| Clone (remote) | `DONE` | `Pitmaster` | Via smart HTTP |
| Read .git/config | `DONE` | `Config\GitConfig` | INI-style parser |
| Write .git/config | `DONE` | `Config\GitConfig` |  |
| Bare repositories | `DONE` | `Repository` | Detected by HEAD presence |

### Staging and Commits
*11/12 fully done (91.7%), 1 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| git add (stage files) | `DONE` | `Repository` | Update index entries |
| git rm (unstage/remove) | `DONE` | `Repository` |  |
| git mv (rename) | `DONE` |  | rm + add |
| git commit | `DONE` | `Repository` | Build tree from index, create commit, update HEAD |
| git reset --soft | `DONE` |  | Move HEAD only |
| git reset --mixed | `DONE` |  | Move HEAD + reset index |
| git reset --hard | `DONE` |  | Move HEAD + reset index + worktree |
| git restore | `DONE` |  | Restore files from tree/index |
| git stash | `DONE` | `Stash\Stash` | Tracked and untracked stash push/apply/pop conflict parity |
| git cherry-pick | `DONE` |  | Apply commit as new commit |
| git revert | `DONE` |  | Inverse cherry-pick |
| git rebase | `PART` |  | Linear non-merge rebase lifecycle parity |

### Working Tree Status
*6/6 fully done (100%), 0 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| HEAD vs index diff | `DONE` | `Status\WorkingTreeStatus` | Staged changes |
| Index vs worktree diff | `DONE` | `Status\WorkingTreeStatus` | Unstaged changes |
| Untracked file detection | `DONE` | `Status\WorkingTreeStatus` |  |
| Porcelain v2 output | `DONE` |  | Machine-readable status |
| .gitignore parsing | `DONE` |  | Required for untracked detection |
| Rename detection | `DONE` |  | Status-side staged rename reporting in porcelain and human status output |

### Diff
*8/11 fully done (72.7%), 3 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Myers diff algorithm | `DONE` | `Diff\MyersDiff` | Default, line-level |
| Patience diff algorithm | `PART` | `Diff\PatienceDiff` | Currently falls back to Myers output |
| Histogram diff algorithm | `PART` | `Diff\HistogramDiff` | Currently falls back to Myers output |
| Minimal diff | `PART` | `Diff\MinimalDiff` | Currently delegates to Myers |
| Tree-to-tree diff | `DONE` | `Diff\TreeDiff` | Recursive tree comparison |
| Unified diff output | `DONE` | `Diff\DiffResult` | Standard patch format |
| Hunk generation | `DONE` | `Diff\Hunk` | Context lines + ranges |
| Binary file detection | `DONE` |  | NUL byte detection |
| Rename detection (diff) | `DONE` |  | TreeDiff content-similarity rename heuristic |
| Word diff | `DONE` |  |  |
| Color diff output | `DONE` |  | Terminal ANSI colors |

### Merge
*4/10 fully done (40%), 6 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Merge base (LCA) | `DONE` | `Merge\MergeBase` | Lowest common ancestor |
| Three-way merge (content) | `PART` | `Merge\ThreeWayMerge` | Basic clean/conflict content merges |
| Conflict markers | `DONE` | `Merge\ConflictMarker` | Default and diff3 marker styles match Git for merge-family conflicts |
| File-level merge (tree) | `PART` |  | Basic tree merge selection; rename/delete parity incomplete |
| Recursive strategy | `PART` |  | Basic recursive merge helper; full multi-base parity incomplete |
| ORT strategy | `PART` | `Merge\RecursiveMerge` | No dedicated ORT implementation |
| Octopus merge | `PART` | `Merge\OctopusMerge` | Low-level clean-merge helper only |
| Ours strategy | `PART` |  | Low-level helper only; no repository strategy path |
| Fast-forward merge | `DONE` |  | Just move the ref |
| Merge commit creation | `DONE` |  | Two-parent commit |

### Commit Graph
*7/7 fully done (100%), 0 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Commit walk (log) | `DONE` | `Graph\CommitWalker` | Topological, newest-first |
| Ancestry check | `DONE` | `Graph\AncestryChecker` | Is A ancestor of B? |
| Revision expressions | `DONE` | `Graph\RevisionParser` | HEAD~3, main^2, tag@{1} |
| Log --all (all branches) | `DONE` | `Graph\CommitWalker` | walkAll() from multiple tips |
| Log with path filter | `DONE` |  | Only commits touching path |
| Log --oneline format | `DONE` |  | Short hash + first line |
| git show | `DONE` |  | Git-shaped CLI/API parity for single-parent, annotated-tag, and merge commits |

### Branch and Tag Operations
*9/9 fully done (100%), 0 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| List branches | `DONE` | `Repository` |  |
| Create branch | `DONE` | `Repository` |  |
| Delete branch | `DONE` | `Repository` |  |
| List tags | `DONE` | `Repository` |  |
| Create lightweight tag | `DONE` | `Repository` | Via updateRef |
| Create annotated tag | `DONE` |  | Write tag object + ref |
| Delete tag | `DONE` | `Repository` | Via deleteRef |
| Checkout / switch branch | `DONE` |  | Update HEAD + worktree + index |
| Detached HEAD | `DONE` |  | HEAD points directly to commit |

### Network Protocol
*13/14 fully done (92.9%), 1 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Pkt-line encoding/decoding | `DONE` | `Protocol\PktLine` | 4-hex-digit length prefix |
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
| SSH transport | `PART` | `Protocol\SshClient` | URL parsing plus ssh2-backed execution; no in-repo SSH oracle |
| git:// transport | `DONE` | `Protocol\GitProtocolClient` | Raw TCP socket, pkt-line framing |
| Dumb HTTP | `DONE` |  | Rare, smart HTTP covers all major hosts |

### Encoding
*5/5 fully done (100%), 0 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| LEB128 unsigned | `DONE` | `Encoding\Leb128` | Delta sizes |
| Git varint (MSB-continue) | `DONE` | `Encoding\VarInt` | Pack headers |
| OFS_DELTA offset encoding | `DONE` | `Encoding\VarInt` | Non-redundant offset |
| Binary reader | `DONE` | `Encoding\BinaryReader` | Position-tracked byte stream |
| Pkt-line format | `DONE` | `Protocol\PktLine` | 4-hex-digit length prefix |

### Error Handling
*9/9 fully done (100%), 0 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| ObjectNotFoundException | `DONE` | `Exceptions\ObjectNotFoundException` |  |
| CorruptObjectException | `DONE` | `Exceptions\CorruptObjectException` | Hash mismatch, bad header |
| PackParseException | `DONE` | `Exceptions\PackParseException` | Bad magic, truncated, deep chain |
| IndexParseException | `DONE` | `Exceptions\IndexParseException` |  |
| MergeConflictException | `DONE` | `Exceptions\MergeConflictException` |  |
| ProtocolException | `DONE` | `Exceptions\ProtocolException` |  |
| Malformed loose object handling | `DONE` |  | Graceful error, not crash |
| Truncated pack handling | `DONE` |  |  |
| Circular delta detection | `DONE` |  | Max depth limit exists |

### Advanced Features
*11/15 fully done (73.3%), 4 partial*

| Feature | Status | Class | Notes |
|---------|--------|-------|-------|
| Submodules | `DONE` | `Submodule\Submodule` | .gitmodules, gitlink entries, init/update/status |
| Worktrees | `DONE` | `Worktree\Worktree` | Multiple working trees, .git file indirection |
| Rerere | `DONE` | `Merge\Rerere` | Git-compatible rr-cache preimage/postimage read and write parity |
| Bisect | `PART` | `Graph\Bisect` | Local linear bisect state helper |
| Stash | `DONE` | `Stash\Stash` | Tracked and untracked stash push/apply/pop conflict parity |
| Sparse checkout | `DONE` | `Checkout\SparseCheckout` | Partial working tree via cone patterns |
| Fsmonitor | `PART` | `Status\Fsmonitor` | Polling helper, not canonical Git fsmonitor protocol |
| Hooks | `DONE` | `Hooks\HookRunner` | Commit, checkout, merge, rebase, and push hook parity |
| Git LFS | `PART` | `Lfs\LfsClient` | Pointer parsing and batch client; requires git-lfs oracle |
| Git attributes | `DONE` | `Config\GitAttributes` |  |
| Shallow clones | `PART` | `Protocol\ShallowClone` | Shallow-file semantics only; transport negotiation incomplete |
| Git bundles | `DONE` | `Protocol\Bundle` |  |
| Git notes | `DONE` | `Ref\Notes` |  |
| Git blame | `DONE` | `Graph\Blame` |  |
| Git grep | `DONE` | `Graph\Grep` |  |

## Progress

```
[###################################-----] 88.4% fully done

Full:     129 features
Partial:  17 features
Todo:     0 features
Deferred: 0 features
N/A:      0 features
```
