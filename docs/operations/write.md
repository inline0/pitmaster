---
title: "Writing to Repositories"
description: "Initialize repos, stage files, create commits, manage branches and tags."
path: "operations/write"
order: 30
section: "Operations"
meta_title: "Writing to Repositories"
meta_description: "Initialize repos, stage files, create commits, manage branches and tags."
---

# Writing to Repositories

Pitmaster writes git repositories using the same binary formats as the canonical `git` binary. Objects are written to the loose object store (zlib-compressed), the index is written in v2 format (or v3 when entries need extended flags; an existing index keeps its version), and refs are written as loose files. Written data is verifiable with `git fsck --strict`.

## Initializing a repository

```php
use Pitmaster\Pitmaster;

$repo = Pitmaster::init('/path/to/new-project');
```

This creates:
- `.git/objects/` (empty object store)
- `.git/refs/heads/` and `.git/refs/tags/` (empty ref directories)
- `.git/HEAD` containing `ref: refs/heads/main`
- `.git/hooks/`, `.git/info/exclude`, and `.git/description`
- `.git/config` with default settings

Pass `'sha256'` as the second argument to create a SHA-256 repository: `Pitmaster::init($path, 'sha256')`.

The default config enables `core.logallrefupdates` for new repositories. Existing repositories keep their configured reflog behavior, including an explicit `false` setting.

## Staging files

### add

```php
$repo->add('src/Feature.php');
$repo->add('src/Feature.php', 'tests/FeatureTest.php', 'README.md');
```

Each argument is a file path relative to the working tree root. Directories and globs are not expanded, and a missing file throws `RuntimeException`. For each file, Pitmaster:
1. Reads the file content from the working tree
2. Creates a `Blob` object and computes its hash
3. Writes the blob to `.git/objects/` (zlib-compressed)
4. Updates the index entry for that path with the new hash and file metadata

### remove

```php
$repo->remove('old-file.txt');
$repo->remove('-r', 'src');
$repo->remove('--cached', 'old-file.txt');
```

Removes tracked paths from the index and working tree. Use `--cached` for index-only removal, or `-r` / `--recursive` for tracked directories.

### mv

```php
$repo->mv('old-name.php', 'new-name.php');
```

Renames the file in the working tree, removes the old index entry, and adds the new one.

## Creating commits

```php
$commitId = $repo->commit('Fix null pointer bug');
echo $commitId->hex;
```

The commit process:

1. **Build tree from index**: the flat list of index entries is organized into a tree hierarchy (directories become tree objects, files are blob references). Each tree is written to the object store.
2. **Determine parent**: the current HEAD commit becomes the parent. Root commits (first commit in a repo) have no parents.
3. **Determine author**: taken from the `PITMASTER_AUTHOR_*` and `GIT_AUTHOR_*` environment variables, then the `PITMASTER_AUTHOR_NAME`/`PITMASTER_AUTHOR_EMAIL` constants, then `.git/config` (`user.name`/`user.email`), then `Pitmaster <pitmaster@localhost>`. The committer is resolved the same way.
4. **Write commit object**: the commit content (`tree`, `parent`, `author`, `committer`, message) is serialized, hashed, and written.
5. **Update HEAD**: the branch ref that HEAD points to is updated to the new commit hash.

Hooks run around these steps (`pre-commit`, `prepare-commit-msg`, `commit-msg`, `post-commit`) unless the handle was opened with `['hooks' => false]`. `commit()` throws when the message is missing, when the tree is unchanged from HEAD, or when the index has unmerged paths.

### Custom author

```php
$author = 'Jane Smith <jane@example.com> 1700000000 +0000';
$commitId = $repo->commit('Add feature', $author);
```

## Verifying with git fsck

Pitmaster's integration coverage checks written objects against `git fsck --strict`, and you can verify the same manually:

```bash
cd /path/to/repo
git fsck --strict
```

If Pitmaster writes an invalid object, `git fsck` will report the error immediately.

## Managing branches

### Create a branch

```php
// Branch from HEAD
$repo->createBranch('feature/new-thing');

// Branch from a specific commit
$repo->createBranch('hotfix', $repo->resolve('v1.0.0'));
```

