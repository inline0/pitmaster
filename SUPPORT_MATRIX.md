# Pitmaster Support Matrix

Git feature coverage for Pitmaster. **146/146 in-scope features fully implemented and oracle-verified within the declared parity scope (100%).**
Partial support exists for **0/146** additional in-scope features (100% supported to some degree).

Every `DONE`/`PART` row is backed by concrete tests and oracle scenarios via `config/support-evidence.json`, validated by `bin/verify-support-evidence`.

Source of truth: `config/support-matrix.json` + `config/support-evidence.json`.

## Legend

| Status | Meaning |
|--------|---------|
| `DONE` | Fully implemented and oracle-verified within the declared parity scope |
| `PART` | Partially implemented |
| `TODO` | In scope, not yet implemented |
| `DEFER` | Post-v1, not yet planned |
| `N/A` | Out of scope (violates pure-PHP or outside agent-IDE scope) |
| Scope `native` | Pitmaster directly authors or owns the behavior/format |
| Scope `interop` | Pitmaster interoperates with Git-authored state or equivalent compatibility paths |

## Summary

| Category | Done | Partial | Todo | Deferred | N/A | Total |
|----------|------|---------|------|----------|-----|-------|
| Object Model | 11 | 0 | 0 | 0 | 0 | 11 |
| Object Storage | 4 | 0 | 0 | 0 | 0 | 4 |
| Pack Files | 11 | 0 | 0 | 0 | 0 | 11 |
| Index (Staging Area) | 7 | 0 | 0 | 0 | 0 | 7 |
| References | 9 | 0 | 0 | 0 | 0 | 9 |
| Repository Operations | 6 | 0 | 0 | 0 | 0 | 6 |
| Staging and Commits | 12 | 0 | 0 | 0 | 0 | 12 |
| Working Tree Status | 6 | 0 | 0 | 0 | 0 | 6 |
| Diff | 11 | 0 | 0 | 0 | 0 | 11 |
| Merge | 10 | 0 | 0 | 0 | 0 | 10 |
| Commit Graph | 7 | 0 | 0 | 0 | 0 | 7 |
| Branch and Tag Operations | 9 | 0 | 0 | 0 | 0 | 9 |
| Network Protocol | 14 | 0 | 0 | 0 | 0 | 14 |
| Encoding | 5 | 0 | 0 | 0 | 0 | 5 |
| Error Handling | 9 | 0 | 0 | 0 | 0 | 9 |
| Advanced Features | 15 | 0 | 0 | 0 | 0 | 15 |
| **Total** | **146** | **0** | **0** | **0** | **0** | **146** |

## Details

### Object Model
*11/11 fully done (100%), 0 partial*

| Feature | Status | Scope | Class | Notes |
|---------|--------|-------|-------|-------|
| Blob read | `DONE` | `native` | `Object\Blob` |  |
| Blob write | `DONE` | `native` | `Object\Blob` |  |
| Tree read | `DONE` | `native` | `Object\Tree` |  |
| Tree write | `DONE` | `native` | `Object\Tree` | Needed for commit() |
| Commit read | `DONE` | `native` | `Object\Commit` |  |
| Commit write | `DONE` | `native` | `Object\Commit` | Needed for commit() |
| Annotated tag read | `DONE` | `native` | `Object\Tag` |  |
| Annotated tag write | `DONE` | `native` | `Object\Tag` |  |
| Lightweight tag | `DONE` | `native` | `Ref\RefDatabase` | Just a ref pointing to a commit |
| ObjectId SHA-1 | `DONE` | `native` | `Object\ObjectId` | 40-char hex, 20-byte binary |
| ObjectId SHA-256 | `DONE` | `native` | `Object\ObjectId` | SHA-256 repository init/open/write parity with Git-compatible refs, index, status, and tag objects |

### Object Storage
*4/4 fully done (100%), 0 partial*

