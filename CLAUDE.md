# Pitmaster

Pure PHP Git implementation. Reads and writes Git repositories without shelling out to `git`, without FFI, without extensions. The canonical `git` binary is the oracle: if `git` accepts what Pitmaster writes, and Pitmaster can read what `git` writes, it is correct.

## Quick Reference

```bash
# Testing (oracle-driven)
./bin/verify-all                        # Required final gate: analyse + cs + phpunit + full oracle regression
./bin/test-scenario <name>               # Single scenario: oracle → actual → compare
./bin/test-regression                    # All scenarios
./bin/test-regression --jobs 4           # Parallel
./bin/test-regression --category diff    # By category
./bin/test-regression --fast             # Pass/fail only, no reports
./bin/verify-compliance                  # Full compliance report

# Oracle management
./bin/oracle <name>                      # Capture git output for scenario
./bin/oracle --refresh <name>            # Re-capture (after git upgrade or setup change)
./bin/actual <name>                      # Run Pitmaster, capture output
./bin/compare <name>                     # Diff oracle vs actual

# Unit tests (no git binary needed)
composer test:unit                       # Isolated component tests
composer test:oracle                     # Vendored upstream oracle corpus
composer test                            # Full phpunit + oracle matrix

# Code quality
composer cs                              # Check coding standards
composer cs:fix                          # Fix coding standards
composer analyse                         # PHPStan static analysis

# CLI
./bin/pitmaster log                      # Walk commit history
./bin/pitmaster cat-file <hash>          # Read object by hash
./bin/pitmaster status                   # Working tree status
./bin/pitmaster diff                     # Diff index vs worktree
```

## Non-Negotiable Testing Rule

After every meaningful work pass, run the full matrix from the repo root before treating the work as done:

```bash
./bin/verify-all
```

No partial sign-off. `composer test` is the full test matrix, not just PHPUnit. Upstream scenario fixtures must stay vendored under `fixtures/upstream`; scenario setup scripts and acquisition scripts must never depend on `/tmp` or `/private/tmp`.

## Autonomous Batch Protocol

When [`SESSION_EXECUTION_QUEUE.md`](SESSION_EXECUTION_QUEUE.md) exists, treat it as the active execution queue for long work passes and use [`ORACLE_PARITY_TODO.md`](ORACLE_PARITY_TODO.md) as the source-of-truth audit behind it. If the session queue does not exist, fall back to the remaining non-`Mapped` rows in the audit file, starting with the `Remaining High-Risk Rows` section and then continuing through the audit in file order.

1. Default behavior is to keep working through the backlog instead of stopping after a single fix. Target a double-digit batch of completed rows per autonomous pass unless a shared blocker or a red `./bin/verify-all` gate stops further work.
2. A row is not done until code, Git-backed integration coverage, scenario coverage, and backlog/support-matrix updates all land together.
3. Run targeted checks while iterating, then run `./bin/verify-all` after each completed wave and again at the end of the pass if anything changed after the last wave gate.
4. If a row proves out of scope for stock Git or cannot honestly be kept as `DONE`, correct the claim immediately instead of leaving the overstatement in place.

## What This Is

A library that operates on `.git` directories natively in PHP. It:

1. Reads and writes loose objects (blob, tree, commit, tag)
2. Reads pack files and resolves delta chains
3. Reads and writes the index (staging area)
4. Reads and writes refs (branches, tags, HEAD)
5. Computes diffs (Myers algorithm)
6. Performs three-way merges
7. Walks commit graphs (log, ancestry, merge base)
8. Speaks the Git smart HTTP protocol (clone, fetch, push)

All without `exec('git ...')`. The only PHP requirements are `sha1()` (built-in), `zlib_encode()`/`zlib_decode()` (built-in), and `hash()` for SHA-256 (built-in). No extensions.

## What This Is Not

Not a wrapper around the `git` binary. Pitmaster is a full, standalone git client implemented entirely in PHP. Every operation that canonical `git` supports is in scope: submodules, worktrees, rerere, bisect, stash, sparse checkout, fsmonitor, hooks, LFS, SSH transport, and the git:// protocol.

## Project Structure

