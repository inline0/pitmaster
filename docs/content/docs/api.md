---
title: "API"
description: "The Pitmaster\\Pitmaster facade and Repository class provide the public PHP API."
path: "api"
order: 2
section: "Documentation"
meta_title: "API"
meta_description: "The Pitmaster\\Pitmaster facade and Repository class provide the public PHP API."
---

# PHP API

## `Pitmaster\Pitmaster`

The `Pitmaster\Pitmaster` class is the static entry point. It provides factory methods for opening, creating, and cloning repositories, plus detection helpers.

```php
use Pitmaster\Pitmaster;
```

### `open`

```php
public static function open(string $path): Repository
```

Open an existing repository at `$path`. The path should be the working tree root (containing `.git/`). Also supports linked worktrees (where `.git` is a file containing `gitdir: <path>`), and bare repositories (where `$path` is the `.git` directory itself).

Throws `InvalidArgumentException` if the path is not a git repository.

```php
$repo = Pitmaster::open('/home/user/project');
```

### `init`

```php
public static function init(string $path): Repository
```

Initialize a new repository at `$path`. Creates the `.git` directory structure with `objects/`, `refs/heads/`, `refs/tags/`, `HEAD` (pointing to `refs/heads/main`), and a default `config`.

Throws `RuntimeException` if a repository already exists at the path.

```php
$repo = Pitmaster::init('/home/user/new-project');
```

### `clone`

```php
public static function clone(string $url, string $path): Repository
```

Clone a remote repository via smart HTTP. Initializes a new repo, discovers remote refs, fetches the pack file, sets up remote tracking branches, and materializes the working tree.

```php
$repo = Pitmaster::clone('https://github.com/user/repo.git', '/tmp/repo');
```

### `isRepository`

```php
public static function isRepository(string $path): bool
```

Check if a path is a git repository. Returns `true` for regular repos (`.git/` directory), linked worktrees (`.git` file), and bare repos (`HEAD` file present).

```php
if (Pitmaster::isRepository('/path/to/check')) {
    // It's a git repo
}
```

### `isWorktree`

```php
public static function isWorktree(string $path): bool
```

Check if a path is a linked worktree (not the main repo). Returns `true` only if `.git` is a file containing a `gitdir:` reference.

```php
if (Pitmaster::isWorktree('/path/to/worktree')) {
    // It's a linked worktree
}
```

### `commonGitDir`

```php
public static function commonGitDir(string $path): ?string
```

Resolve the common git directory from any checkout path. For regular repos, this is the `.git` directory. For linked worktrees, this is the shared git directory that contains objects, config, and packed-refs. Returns `null` if the path is not a repository.

```php
$common = Pitmaster::commonGitDir('/path/to/worktree');
// '/path/to/main-repo/.git'
```

---

## `Pitmaster\Repository`

The `Pitmaster\Repository` class is the main handle for all git operations. Obtain one via `Pitmaster::open()`, `Pitmaster::init()`, or `Pitmaster::clone()`.

```php
use Pitmaster\Repository;
```

### Path accessors

#### `gitDir`

```php
public function gitDir(): string
```

Returns the per-worktree git directory. For regular repos this is `.git/`. For linked worktrees, this is `.git/worktrees/<name>/`.

#### `commonGitDir`

```php
public function commonGitDir(): string
```

Returns the shared git directory. Same as `gitDir()` for regular repos. For linked worktrees, this is the main repo's `.git/` directory (where objects, config, and packed-refs live).

#### `workDir`

```php
public function workDir(): string
```

Returns the working tree root path.

#### `isLinkedWorktree`

```php
public function isLinkedWorktree(): bool
```

Returns `true` if this repository handle is a linked worktree.

---

### Objects

#### `readObject`

```php
public function readObject(string $hash): GitObject
```

Read any object by its full 40-character hex hash. Returns a `Blob`, `Tree`, `Commit`, or `Tag` instance. Searches loose objects first, then pack files.