| Feature | Status | Scope | Class | Notes |
|---------|--------|-------|-------|-------|
| Loose object read | `DONE` | `native` | `Storage\LooseObjectStore` | zlib decompress + header parse |
| Loose object write | `DONE` | `native` | `Storage\LooseObjectStore` | Atomic write via temp+rename |
| Object serialization | `DONE` | `native` | `Storage\ObjectSerializer` | type size\0content format |
| Object database (composite) | `DONE` | `native` | `Storage\ObjectDatabase` | Loose first, then packs |

### Pack Files
*11/11 fully done (100%), 0 partial*

| Feature | Status | Scope | Class | Notes |
|---------|--------|-------|-------|-------|
| Pack file read | `DONE` | `native` | `Pack\PackFile` | PACK v2 format |
| Pack file write | `DONE` | `native` | `Pack\PackWriter` | Native .pack/.idx writing for transport/import paths; Git-managed gc/repack remains interoperable |
| Pack index v2 read | `DONE` | `native` | `Pack\PackIndex` | Fanout + binary search |
| Pack index v1 read | `DONE` | `native` |  | v2 covers all modern repos |
| OFS_DELTA resolution | `DONE` | `native` | `Pack\DeltaApplier` | Offset-based delta chains |
| REF_DELTA resolution | `DONE` | `native` | `Pack\DeltaApplier` | Hash-based delta lookup |
| Delta chain following | `DONE` | `native` | `Pack\PackFile` | Up to PITMASTER_MAX_DELTA_CHAIN depth |
| Copy/insert instructions | `DONE` | `native` | `Pack\DeltaApplier` | Full delta instruction set |
| Pack enumeration | `DONE` | `native` | `Pack\PackEnumerator` | Iterate all objects in pack |
| Multi-pack-index (MIDX) | `DONE` | `interop` | `Pack\MultiPackIndex` | Interop scope: reads Git-generated MIDX metadata |
| Commit-graph file | `DONE` | `interop` | `Pack\CommitGraph` | Interop scope: reads Git-generated commit-graph metadata |

### Index (Staging Area)
*7/7 fully done (100%), 0 partial*

| Feature | Status | Scope | Class | Notes |
|---------|--------|-------|-------|-------|
| Index v2 read | `DONE` | `native` | `Index\Index` | Most common format |
| Index v2 write | `DONE` | `native` | `Index\IndexWriter` | Required for add/commit |
| Index v3 read (extended flags) | `DONE` | `native` |  |  |
| Index v4 read (path prefix compression) | `DONE` | `native` |  |  |
| Conflict stages (1/2/3) | `DONE` | `native` | `Index\IndexEntry` | Required for merge |
| Index extensions (TREE, REUC) | `DONE` | `native` |  |  |
| Index diff (vs tree/worktree) | `DONE` | `native` | `Index\IndexDiff` | Required for status |

### References
*9/9 fully done (100%), 0 partial*

| Feature | Status | Scope | Class | Notes |
|---------|--------|-------|-------|-------|
| Loose ref read | `DONE` | `native` | `Ref\LooseRefStore` |  |
| Loose ref write | `DONE` | `native` | `Ref\LooseRefStore` |  |
| Packed refs read | `DONE` | `native` | `Ref\PackedRefStore` | With peeled values |
| Packed refs write | `DONE` | `native` | `Ref\PackedRefStore` | Native packed-refs write parity via Repository::packRefs and PackedRefStore |
| Symbolic ref (HEAD) | `DONE` | `native` | `Ref\SymbolicRef` | ref: refs/heads/main |
| Ref database (composite) | `DONE` | `native` | `Ref\RefDatabase` | Loose priority over packed |
| Reflog read | `DONE` | `native` | `Ref\Reflog` |  |
| Reflog write | `DONE` | `native` | `Ref\Reflog` | Required for proper ref updates |
| Reftable format | `DONE` | `interop` | `Ref\Reftable` | Interop scope: Git-generated reftable read/open parity through RefDatabase and Repository |

### Repository Operations
*6/6 fully done (100%), 0 partial*