```
pitmaster/
├── src/
│   ├── Pitmaster.php                    # Static facade (public API entry point)
│   ├── Repository.php                   # Repository handle (wraps .git directory)
│   │
│   ├── Object/
│   │   ├── GitObject.php                # Base: type + size + content + hash
│   │   ├── Blob.php                     # Raw file content
│   │   ├── Tree.php                     # Directory listing (mode, name, hash entries)
│   │   ├── TreeEntry.php                # Single tree entry (readonly value object)
│   │   ├── Commit.php                   # Tree + parents + author + committer + message
│   │   ├── Tag.php                      # Annotated tag (object + type + name + tagger)
│   │   ├── ObjectId.php                 # SHA-1 or SHA-256 hash (readonly, hex + binary)
│   │   └── ObjectType.php              # Enum: Blob, Tree, Commit, Tag
│   │
│   ├── Storage/
│   │   ├── ObjectStore.php              # Interface: read/write/exists for objects
│   │   ├── LooseObjectStore.php         # objects/XX/YYYY... (zlib compressed)
│   │   ├── PackFileStore.php            # Pack file reader (resolves deltas)
│   │   ├── ObjectDatabase.php           # Composite: loose + packs, write-through to loose
│   │   └── ObjectSerializer.php         # Encode/decode object wire format (type size\0content)
│   │
│   ├── Pack/
│   │   ├── PackFile.php                 # Single .pack file reader
│   │   ├── PackIndex.php                # .idx file reader (v1 and v2)
│   │   ├── PackEntry.php                # Parsed pack entry (type, offset, size)
│   │   ├── DeltaResolver.php            # Resolves OFS_DELTA and REF_DELTA chains
│   │   ├── DeltaApplier.php             # Applies copy/insert delta instructions
│   │   └── PackEnumerator.php           # Iterate all objects in a pack
│   │
│   ├── Index/
│   │   ├── Index.php                    # .git/index reader/writer
│   │   ├── IndexEntry.php               # Single index entry (readonly value object)
│   │   ├── IndexWriter.php              # Serialize index back to binary
│   │   └── IndexDiff.php               # Compare index against tree or worktree
│   │
│   ├── Ref/
│   │   ├── RefStore.php                 # Interface: resolve, list, update refs
│   │   ├── LooseRefStore.php            # refs/heads/*, refs/tags/*, HEAD as files
│   │   ├── PackedRefStore.php           # packed-refs file reader
│   │   ├── RefDatabase.php              # Composite: loose + packed, write to loose
│   │   ├── SymbolicRef.php              # Symbolic reference (HEAD -> refs/heads/main)
│   │   └── Reflog.php                   # Reflog reader/writer
│   │
│   ├── Diff/
│   │   ├── MyersDiff.php                # Myers diff algorithm (line-level)
│   │   ├── PatienceDiff.php             # Patience diff (better structural diffs)
│   │   ├── DiffResult.php               # Hunks with context lines
│   │   ├── TreeDiff.php                 # Tree-to-tree diff (recursive)
│   │   └── Hunk.php                     # Single diff hunk (old/new ranges + lines)
│   │
│   ├── Merge/
│   │   ├── ThreeWayMerge.php            # Three-way merge algorithm
│   │   ├── MergeResult.php              # Merged content + conflicts
│   │   ├── ConflictMarker.php           # <<<<<<< / ======= / >>>>>>> generation
│   │   └── MergeBase.php               # Lowest common ancestor(s) in commit graph
│   │
│   ├── Graph/
│   │   ├── CommitWalker.php             # Topological commit traversal (log)
│   │   ├── AncestryChecker.php          # Is commit A ancestor of B?
│   │   └── RevisionParser.php           # Parse revision expressions (HEAD~3, main^2)
│   │
│   ├── Status/
│   │   ├── WorkingTreeStatus.php        # Compare HEAD vs index vs worktree
│   │   ├── FileStatus.php               # Enum: Added, Modified, Deleted, Untracked, Renamed
│   │   └── StatusEntry.php              # Single file status (readonly value object)
│   │
│   ├── Protocol/
│   │   ├── SmartHttpClient.php          # Git smart HTTP transport
│   │   ├── PktLine.php                  # Pkt-line encoding/decoding
│   │   ├── RefDiscovery.php             # Parse remote ref advertisement
│   │   ├── UploadPackClient.php         # Fetch negotiation (want/have/done)
│   │   ├── ReceivePackClient.php        # Push (old/new/ref + pack)
│   │   └── Capability.php              # Protocol capability negotiation
│   │
│   ├── Config/
│   │   ├── GitConfig.php                # .git/config and ~/.gitconfig parser
│   │   └── ConfigEntry.php              # Single config key/value
│   │
│   ├── Encoding/
│   │   ├── Leb128.php                   # LEB128 variable-length integer codec
│   │   ├── VarInt.php                   # Git-style variable-length integer codec
│   │   └── BinaryReader.php            # Low-level byte stream with position tracking
│   │
│   └── Exceptions/
│       ├── ObjectNotFoundException.php
│       ├── CorruptObjectException.php
│       ├── PackParseException.php
│       ├── IndexParseException.php
│       ├── MergeConflictException.php
│       └── ProtocolException.php
│
├── bin/
│   ├── pitmaster                        # CLI entry point (mirrors subset of git commands)
│   ├── oracle                           # Capture canonical git output for a scenario
│   ├── actual                           # Run Pitmaster on a scenario, capture output
│   ├── compare                          # Diff oracle vs actual for a scenario
│   ├── test-scenario                    # Full pipeline: oracle → actual → compare
│   ├── test-regression                  # Run all scenarios (with --jobs for parallelism)
│   └── verify-compliance                # Full compliance report
│
├── tests/
│   ├── Unit/                            # Isolated component tests (no git binary needed)
│   └── Oracle/
│       ├── OracleCapture.php            # Runs git commands, captures structured output
│       ├── ActualCapture.php            # Runs Pitmaster operations, captures same structure
│       ├── ScenarioComparator.php       # Diffs oracle vs actual output
│       ├── ScenarioRunner.php           # Orchestrates: setup → oracle → actual → compare
│       └── ScenarioRepository.php       # Discovers and loads scenarios from disk
│
├── scenarios/
│   ├── objects/                          # Object read/write scenarios
│   │   ├── blob-simple/
│   │   ├── blob-binary/
│   │   ├── blob-empty/
│   │   ├── blob-large/
│   │   ├── tree-flat/
│   │   ├── tree-nested/
│   │   ├── tree-modes/                  # executable, symlink, gitlink
│   │   ├── commit-root/                 # No parents
│   │   ├── commit-single-parent/
│   │   ├── commit-merge/                # Two parents
│   │   ├── commit-octopus/              # Three+ parents
│   │   ├── commit-unicode/              # UTF-8 author, message
│   │   └── tag-annotated/
│   │
│   ├── packs/                           # Pack file reading scenarios
│   │   ├── pack-undeltified/
│   │   ├── pack-ofs-delta/
│   │   ├── pack-ref-delta/
│   │   ├── pack-deep-chain/             # Delta chain depth > 10
│   │   ├── pack-mixed/                  # Mix of base + delta objects
│   │   └── pack-large/                  # Many objects
│   │
│   ├── index/                           # Staging area scenarios
│   │   ├── index-basic/
│   │   ├── index-conflict-stages/       # Stage 1, 2, 3 entries
│   │   ├── index-assume-valid/
│   │   └── index-unicode-paths/
│   │
│   ├── refs/                            # Reference scenarios
│   │   ├── refs-loose/
│   │   ├── refs-packed/
│   │   ├── refs-symbolic/               # HEAD -> refs/heads/main
│   │   └── refs-tags/                   # Lightweight + annotated
│   │
│   ├── diff/                            # Diff scenarios
│   │   ├── diff-add-lines/
│   │   ├── diff-delete-lines/
│   │   ├── diff-modify-lines/
│   │   ├── diff-rename/
│   │   ├── diff-binary/
│   │   ├── diff-empty-file/
│   │   ├── diff-no-newline/             # No trailing newline
│   │   └── diff-multiple-hunks/
│   │
│   ├── merge/                           # Merge scenarios
│   │   ├── merge-clean/
│   │   ├── merge-conflict/
│   │   ├── merge-both-added/
│   │   ├── merge-both-deleted/
│   │   ├── merge-rename-conflict/
│   │   └── merge-criss-cross/           # Multiple merge bases
│   │
│   ├── status/                          # Status scenarios
│   │   ├── status-clean/
│   │   ├── status-staged/
│   │   ├── status-modified/
│   │   ├── status-untracked/
│   │   ├── status-deleted/
│   │   └── status-mixed/
│   │
│   ├── log/                             # Commit walk scenarios
│   │   ├── log-linear/
│   │   ├── log-branched/
│   │   ├── log-merge-commits/
│   │   └── log-limit/
│   │
│   ├── protocol/                        # Network scenarios
│   │   ├── clone-basic/
│   │   ├── fetch-incremental/
│   │   └── push-basic/
│   │
│   └── upstream/                        # Fixtures from other implementations
│       ├── libgit2/
│       ├── go-git/
│       └── gitoxide/
│
├── fixtures/
│   ├── repos/                           # Pre-built test repositories
│   ├── packs/                           # Isolated pack files with known contents
│   ├── objects/                         # Individual loose objects
│   └── malformed/                       # Invalid inputs (must error, not crash)
│
├── composer.json
├── phpunit.xml.dist
├── phpcs.xml
└── CLAUDE.md
```