Throws `ObjectNotFoundException` if the hash is not found.

```php
$object = $repo->readObject('a1b2c3d4e5f6...');
```

#### `writeObject`

```php
public function writeObject(GitObject $object): ObjectId
```

Write an object to the loose object store. Returns the computed `ObjectId`.

```php
use Pitmaster\Object\Blob;

$blob = Blob::fromContent('Hello, world!');
$id = $repo->writeObject($blob);
```

#### `catFile`

```php
public function catFile(string $hash): string
```

Return the raw content of an object, equivalent to `git cat-file -p`.

```php
echo $repo->catFile('a1b2c3d4...');
```

#### `objectExists`

```php
public function objectExists(string $hash): bool
```

Check if an object exists in the repository (loose or packed).

#### `listObjects`

```php
public function listObjects(): array
```

List all object hashes in the repository. Returns an array of hex strings.

---

### Refs

#### `head`

```php
public function head(): Commit
```

Returns the current HEAD commit. Follows symbolic refs (HEAD -> refs/heads/main -> commit hash).

Throws `RuntimeException` if HEAD does not point to a valid commit (e.g., empty repository).

#### `branch`

```php
public function branch(?string $name = null): ?string
```

Without arguments, returns the current branch name (e.g., `'main'`), or `null` if HEAD is detached.

With a branch name, resolves it to its commit hash (hex string) or returns `null` if the branch does not exist.

```php
$current = $repo->branch();        // 'main'
$hash = $repo->branch('feature');   // 'abc123...' or null
```

#### `branches`

```php
public function branches(): array
```

Returns a sorted array of all branch names (without the `refs/heads/` prefix).

```php
$branches = $repo->branches();
// ['bugfix/typo', 'feature/login', 'main']
```

#### `tags`

```php
public function tags(): array
```

Returns a sorted array of all tag names (without the `refs/tags/` prefix).

#### `resolve`

```php
public function resolve(string $revision): ObjectId
```

Resolve a revision expression to an `ObjectId`. Supports:
- Full 40-character hex hashes
- `HEAD`
- Branch names (`main`, `feature/login`)
- Tag names (`v1.0.0`)
- Full ref paths (`refs/heads/main`)
- Revision expressions (`HEAD~3`, `main^2`)

Throws `RuntimeException` if the revision cannot be resolved.

```php
$id = $repo->resolve('HEAD~3');
$id = $repo->resolve('v1.0.0');
$id = $repo->resolve('feature/login');
```

#### `allRefs`

```php
public function allRefs(): array
```

Returns all refs as an associative array of ref name to hex hash.

```php
$refs = $repo->allRefs();
// ['refs/heads/main' => 'abc123...', 'refs/tags/v1.0.0' => 'def456...']
```

#### `updateRef`

```php
public function updateRef(string $name, ObjectId $target): void
```

Update a ref to point to a new target.

```php
$repo->updateRef('refs/heads/main', $commitId);
```

#### `createBranch`

```php
public function createBranch(string $name, ?ObjectId $from = null): void
```

Create a new branch. If `$from` is null, branches from the current HEAD.

```php
$repo->createBranch('feature/new-thing');
$repo->createBranch('hotfix', $repo->resolve('v1.0.0'));
```

#### `deleteBranch`

```php
public function deleteBranch(string $name): void
```

Delete a branch by name.

```php
$repo->deleteBranch('feature/old');
```

#### `createLightweightTag`

```php
public function createLightweightTag(string $name, ?ObjectId $target = null): void
```

Create a lightweight tag. If `$target` is null, tags the current HEAD directly.

```php
$repo->createLightweightTag('v2.0.0');
$repo->createLightweightTag('hotfix-start', $repo->resolve('main~2'));
```

#### `createTag`

```php
public function createTag(string $name, string $message, ?ObjectId $target = null, ?string $tagger = null): ObjectId
```