| Feature | Status | Scope | Class | Notes |
|---------|--------|-------|-------|-------|
| Open existing repo | `DONE` | `native` | `Pitmaster` |  |
| Init new repo | `DONE` | `native` | `Pitmaster` | Creates .git structure |
| Clone (remote) | `DONE` | `native` | `Pitmaster` | Via smart HTTP |
| Read .git/config | `DONE` | `native` | `Config\GitConfig` | INI-style parser |
| Write .git/config | `DONE` | `native` | `Config\GitConfig` |  |
| Bare repositories | `DONE` | `native` | `Repository` | Detected by HEAD presence |

### Staging and Commits
*12/12 fully done (100%), 0 partial*

| Feature | Status | Scope | Class | Notes |
|---------|--------|-------|-------|-------|
| git add (stage files) | `DONE` | `native` | `Repository` | Update index entries |
| git rm (unstage/remove) | `DONE` | `native` | `Repository` |  |
| git mv (rename) | `DONE` | `native` |  | rm + add |
| git commit | `DONE` | `native` | `Repository` | Build tree from index, create commit, update HEAD |
| git reset --soft | `DONE` | `native` |  | Move HEAD only |
| git reset --mixed | `DONE` | `native` |  | Move HEAD + reset index |
| git reset --hard | `DONE` | `native` |  | Move HEAD + reset index + worktree |
| git restore | `DONE` | `native` |  | Restore files from tree/index |
| git stash | `DONE` | `native` | `Stash\Stash` | Tracked and untracked stash push/apply/pop conflict parity |
| git cherry-pick | `DONE` | `native` |  | Apply commit as new commit |
| git revert | `DONE` | `native` |  | Inverse cherry-pick |
| git rebase | `DONE` | `native` |  | Linear non-merge rebase lifecycle parity |

### Working Tree Status
*6/6 fully done (100%), 0 partial*

| Feature | Status | Scope | Class | Notes |
|---------|--------|-------|-------|-------|
| HEAD vs index diff | `DONE` | `native` | `Status\WorkingTreeStatus` | Staged changes |
| Index vs worktree diff | `DONE` | `native` | `Status\WorkingTreeStatus` | Unstaged changes |
| Untracked file detection | `DONE` | `native` | `Status\WorkingTreeStatus` |  |
| Porcelain v2 output | `DONE` | `native` |  | Machine-readable status |
| .gitignore parsing | `DONE` | `native` |  | Required for untracked detection |
| Rename detection | `DONE` | `native` |  | Status-side staged rename reporting in porcelain and human status output |

### Diff
*11/11 fully done (100%), 0 partial*

| Feature | Status | Scope | Class | Notes |
|---------|--------|-------|-------|-------|
| Myers diff algorithm | `DONE` | `native` | `Diff\MyersDiff` | Default, line-level |
| Patience diff algorithm | `DONE` | `native` | `Diff\PatienceDiff` | Dedicated patience anchor selection with Git-backed unified diff parity |
| Histogram diff algorithm | `DONE` | `native` | `Diff\HistogramDiff` | Dedicated histogram anchor selection with Git-backed unified diff parity |
| Minimal diff | `DONE` | `native` | `Diff\MinimalDiff` | Minimal Myers search path with Git-backed unified diff parity |
| Tree-to-tree diff | `DONE` | `native` | `Diff\TreeDiff` | Recursive tree comparison |
| Unified diff output | `DONE` | `native` | `Diff\DiffResult` | Standard patch format |
| Hunk generation | `DONE` | `native` | `Diff\Hunk` | Context lines + ranges |
| Binary file detection | `DONE` | `native` |  | NUL byte detection |
| Rename detection (diff) | `DONE` | `native` |  | TreeDiff content-similarity rename heuristic |
| Word diff | `DONE` | `native` |  |  |
| Color diff output | `DONE` | `native` |  | Terminal ANSI colors |

### Merge
*10/10 fully done (100%), 0 partial*