## Public API

### Facade

```php
Pitmaster::open(string $path): Repository;           // Open existing repo
Pitmaster::init(string $path): Repository;            // Init new repo
Pitmaster::clone(string $url, string $path): Repository; // Clone remote
```

### Repository

```php
$repo = Pitmaster::open('/path/to/project');

// Objects
$repo->readObject(string $hash): GitObject;           // Read any object by hash
$repo->writeObject(GitObject $object): ObjectId;      // Write object, returns hash
$repo->catFile(string $hash): string;                 // Raw content (like git cat-file -p)

// Refs
$repo->head(): Commit;                                // Current HEAD commit
$repo->branch(?string $name = null): string;          // Current branch name, or resolve
$repo->branches(): array;                             // List all branches
$repo->tags(): array;                                 // List all tags
$repo->resolve(string $revision): ObjectId;           // Resolve revision expression
$repo->updateRef(string $name, ObjectId $target): void;
$repo->createBranch(string $name, ?ObjectId $from = null): void;
$repo->deleteBranch(string $name): void;

// Index
$repo->index(): Index;                                // Read current index
$repo->add(string ...$paths): void;                   // Stage files
$repo->remove(string ...$paths): void;                // Remove tracked paths (supports --cached / -r)

// Commits
$repo->commit(string $message, ?Author $author = null): ObjectId;
$repo->log(int $limit = 50, ?ObjectId $from = null): array;  // Commit[]

// Diff
$repo->diff(?string $pathspec = null): DiffResult;     // Worktree vs index
$repo->diffStaged(?string $pathspec = null): DiffResult; // Index vs HEAD
$repo->diffTree(ObjectId $a, ObjectId $b): DiffResult; // Tree vs tree

// Status
$repo->status(): array;                               // StatusEntry[]

// Merge
$repo->merge(string $branch): MergeResult;
$repo->mergeBase(ObjectId $a, ObjectId $b): ObjectId;

// Network
$repo->fetch(string $remote = 'origin'): void;
$repo->push(string $remote = 'origin', string $branch = null): void;
```