Create an annotated tag. If `$target` is null, tags the current HEAD. Returns the tag object's `ObjectId`.

```php
$tagId = $repo->createTag('v2.0.0', 'Release version 2.0.0');
```

#### `deleteTag`

```php
public function deleteTag(string $name): void
```

Delete a tag by name.

```php
$repo->deleteTag('v1.0.0');
```

#### `packRefs`

```php
public function packRefs(): void
```

Rewrite the shared `packed-refs` file from the current loose ref set and prune the corresponding shared loose refs. For linked worktrees, this writes to the common git directory.

```php
$repo->packRefs();
```

#### `defaultBranch`

```php
public function defaultBranch(): string
```

Resolve the repository's default branch. Checks the remote HEAD symref first, then the local HEAD, then falls back to `main` or `master`.

#### `isBranchMerged`

```php
public function isBranchMerged(string $branch, ?string $target = null): bool
```

Check if a branch is fully merged into another branch (defaults to the default branch).

```php
if ($repo->isBranchMerged('feature/done')) {
    $repo->deleteBranch('feature/done');
}
```

#### `checkout`

```php
public function checkout(string $target): void
```

Switch branches or detach HEAD. Updates HEAD, the index, and the working tree.

```php
$repo->checkout('feature/login');
$repo->checkout('abc123def456...');  // Detached HEAD
```

---

### Index

#### `index`

```php
public function index(): Index
```

Read and return the current index (staging area) from `.git/index`.

#### `add`

```php
public function add(string ...$paths): void
```

Stage files. Reads each file from the working tree, creates a blob object, and updates the index.

```php
$repo->add('src/Feature.php', 'tests/FeatureTest.php');
```

#### `remove`

```php
public function remove(string ...$paths): void
public function removeCached(string ...$paths): void
```

Remove tracked paths from both the index and the working tree. Supports `--cached` and `-r` / `--recursive` in the argument list for Git-shaped behavior.

```php
$repo->remove('old-file.txt');
$repo->remove('-r', 'src');
$repo->remove('--cached', 'old-file.txt');
$repo->removeCached('old-file.txt');
```

#### `mv`

```php
public function mv(string $source, string $destination): void
```

Move/rename a file in both the working tree and the index.

```php
$repo->mv('old-name.php', 'new-name.php');
```

---

### Commits

#### `commit`

```php
public function commit(string $message, ?string $author = null): ObjectId
```

Create a commit from the current index. Builds a tree hierarchy from the index entries, creates the commit object with the HEAD as parent (if any), and updates HEAD. Returns the new commit's `ObjectId`.

The `$author` parameter is a full git author string (`Name <email> timestamp timezone`). If null, it is derived from `.git/config` or the `PITMASTER_AUTHOR_NAME`/`PITMASTER_AUTHOR_EMAIL` constants.

```php
$id = $repo->commit('Fix null pointer bug');
```

#### `show`

```php
public function show(string $revision): array
```

Show a commit-ish and its diff against the first parent. When the revision resolves to an annotated tag, the return value also includes `['tag' => Tag]` and `commit` is the peeled target commit.

```php
$result = $repo->show('HEAD');
echo $result['commit']->message;

foreach ($result['diff'] as $diff) {
    echo $diff->format();
}
```

#### `reset`

```php
public function reset(string $revision, string $mode = 'mixed'): void
```

Reset HEAD to a commit. Modes:
- `'soft'`: move HEAD only
- `'mixed'`: move HEAD + reset index (default)
- `'hard'`: move HEAD + reset index + reset working tree

```php
$repo->reset('HEAD~1', 'soft');
$repo->reset('main', 'hard');
```

#### `restore`

```php
public function restore(string $path, ?string $source = null): void
```

Restore a path from the index or a specific source tree. By default Pitmaster restores the worktree from the index. Pass `staged: true` to restore the index, and `worktree: true` to restore both.