| Feature | Status | Scope | Class | Notes |
|---------|--------|-------|-------|-------|
| Merge base (LCA) | `DONE` | `native` | `Merge\MergeBase` | Lowest common ancestor |
| Three-way merge (content) | `DONE` | `native` | `Merge\ThreeWayMerge` | Git-backed clean, conflicted, disjoint-edit, and diff3 content merge parity |
| Conflict markers | `DONE` | `native` | `Merge\ConflictMarker` | Default and diff3 marker styles match Git for merge-family conflicts |
| File-level merge (tree) | `DONE` | `native` |  | Git-backed tree merge parity including rename/delete conflict handling |
| Recursive strategy | `DONE` | `native` |  | Recursive virtual-base merge parity for multi-base criss-cross histories |
| ORT strategy | `DONE` | `native` | `Merge\RecursiveMerge` | Explicit ort strategy selection matches Git on supported recursive merge cases |
| Octopus merge | `DONE` | `native` | `Merge\OctopusMerge` | Repository-level clean octopus merge parity |
| Ours strategy | `DONE` | `native` | `Merge\OursMerge` | Repository-level ours-strategy merge parity |
| Fast-forward merge | `DONE` | `native` |  | Just move the ref |
| Merge commit creation | `DONE` | `native` |  | Two-parent commit |

### Commit Graph
*7/7 fully done (100%), 0 partial*

| Feature | Status | Scope | Class | Notes |
|---------|--------|-------|-------|-------|
| Commit walk (log) | `DONE` | `native` | `Graph\CommitWalker` | Topological, newest-first |
| Ancestry check | `DONE` | `native` | `Graph\AncestryChecker` | Is A ancestor of B? |
| Revision expressions | `DONE` | `native` | `Graph\RevisionParser` | HEAD~3, main^2, tag@{1} |
| Log --all (all branches) | `DONE` | `native` | `Graph\CommitWalker` | walkAll() from multiple tips |
| Log with path filter | `DONE` | `native` |  | Only commits touching path |
| Log --oneline format | `DONE` | `native` |  | Short hash + first line |
| git show | `DONE` | `native` |  | Git-shaped CLI/API parity for single-parent, annotated-tag, and merge commits |

### Branch and Tag Operations
*9/9 fully done (100%), 0 partial*

| Feature | Status | Scope | Class | Notes |
|---------|--------|-------|-------|-------|
| List branches | `DONE` | `native` | `Repository` |  |
| Create branch | `DONE` | `native` | `Repository` |  |
| Delete branch | `DONE` | `native` | `Repository` |  |
| List tags | `DONE` | `native` | `Repository` |  |
| Create lightweight tag | `DONE` | `native` | `Repository` | Via updateRef |
| Create annotated tag | `DONE` | `native` |  | Write tag object + ref |
| Delete tag | `DONE` | `native` | `Repository` | Via deleteRef |
| Checkout / switch branch | `DONE` | `native` |  | Update HEAD + worktree + index |
| Detached HEAD | `DONE` | `native` |  | HEAD points directly to commit |

### Network Protocol
*14/14 fully done (100%), 0 partial*

| Feature | Status | Scope | Class | Notes |
|---------|--------|-------|-------|-------|
| Pkt-line encoding/decoding | `DONE` | `native` | `Protocol\PktLine` | 4-hex-digit length prefix |
| Smart HTTP transport | `DONE` | `native` | `Protocol\SmartHttpClient` | HTTPS only (no exec) |
| Protocol v2 | `DONE` | `native` |  | Single round-trip, simpler |
| Protocol v1 | `DONE` | `native` |  | v2 preferred |
| Ref discovery | `DONE` | `native` | `Protocol\RefDiscovery` | Parse remote ref advertisement |
| Capability negotiation | `DONE` | `native` | `Protocol\Capability` |  |
| Upload-pack (fetch) | `DONE` | `native` | `Protocol\UploadPackClient` | want/have/done negotiation |
| Receive-pack (push) | `DONE` | `native` | `Protocol\ReceivePackClient` | Send pack + ref updates |
| Clone via HTTP | `DONE` | `native` |  | Ref discovery + full fetch |
| Incremental fetch | `DONE` | `native` |  | Only new objects |
| Push | `DONE` | `native` |  | Send objects + update remote refs |
| SSH transport | `DONE` | `native` | `Protocol\SshClient` | Git-over-SSH clone/fetch/push parity with an in-repo sshd-backed oracle scenario |
| git:// transport | `DONE` | `native` | `Protocol\GitProtocolClient` | Raw TCP socket, pkt-line framing |
| Dumb HTTP | `DONE` | `native` |  | Rare, smart HTTP covers all major hosts |