## Configuration

| Constant | Default | Description |
|---|---|---|
| `PITMASTER_HASH_ALGO` | `sha1` | Object hash algorithm: `sha1` or `sha256` |
| `PITMASTER_MAX_DELTA_CHAIN` | `50` | Max delta chain depth before giving up |
| `PITMASTER_MAX_PACK_MEMORY` | `256M` | Memory limit for pack file operations |
| `PITMASTER_HTTP_TIMEOUT` | `30` | Timeout in seconds for HTTP protocol operations |
| `PITMASTER_AUTHOR_NAME` | system user | Default author name for commits |
| `PITMASTER_AUTHOR_EMAIL` | system email | Default author email for commits |

## Key Rules

1. Pure PHP. No extensions beyond what ships with every PHP install (`sha1`, `hash`, `zlib_encode`, `zlib_decode`, `pack`, `unpack`). No FFI. No `exec()`. No `proc_open()`. No shelling out to the `git` binary at runtime. The entire point is to eliminate the `git` dependency.
2. Canonical `git` is the oracle, not the spec. Git's technical documentation describes the formats, but the reference C implementation is the authority. When the docs are ambiguous, test against what `git` actually does.
3. Objects are immutable. Once an `ObjectId` is computed, the content behind it never changes. Use readonly classes for `Blob`, `Tree`, `Commit`, `Tag`, `TreeEntry`, `IndexEntry`, `StatusEntry`.
4. SHA-1 first, SHA-256 later. SHA-1 is what every existing repository uses. SHA-256 support is real but deferred to post-v1. The hash algorithm must be abstractable from day one (use `ObjectId` everywhere, never raw strings).
5. Pack file reading is essential. Pack file writing is optimization. Almost every real repository has pack files. You cannot read a cloned repo without parsing packs. But writing packs can be deferred: write loose objects and let canonical `git gc` repack.
6. Delta resolution must handle chains. A deltified object's base may itself be deltified. OFS_DELTA (offset-based) is more common than REF_DELTA (hash-based). The resolver must follow chains up to `PITMASTER_MAX_DELTA_CHAIN` depth.
7. The index file is the staging area. Reading it is required for `status`. Writing it is required for `add` and `commit`. Support v2 format first (most common), then v3 (extended flags), then v4 (path prefix compression).
8. Integer encoding matters. Pack files and protocol use multiple variable-length integer encodings: LEB128 (unsigned and signed), Git's own varint (MSB-continue, used in pack type/size headers), and pkt-line length (4-char hex). Get these right or everything downstream breaks.
9. The smart HTTP protocol is the only network transport for v1. SSH requires `proc_open()` which defeats the purpose. The `git://` protocol is unencrypted and dying. Smart HTTP over HTTPS is the practical choice. Support protocol v2 (simpler, single round-trip).
10. PHP 8.2+. Use readonly classes for value objects. Use enums for `ObjectType`, `FileStatus`, `DeltaType`. Use match expressions for exhaustive branching. Use constructor promotion. Use named arguments in builders.
11. Diffs use the Myers algorithm by default. Patience diff is available as an alternative. Both must produce output identical to `git diff` on the same inputs (verified by interop tests).
12. Three-way merge operates on blob content, not files. The merge algorithm takes three strings (base, ours, theirs) and produces a merged string plus conflict markers. File-level merge (which blobs to merge) is a separate concern handled by `TreeDiff`.
13. Error handling uses typed exceptions, not return codes. `ObjectNotFoundException`, `CorruptObjectException`, `PackParseException`, etc. A corrupt pack file must never silently produce wrong data.

## Git Binary Formats Reference

### Loose Object Format