Creates a ref file at `.git/refs/heads/<name>` containing the target commit hash.

### Delete a branch

```php
$repo->deleteBranch('feature/old');
```

### Switch branches

```php
$repo->checkout('feature/login');
```

Updates HEAD to a symbolic ref pointing to the branch, resets the index to match the branch tip's tree, and updates the working tree.

Local changes in paths that differ between the two trees stop checkout before HEAD, the index or files are changed. Paths that are identical in both trees carry their staged and unstaged changes, including staged additions and deletions.

### Check if merged

```php
if ($repo->isBranchMerged('feature/done')) {
    $repo->deleteBranch('feature/done');
}
```

## Managing tags

### Lightweight tag

```php
$repo->createLightweightTag('v1.0.0');                          // At HEAD
$repo->createLightweightTag('v0.9.0', $repo->resolve('HEAD~5'));
```

A lightweight tag is just a ref pointing directly to a commit.

### Annotated tag

```php
$tagId = $repo->createTag('v2.0.0', 'Release version 2.0.0');
```

An annotated tag creates a tag object (with tagger, message, and target) and a ref pointing to it. The tagger is the current committer identity, resolved like the commit author.

### Custom tagger

```php
$tagId = $repo->createTag(
    'v2.0.0',
    'Release version 2.0.0',
    $repo->resolve('HEAD'),
    'Jane Smith <jane@example.com> 1700000000 +0000',
);
```

## Updating refs directly

```php
$repo->updateRef('refs/heads/main', $commitId);
$repo->updateRef('refs/tags/latest', $commitId);
```

## Writing objects directly

For advanced use cases, you can write objects without going through the high-level API.

### Write a blob

```php
use Pitmaster\Object\Blob;

$blob = Blob::fromContent('Hello, world!');
$id = $repo->writeObject($blob);
echo $id->hex;  // SHA-1 of "blob 13\0Hello, world!"
```

In a SHA-256 repository pass the algorithm: `Blob::fromContent('Hello, world!', 'sha256')`.

### Write a tree

```php
use Pitmaster\Object\Tree;
use Pitmaster\Object\TreeEntry;
use Pitmaster\Object\ObjectId;

$entries = [
    new TreeEntry('100644', 'README.md', $blobId),
    new TreeEntry('40000', 'src', $subtreeId),
];

$tree = Tree::fromEntries($entries);
$id = $repo->writeObject($tree);
```

`Tree::fromEntries()` writes the entries in the order given and the mode strings as given. Pass them in git's tree order (by name, with directories compared as if their name ended in `/`) and write directory modes as `40000`: git stores tree modes without the leading zero, and `git fsck --strict` rejects `040000`.

### Write a commit

```php
use Pitmaster\Object\Commit;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;

$content = Commit::buildContent(
    tree: $treeId,
    parents: [$parentId],
    author: 'Name <email> 1700000000 +0000',
    committer: 'Name <email> 1700000000 +0000',
    message: "Commit message\n",
);

$commitId = ObjectId::compute(ObjectType::Commit, $content);
$commit = Commit::parse($content, $commitId);
$repo->writeObject($commit);
```

## Reset

```php
// Soft: move HEAD only
$repo->reset('HEAD~1', 'soft');

// Mixed: move HEAD + reset index (default)
$repo->reset('HEAD~1');

// Hard: move HEAD + reset index + reset working tree
$repo->reset('HEAD~3', 'hard');
```

## Restore

```php
// Restore a file from the index into the worktree
$repo->restore('src/File.php');

// Restore the index version from HEAD (the worktree is left alone)
$repo->restore('src/File.php', staged: true);

// Restore from a specific commit
$repo->restore('src/File.php', 'HEAD~1');

// Restore both index and worktree from a specific commit
$repo->restore('src/File.php', 'HEAD~1', staged: true, worktree: true);
```

## Cherry-pick and revert

```php
// Apply a commit on the current branch
$newId = $repo->cherryPick('feature~1');

// Undo a commit
$revertId = $repo->revert('HEAD~2');
```

Both take any revision `resolve()` accepts. Full hashes work; abbreviated hashes do not.