```php
$repo->restore('src/File.php');                                        // Worktree from index
$repo->restore('src/File.php', staged: true);                          // Index from HEAD
$repo->restore('src/File.php', 'HEAD~1');                              // Worktree from another commit
$repo->restore('src/File.php', 'HEAD~1', staged: true, worktree: true); // Index + worktree from another commit
```

#### `cherryPick`

```php
public function cherryPick(string $revision): ObjectId
public function cherryPickContinue(): ObjectId
public function cherryPickAbort(): void
```

Apply a commit as a new commit on the current branch, preserving the original author. If conflicts stop the operation, resolve them, stage the paths, then call `cherryPickContinue()` or `cherryPickAbort()`.

```php
$newId = $repo->cherryPick('abc123');

// After resolving conflicts:
$repo->add('src/File.php');
$repo->cherryPickContinue();
```

#### `revert`

```php
public function revert(string $revision): ObjectId
public function revertContinue(): ObjectId
public function revertAbort(): void
```

Create a commit that undoes another commit. If conflicts stop the revert, resolve them, stage the paths, then call `revertContinue()` or `revertAbort()`.

```php
$revertId = $repo->revert('abc123');

// After resolving conflicts:
$repo->add('src/File.php');
$repo->revertContinue();
```

#### `rebase`

```php
public function rebase(string $onto): array
public function rebaseContinue(): array
public function rebaseAbort(): void
public function rebaseSkip(): array
```

Rebase the current branch onto another revision. Pitmaster currently covers linear rebases with Git-shaped stop state for conflicts, plus `--continue`, `--abort`, and `--skip`.

```php
$result = $repo->rebase('main');

if (!$result['success']) {
    // Resolve conflicts, then continue or skip/abort.
    $repo->add('src/File.php');
    $repo->rebaseContinue();
}
```

---

### Status

#### `status`

```php
public function status(): array
```

Compute working tree status. Returns an array of `StatusEntry` objects, each containing:
- `path`: the file path
- `index`: `FileStatus` enum for HEAD-to-index changes
- `worktree`: `FileStatus` enum for index-to-worktree changes

```php
foreach ($repo->status() as $entry) {
    echo "{$entry->index->value}{$entry->worktree->value} {$entry->path}\n";
}
```

#### `statusPorcelainV2`

```php
public function statusPorcelainV2(): string
```

Machine-readable status output in git's porcelain v2 format.

```php
echo $repo->statusPorcelainV2();
// 1 M. N... 000000 000000 000000 0000...0000 0000...0000 modified-file.txt
// ? untracked-file.txt
```

---

### Diff

#### `diff`

```php
public function diff(?string $pathspec = null): array
```

Diff working tree against the index (unstaged changes). Returns an array of `DiffResult` objects. Optionally filter by a single path.

```php
$diffs = $repo->diff();

foreach ($diffs as $diff) {
    echo $diff->format();  // Unified diff output
}
```

#### `diffStaged`

```php
public function diffStaged(?string $pathspec = null): array
```

Diff index against HEAD (staged changes). Returns `DiffResult[]`.

```php
$staged = $repo->diffStaged();
```

#### `diffTree`

```php
public function diffTree(ObjectId $a, ObjectId $b): array
```

Diff two trees by their `ObjectId`. Returns `DiffResult[]`.

```php
$commit1 = $repo->resolve('HEAD~1');
$commit2 = $repo->resolve('HEAD');
$diffs = $repo->diffTree($commit1, $commit2);
```

---

### Log

#### `log`

```php
public function log(int $limit = 50, ?ObjectId $from = null): array
```

Walk commit history in topological order. Returns `Commit[]`. If `$from` is null, starts from HEAD.

```php
$commits = $repo->log(10);
```

#### `logPath`

```php
public function logPath(string $path, int $limit = 50): array
```

Log filtered by a file path. Returns only commits that touch the given file.