```
Header:  <type> <size>\0       (ASCII type, ASCII decimal size, null byte)
Content: <raw bytes>           (type-specific content)
Storage: zlib_encode(header + content)
Path:    .git/objects/<first 2 hex chars of hash>/<remaining 38 hex chars>
Hash:    sha1(header + content)   (computed over uncompressed data)
```

### Tree Entry Format (inside tree object content)

```
<mode> <name>\0<20-byte binary hash>
```

- Mode: `100644` (file), `100755` (executable), `040000` (directory), `120000` (symlink), `160000` (gitlink)
- Entries sorted by name (with directory names treated as if they end with `/`)
- Hash: raw 20-byte binary (not hex)

### Commit Object Format (inside commit object content)

```
tree <40-char hex hash>\n
parent <40-char hex hash>\n          (zero or more parent lines)
author <name> <<email>> <unix-timestamp> <timezone>\n
committer <name> <<email>> <unix-timestamp> <timezone>\n
\n
<commit message>
```

### Pack File Format

```
Header:  PACK (4 bytes) + version (4 bytes, big-endian) + object count (4 bytes, big-endian)
Entries: [type+size varint] [delta-base info if delta] [zlib-compressed data]
Trailer: 20-byte SHA-1 of all preceding bytes
```

Type encoding in first varint byte: `1TTTSSSS` where TTT = type (1-4 for base objects, 6 for OFS_DELTA, 7 for REF_DELTA), SSSS = first 4 bits of uncompressed size. If MSB is 1, continue reading more size bytes.

### Delta Instruction Format

```
Copy:   1XXXXXXX [offset bytes 0-4] [size bytes 0-3]   (copy from base)
Insert: 0LLLLLLL <L bytes of literal data>               (insert new data, L=1-127)
```

Offset and size bytes are present based on which X bits are set. Zero-size copy means 0x10000 bytes.

### Pack Index v2 Format

```
Magic:   FF 74 4F 63   (0xFF, 't', 'O', 'c')
Version: 00 00 00 02
Fanout:  256 x 4-byte big-endian counts (cumulative)
Names:   N x 20-byte SHA-1 hashes (sorted)
CRC32s:  N x 4-byte CRC32 of packed data
Offsets: N x 4-byte offsets (MSB=1 means index into 8-byte table)
Large:   M x 8-byte offsets (only for packs > 2GB)
Pack checksum: 20 bytes
Index checksum: 20 bytes
```

### Index File Format (v2)

```
Header:  DIRC (4 bytes) + version (4 bytes) + entry count (4 bytes)
Entries: [ctime 8B] [mtime 8B] [dev 4B] [ino 4B] [mode 4B] [uid 4B] [gid 4B]
         [size 4B] [SHA-1 20B] [flags 2B] [path NUL-terminated] [padding to 8-byte align]
Extensions: [signature 4B] [size 4B] [data]
Checksum: 20-byte SHA-1 of all preceding bytes
```

Flags: bit 15 = assume-valid, bits 13-12 = stage (0-3), bits 11-0 = path length (capped at 0xFFF).

### Pkt-Line Format (protocol)

```
<4 hex digits = total line length including these 4 bytes><payload>
0000 = flush packet (end of section)
0001 = delimiter packet (protocol v2)
```

Length includes the 4 length bytes. Minimum data line is `0005` (1 byte payload). Maximum is `FFFF` (65531 bytes payload).

---

## Oracle Model

This is the most important section. Pitmaster uses the same oracle-driven verification model as php-browser (sibling project in this repo). The principle is identical:

**Chromium is to php-browser as canonical `git` is to Pitmaster.**

In php-browser, Playwright captures Chromium's rendering as the oracle, the PHP renderer produces the actual output, and the diff layer measures the gap. Pitmaster follows the same loop with `git` as the oracle:

```
1. SETUP    → a script creates a repo state (files, branches, history)
2. ORACLE   → canonical git performs operations, output is captured as truth
3. ACTUAL   → Pitmaster performs the same operations, output is captured
4. COMPARE  → oracle vs actual, diff measures the gap
```

Every behavior Pitmaster implements is proven by comparing its output against git's output on the same input. We never hardcode expected values in tests. We never assert against hand-written fixtures. The oracle is always git itself, captured fresh.

### Relationship to php-browser

The test infrastructure mirrors php-browser's fixture system:

| Concept | php-browser | Pitmaster |
|---|---|---|
| Oracle | Chromium via Playwright | Canonical `git` binary |
| Actual | PHP renderer (ImageMagick) | Pitmaster (pure PHP) |
| Fixture/Scenario | `fixtures/<name>/` | `scenarios/<category>/<name>/` |
| Oracle output | `oracle/` (screenshot + layout JSON) | `oracle/` (git command outputs) |
| Actual output | `actual/` (rendered PNG + layout) | `actual/` (Pitmaster outputs) |
| Comparison | `ImageComparator` + `LayoutComparator` | `ScenarioComparator` |
| Pipeline | `./bin/oracle` → `./bin/render` → `./bin/compare` | `./bin/oracle` → `./bin/actual` → `./bin/compare` |
| Combined | `./bin/test-fixture <name>` | `./bin/test-scenario <name>` |
| Regression | `./bin/test-regression --jobs N` | `./bin/test-regression --jobs N` |
| Metadata | `fixture.json` | `scenario.json` |
| Report | `reports/` per fixture | `reports/` per scenario |