### Encoding
*5/5 fully done (100%), 0 partial*

| Feature | Status | Scope | Class | Notes |
|---------|--------|-------|-------|-------|
| LEB128 unsigned | `DONE` | `native` | `Encoding\Leb128` | Delta sizes |
| Git varint (MSB-continue) | `DONE` | `native` | `Encoding\VarInt` | Pack headers |
| OFS_DELTA offset encoding | `DONE` | `native` | `Encoding\VarInt` | Non-redundant offset |
| Binary reader | `DONE` | `native` | `Encoding\BinaryReader` | Position-tracked byte stream |
| Pkt-line format | `DONE` | `native` | `Protocol\PktLine` | 4-hex-digit length prefix |

### Error Handling
*9/9 fully done (100%), 0 partial*

| Feature | Status | Scope | Class | Notes |
|---------|--------|-------|-------|-------|
| ObjectNotFoundException | `DONE` | `native` | `Exceptions\ObjectNotFoundException` |  |
| CorruptObjectException | `DONE` | `native` | `Exceptions\CorruptObjectException` | Hash mismatch, bad header |
| PackParseException | `DONE` | `native` | `Exceptions\PackParseException` | Bad magic, truncated, deep chain |
| IndexParseException | `DONE` | `native` | `Exceptions\IndexParseException` |  |
| MergeConflictException | `DONE` | `native` | `Exceptions\MergeConflictException` |  |
| ProtocolException | `DONE` | `native` | `Exceptions\ProtocolException` |  |
| Malformed loose object handling | `DONE` | `native` |  | Graceful error, not crash |
| Truncated pack handling | `DONE` | `native` |  |  |
| Circular delta detection | `DONE` | `native` |  | Max depth limit exists |

### Advanced Features
*15/15 fully done (100%), 0 partial*

| Feature | Status | Scope | Class | Notes |
|---------|--------|-------|-------|-------|
| Submodules | `DONE` | `native` | `Submodule\Submodule` | .gitmodules, gitlink entries, init/update/status |
| Worktrees | `DONE` | `native` | `Worktree\Worktree` | Multiple working trees, .git file indirection |
| Rerere | `DONE` | `native` | `Merge\Rerere` | Git-compatible rr-cache preimage/postimage read and write parity |
| Bisect | `DONE` | `native` | `Graph\Bisect` | Linear bisect start/good/bad/reset parity with Git-shaped BISECT state |
| Stash | `DONE` | `native` | `Stash\Stash` | Tracked and untracked stash push/apply/pop conflict parity |
| Sparse checkout | `DONE` | `native` | `Checkout\SparseCheckout` | Partial working tree via cone patterns |
| Fsmonitor | `DONE` | `native` | `Status\Fsmonitor` | Canonical core.fsmonitor hook protocol parity with fallback scanning |
| Hooks | `DONE` | `native` | `Hooks\HookRunner` | Commit, checkout, merge, rebase, and push hook parity |
| Git LFS | `DONE` | `native` | `Lfs\LfsClient` | Pointer parsing and batch upload/download parity against a repo-local git-lfs oracle |
| Git attributes | `DONE` | `native` | `Config\GitAttributes` |  |
| Shallow clones | `DONE` | `native` | `Protocol\ShallowClone` | Smart HTTP depth clone/fetch parity with Git-compatible shallow-file updates |
| Git bundles | `DONE` | `native` | `Protocol\Bundle` |  |
| Git notes | `DONE` | `native` | `Ref\Notes` |  |
| Git blame | `DONE` | `native` | `Graph\Blame` |  |
| Git grep | `DONE` | `native` | `Graph\Grep` |  |

## Progress

```
[########################################] 100% fully done

Full:     146 features
Partial:  0 features
Todo:     0 features
Deferred: 0 features
N/A:      0 features
```