```php
$commits = $repo->logPath('src/Repository.php', 20);
```

---

### Merge

#### `merge`

```php
public function merge(string $branch): MergeResult
public function mergeContinue(): ObjectId
public function mergeAbort(): void
```

Merge a branch into HEAD. Detects fast-forward merges automatically. For non-fast-forward merges, performs tree-level conflict detection and creates a merge commit if clean. Conflict stops write Git-shaped merge state to the index and worktree; resolve the paths, stage them, then call `mergeContinue()` or `mergeAbort()`.

Returns a `MergeResult` with:
- `clean`: whether the merge succeeded without conflicts
- `commitId`: the merge commit ID (if clean)
- `conflictPaths`: array of conflicting file paths (if not clean)

```php
$result = $repo->merge('feature/login');

if ($result->clean) {
    echo "Merged: {$result->commitId->hex}\n";
} else {
    echo "Conflicts in: " . implode(', ', $result->conflictPaths) . "\n";
    $repo->add('src/File.php');
    $repo->mergeContinue();
}
```

#### `mergeBase`

```php
public function mergeBase(ObjectId $a, ObjectId $b): ?ObjectId
```

Find the merge base (lowest common ancestor) of two commits. Returns null if no common ancestor exists.

```php
$base = $repo->mergeBase(
    $repo->resolve('main'),
    $repo->resolve('feature/login'),
);
```

---

### Network

#### `fetch`

```php
public function fetch(string $remote = 'origin'): void
```

Fetch from a remote. Downloads new objects and updates remote tracking refs.

```php
$repo->fetch();
$repo->fetch('upstream');
```

#### `push`

```php
public function push(string $remote = 'origin', ?string $branch = null): void
public function pushForceWithLease(string $remote = 'origin', ?string $branch = null, ?ObjectId $expected = null): void
public function pushAtomic(string $remote = 'origin', array $branches = []): void
public function pushMirror(string $remote = 'origin'): void
```

Push the current branch (or a specified branch) to a remote. Force-with-lease, atomic, and mirror variants are available for stricter or broader ref updates.

```php
$repo->push();
$repo->push('origin', 'feature/login');
$repo->pushForceWithLease('origin', 'main', $repo->resolve('refs/remotes/origin/main'));
$repo->pushAtomic('origin', ['main', 'release']);
$repo->pushMirror();
```

---

### Worktrees

#### `addWorktree`

```php
public function addWorktree(string $path, string $branch, ?ObjectId $from = null, ?string $name = null): Worktree
```

Add a linked worktree. Creates the branch if it does not exist, sets up the worktree metadata, and materializes the working tree files. Pass `$name` when you need a deterministic metadata slug that differs from the checkout directory basename.

```php
$wt = $repo->addWorktree('/tmp/review', 'feature/review');
$wt = $repo->addWorktree('/tmp/a/divine-child', 'feature/review', name: 'app-theme');
```

#### `removeWorktree`

```php
public function removeWorktree(string $pathOrName, bool $force = false): void
```

Remove a linked worktree. Use `$force = true` to remove even if locked.

#### `worktrees`

```php
public function worktrees(): array
```

List all worktrees (main + linked). Returns `Worktree[]`.

```php
foreach ($repo->worktrees() as $wt) {
    echo "{$wt->path} [{$wt->branch}]\n";
}
```

---

### Config

#### `config`

```php
public function config(): GitConfig
```

Access the repository's git config. Read and write config values.

```php
$config = $repo->config();
$name = $config->get('user.name');
$config->set('user.email', 'new@example.com');
```

---

### Internal access

These methods expose internal components for advanced use cases.

#### `objectDatabase`

```php
public function objectDatabase(): ObjectDatabase
```

Access the raw object database (loose + pack stores).

#### `refDatabase`

```php
public function refDatabase(): RefDatabase
```

Access the raw ref database (loose + packed refs).