The CLI surface, directory layout, and runner semantics are intentionally aligned. If you know how to work with php-browser's fixtures, you know how to work with pitmaster's scenarios. Study `php-browser/bin/` and `php-browser/src/Fixture/` for the reference implementation of this pattern.

### Scenario Structure

Each test scenario lives in its own directory under `scenarios/`:

```
scenarios/diff/diff-add-lines/
├── scenario.json                 # Metadata: name, category, operations to test
├── setup.sh                      # Creates repo state (runs git commands)
├── oracle/                       # Captured git output (generated, not hand-written)
│   ├── objects.json              # git cat-file --batch output for all objects
│   ├── refs.json                 # git show-ref output
│   ├── log.json                  # git log --format=raw output
│   ├── diff.txt                  # git diff output
│   ├── status.txt                # git status --porcelain output
│   ├── index.json                # git ls-files --stage --debug output
│   ├── fsck.txt                  # git fsck --strict output
│   └── verify-pack.txt           # git verify-pack -v output (if pack scenarios)
├── actual/                       # Pitmaster output (generated)
│   ├── objects.json              # Pitmaster object reads
│   ├── refs.json                 # Pitmaster ref resolution
│   ├── log.json                  # Pitmaster commit walk
│   ├── diff.txt                  # Pitmaster diff output
│   ├── status.txt                # Pitmaster status output
│   └── index.json                # Pitmaster index reads
└── reports/
    └── comparison.json           # Diff results: pass/fail per output, mismatches
```

### scenario.json

```json
{
    "name": "diff-add-lines",
    "category": "diff",
    "description": "Diff after adding lines to an existing file",
    "operations": ["diff", "status"],
    "oracle_commands": {
        "diff": "git diff",
        "status": "git status --porcelain"
    },
    "expectations": {
        "exact_match": ["diff", "status"],
        "fsck_clean": true
    }
}
```

### Pipeline Commands

```bash
# Single scenario
./bin/oracle diff-add-lines              # Capture git output → oracle/
./bin/actual diff-add-lines              # Run Pitmaster → actual/
./bin/compare diff-add-lines             # Diff oracle/ vs actual/ → reports/
./bin/test-scenario diff-add-lines       # All three in sequence

# Refresh oracle (re-capture after git version update)
./bin/oracle --refresh diff-add-lines

# All scenarios
./bin/test-regression                    # Run all scenarios
./bin/test-regression --jobs 4           # Parallel
./bin/test-regression --category diff    # Only diff scenarios
./bin/test-regression --fast             # Skip report generation, just pass/fail
```

### Oracle Capture Details

The oracle step runs `setup.sh` in a temp directory, then captures git's output in structured format:

| Oracle output | Git command | What it captures |
|---|---|---|
| `objects.json` | `git cat-file --batch-all-objects --buffer` | Every object: hash, type, size, content |
| `refs.json` | `git for-each-ref --format='%(objectname) %(refname)'` | All refs with resolved hashes |
| `log.json` | `git log --all --format=raw` | Full commit graph with parents, trees, authors |
| `diff.txt` | `git diff` (or `git diff --cached`, `git diff A B`) | Unified diff output |
| `status.txt` | `git status --porcelain=v2` | Working tree + index state |
| `index.json` | `git ls-files --stage --debug` | All index entries with full metadata |
| `tree.json` | `git ls-tree -r <tree-hash>` | Recursive tree listing |
| `fsck.txt` | `git fsck --strict --no-progress` | Structural validation |
| `verify-pack.txt` | `git verify-pack -v *.pack` | Pack structure, delta chains, sizes |

Oracle output is committed to the repo. It is re-captured when:
- A scenario's `setup.sh` changes
- The git version used for testing is upgraded
- `--refresh` is passed to `./bin/oracle`

### Comparison Rules

The comparator supports different matching modes per output:

| Mode | Behavior | Used for |
|---|---|---|
| `exact` | Byte-for-byte identical | `diff.txt`, `status.txt`, `refs.json` |
| `semantic` | Parsed and compared structurally (ignoring whitespace, field order) | `objects.json`, `log.json`, `index.json` |
| `fsck_clean` | Oracle's fsck output must show no errors, and Pitmaster's written repo must also pass fsck | `fsck.txt` |
| `superset` | Pitmaster output must contain everything in oracle (may have more) | `tree.json` (recursive listings) |

### What Oracle Tests Prove

Oracle scenarios are the primary proof of correctness. They prove:

1. **Format compliance**: if Pitmaster writes objects and `git fsck --strict` passes on the oracle side, the binary format is correct.
2. **Read compliance**: if Pitmaster reads objects from a git-created repo and `actual/objects.json` matches `oracle/objects.json`, the parser is correct.
3. **Operation compliance**: if Pitmaster's diff/status/log output matches git's output on the same repo state, the algorithms are correct.
4. **Round-trip compliance**: if Pitmaster writes a commit and then git reads it (oracle capture on a Pitmaster-written repo), the write path is correct.

### Round-Trip Scenarios (the strongest proof)

Some scenarios test in both directions:

```
scenarios/roundtrip/commit-basic/
├── setup.sh                      # Pitmaster creates repo, adds files, commits
├── oracle/                       # git reads the Pitmaster-written repo
│   ├── fsck.txt                  # git fsck --strict (must be clean)
│   ├── log.json                  # git log (must see the commit)
│   ├── objects.json              # git cat-file (must read all objects)
│   └── show.txt                  # git show (must display commit correctly)
├── actual/                       # Pitmaster reads its own repo back
│   └── ...
└── reports/
    └── comparison.json

# And the reverse:
scenarios/roundtrip/commit-basic-reverse/
├── setup.sh                      # git creates repo, adds files, commits
├── oracle/                       # git reads its own repo (baseline)
├── actual/                       # Pitmaster reads the git-created repo
└── reports/
```

### Unit Tests (what oracle cannot test)

Oracle scenarios prove git compatibility. Unit tests prove internal algorithm correctness where oracle comparison is not meaningful:

```
tests/Unit/
├── Encoding/
│   ├── Leb128Test.php                # LEB128 edge cases (max values, signed overflow)
│   ├── VarIntTest.php                # Git varint encoding round-trips
│   └── BinaryReaderTest.php          # EOF handling, position tracking
├── Pack/
│   ├── DeltaApplierTest.php          # Copy/insert instruction application on known inputs
│   └── DeltaResolverTest.php         # Chain depth limits, circular chain detection
├── Diff/
│   ├── MyersDiffTest.php             # Empty inputs, single-line, identical files
│   └── PatienceDiffTest.php          # Unique-line matching, structural changes
├── Merge/
│   ├── ThreeWayMergeTest.php         # Clean merge, both-modified detection
│   └── MergeBaseTest.php             # Diamond graph, criss-cross, multiple bases
├── Graph/
│   ├── CommitWalkerTest.php          # Topological ordering on synthetic graphs
│   └── RevisionParserTest.php        # HEAD~3, main^2, tag@{1}
├── Protocol/
│   ├── PktLineTest.php               # Encode/decode, flush, delimiter, max length
│   └── CapabilityTest.php            # Capability string parsing
└── Malformed/
    ├── TruncatedPackTest.php         # PackParseException, not crash
    ├── CorruptObjectTest.php         # CorruptObjectException, not wrong data
    ├── CircularDeltaTest.php         # DeltaResolver gives up at max depth
    └── InvalidHeaderTest.php         # Bad magic bytes, wrong version
```

### Upstream Fixture Tests (via oracle)

Upstream fixtures from libgit2, go-git, and gitoxide are tested through the oracle model too. The setup script unpacks the fixture, oracle captures git's view of it, Pitmaster reads it, and the comparator checks agreement.

```bash
# Acquire upstream fixtures
./bin/acquire-fixtures                    # Downloads and unpacks into scenarios/upstream/

# Test them
./bin/test-regression --category upstream
```

Acquisition sources:

```bash
# libgit2 fixtures (rename .gitted to .git)
git clone --depth=1 https://github.com/libgit2/libgit2.git /tmp/libgit2
cp -r /tmp/libgit2/tests/resources/testrepo.git scenarios/upstream/libgit2/testrepo.git

# go-git fixtures (tarballed .git directories)
git clone --depth=1 https://github.com/go-git/go-git-fixtures.git /tmp/go-git-fixtures
# Extract .tgz fixtures into scenarios/upstream/go-git/

# Generate from git's test suite
cd /tmp && git clone --depth=1 https://github.com/git/git.git
cd git/t && bash t1000-read-tree-m-3way.sh --debug
# Copy resulting test repos into scenarios/upstream/git-suite/
```

### Compliance Report

`bin/verify-compliance` aggregates all scenario results into a single report:

```
Pitmaster Compliance Report
===========================
Oracle: git version 2.45.0

Object Scenarios:          14/14 passed
  blob-simple              PASS  (exact: objects, fsck clean)
  blob-binary              PASS  (exact: objects, fsck clean)
  blob-empty               PASS  (exact: objects, fsck clean)
  tree-flat                PASS  (exact: objects, tree listing)
  tree-nested              PASS  (exact: objects, tree listing)
  commit-root              PASS  (exact: objects, log, fsck clean)
  commit-merge             PASS  (exact: objects, log, fsck clean)
  ...

Pack Scenarios:            6/6 passed
  pack-undeltified         PASS  (all objects extracted, content matches)
  pack-ofs-delta           PASS  (delta chains resolved, content matches)
  pack-deep-chain          PASS  (12-deep chain, content matches)
  ...

Diff Scenarios:            8/8 passed
  diff-add-lines           PASS  (exact: diff output)
  diff-delete-lines        PASS  (exact: diff output)
  diff-rename              PASS  (exact: diff output)
  ...

Merge Scenarios:           6/6 passed
  merge-clean              PASS  (exact: merged content)
  merge-conflict           PASS  (exact: conflict markers)
  ...

Status Scenarios:          6/6 passed
  status-clean             PASS  (exact: porcelain output)
  status-mixed             PASS  (exact: porcelain output)
  ...

Round-Trip Scenarios:      4/4 passed
  commit-basic             PASS  (Pitmaster writes → git reads → fsck clean, log matches)
  commit-basic-reverse     PASS  (git writes → Pitmaster reads → output matches)
  ...

Upstream Fixtures:         8/8 passed
  libgit2/testrepo         PASS  (all objects, all refs)
  go-git/basic             PASS  (pack extraction, all objects)
  ...

Malformed Inputs:          4/4 passed
  truncated-pack           PASS  (PackParseException thrown)
  corrupt-object           PASS  (CorruptObjectException thrown)
  ...

Total: 56/56 scenarios passed
```

## Implementation Order

Build bottom-up. Each phase unlocks new scenario categories. Write the scenarios first, capture the oracle, then implement until actual matches oracle.

### Phase 1: Read a repository (prove we understand the formats)

1. `ObjectId` + `ObjectType` enum + `BinaryReader`
2. `LooseObjectStore`: read loose objects (zlib_decode, parse header, return typed object)
3. `Blob`, `Tree`, `Commit`, `Tag` parsing
4. `PackIndex` v2 reader (fanout, binary search, offset lookup)
5. `PackFile` reader (header, entry parsing, zlib decompression)
6. `DeltaApplier` (copy/insert instructions)
7. `DeltaResolver` (chain following, OFS_DELTA offset calculation)
8. `ObjectDatabase` (composite: try loose first, then packs)
9. `LooseRefStore` + `PackedRefStore` + `RefDatabase`
10. `Repository` with read-only operations: `readObject`, `head`, `branches`, `log`

**Oracle gate:** `./bin/test-regression --category objects --category packs --category refs --category log` all green. Pitmaster reads git-created repos and produces identical object/ref/log output.

### Phase 2: Write to a repository (prove git accepts our output)

11. `ObjectSerializer` (encode type/size/content, zlib_encode, compute hash, write to loose)
12. `IndexParser` + `IndexWriter` (read/write .git/index)
13. `WorkingTreeStatus` (compare HEAD tree vs index vs worktree)
14. `add()`, `remove()` (update index entries)
15. `commit()` (build tree from index, create commit object, update HEAD)

**Oracle gate:** `./bin/test-regression --category roundtrip --category index --category status` all green. Pitmaster writes repos that pass `git fsck --strict`. Git reads Pitmaster-written commits and produces matching log/show output.

### Phase 3: Diff and merge (prove our algorithms match git's)

16. `MyersDiff` (line-level diff)
17. `TreeDiff` (recursive tree comparison)
18. `DiffResult` + `Hunk` formatting (unified diff output)
19. `MergeBase` (LCA algorithm on commit graph)
20. `ThreeWayMerge` (content-level merge with conflict markers)

**Oracle gate:** `./bin/test-regression --category diff --category merge` all green. Pitmaster diff output matches `git diff` byte-for-byte on every scenario. Merge results match `git merge` output.

### Phase 4: Network (prove we can talk to real git servers)

21. `PktLine` encoder/decoder
22. `RefDiscovery` (parse ref advertisement)
23. `UploadPackClient` (want/have negotiation, receive pack data)
24. `ReceivePackClient` (send pack + ref update commands)
25. `SmartHttpClient` (HTTP transport layer)

**Oracle gate:** `./bin/test-regression --category protocol` all green. Pitmaster clones and the resulting repo's object/ref output matches oracle. Push scenarios verified by git reading the pushed repo.

### Phase 5: Harden (prove we handle the weird stuff)

26. Malformed input handling (truncated packs, corrupt objects, circular deltas)
27. Upstream fixture scenarios (libgit2, go-git, gitoxide fixtures via oracle model)
28. Large file handling (objects > available memory)
29. Binary file diff (detect and skip)
30. Edge cases: empty tree, root commit (no parents), octopus merge, UTF-8 paths

**Oracle gate:** `./bin/verify-compliance` full report green. All scenario categories pass. All upstream fixtures readable. All malformed inputs rejected cleanly.

## Comment Policy

Same as queuety. PHPDoc on public APIs. Inline comments explain why, not what. No decorative separators. No em dashes. Use periods, commas, colons, or rewrite.
