---
title: "CLI"
description: "Complete command reference for the Pitmaster CLI."
path: "cli"
order: 190
section: "Reference"
meta_title: "CLI"
meta_description: "Complete command reference for the Pitmaster CLI."
---

# CLI Reference

Pitmaster includes a CLI that mirrors a subset of git commands. It operates on the repository in the current working directory.

```bash
./bin/pitmaster <command> [args...]
```

## Commands

### `log`

Show commit history.

```bash
# Default: last 10 commits
./bin/pitmaster log

# Last 25 commits
./bin/pitmaster log 25
```

Output includes commit hash (yellow), author, merge parents (if any), and the commit message indented.

### `show`

Show commit details and diff against parent.

```bash
# Show HEAD
./bin/pitmaster show

# Show a specific commit
./bin/pitmaster show abc123
./bin/pitmaster show HEAD~3
./bin/pitmaster show v1.0.0
```

Displays the commit metadata followed by a colorized unified diff.

### `cat-file`

Print the raw content of a git object.

```bash
./bin/pitmaster cat-file a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2
```

Works with blobs, trees, commits, and tags. Equivalent to `git cat-file -p`.

### `status`

Show working tree status.

```bash
./bin/pitmaster status
```

Output:

```
On branch main

Changes to be committed:
    new file:   src/Feature.php

Changes not staged for commit:
    modified:   src/Existing.php

Untracked files:
    notes.txt
```

Staged files are shown in green, unstaged and untracked in red.

### `diff`

Show changes between the index and the working tree (unstaged changes).

```bash
# Unstaged changes
./bin/pitmaster diff

# Staged changes (index vs HEAD)
./bin/pitmaster diff --cached
./bin/pitmaster diff --staged
```

Output is colorized unified diff format matching `git diff`.

### `add`

Stage files for the next commit.

```bash
./bin/pitmaster add src/Feature.php
./bin/pitmaster add src/Feature.php tests/FeatureTest.php README.md
```

Each file is read from the working tree, written as a blob object, and the index is updated.

### `commit`

Create a commit from the staged files.

```bash
./bin/pitmaster commit -m "Add new feature"
```

Output:

```
[main abc1234] Add new feature
```

The author and committer are taken from `.git/config` (`user.name` and `user.email`).

### `branch`

List branches or create a new one.

```bash
# List all branches (current marked with *)
./bin/pitmaster branch

# Create a new branch from HEAD
./bin/pitmaster branch feature/new-thing

# Delete a branch
./bin/pitmaster branch -d feature/old
./bin/pitmaster branch -D feature/old
```

Output for listing:

```
  bugfix/typo
* main
  feature/login
```

### `tag`

List tags or create a new one.

```bash
# List all tags
./bin/pitmaster tag

# Create a lightweight tag
./bin/pitmaster tag v1.0.0

# Create an annotated tag
./bin/pitmaster tag v2.0.0 -m "Release version 2.0.0"
```

### `checkout`

Switch branches or detach HEAD.

```bash
# Switch to a branch
./bin/pitmaster checkout feature/login

# Detach HEAD at a commit
./bin/pitmaster checkout abc123
```

Updates HEAD, the index, and the working tree to match the target.

### `merge`

Merge a branch into the current branch.

```bash
./bin/pitmaster merge feature/login
```

Output on success:

```
Merge successful.
```

Output on conflict:

```
CONFLICT in: src/File.php, src/Other.php
```

### `stash`

Save and restore working directory changes.

```bash
# Save current changes (push)
./bin/pitmaster stash
./bin/pitmaster stash push
./bin/pitmaster stash push "Work in progress on login"

# Apply and remove top stash entry
./bin/pitmaster stash pop

# Apply without removing
./bin/pitmaster stash apply

# List stash entries
./bin/pitmaster stash list

# Drop top stash entry
./bin/pitmaster stash drop
```

Stash list output:

```
stash@{0}: Work in progress on login
stash@{1}: WIP on main
```

### `blame`

Show line-by-line authorship of a file.

```bash
./bin/pitmaster blame src/Repository.php
```

Output:

```
a1b2c3d4 (Dennis Johnson           ) <?php
a1b2c3d4 (Dennis Johnson           )
f5e6d7c8 (Jane Smith               ) declare(strict_types=1);
```

### `grep`

Search file contents in the current HEAD tree.

```bash
./bin/pitmaster grep "function merge"
./bin/pitmaster grep -i -E "h.llo"
```

Output:

```
src/Repository.php:1238:    public function merge(string $branch): MergeResult
src/Merge/ThreeWayMerge.php:25:    public function merge(string $base, string $ours, string $theirs): string
```

Options:

- `-i`, `--ignore-case`: case-insensitive matching
- `-E`, `--extended-regexp`, `--regex`: treat the pattern as a regular expression

Paths are shown in magenta, line numbers in green.

### `refs`

List all refs with their hashes.

```bash
./bin/pitmaster refs
```

Output:

```
abc123def456... refs/heads/main
789012abc345... refs/heads/feature/login
def456789012... refs/tags/v1.0.0
```

### `reset`

Reset HEAD to a specific commit.

```bash
# Mixed reset (HEAD + index, default)
./bin/pitmaster reset HEAD~1

# Soft reset (HEAD only)
./bin/pitmaster reset --soft HEAD~1

# Hard reset (HEAD + index + worktree)
./bin/pitmaster reset --hard HEAD~3
```

Output:

```
HEAD is now at abc1234
```

### `merge`

Merge a branch into the current HEAD, or continue/abort an in-progress merge conflict.

```bash
# Start a merge
./bin/pitmaster merge feature

# Continue after resolving conflicts
./bin/pitmaster add a.txt
./bin/pitmaster merge --continue

# Abort and restore the pre-merge state
./bin/pitmaster merge --abort
```

### `cherry-pick`

Apply a commit on top of the current HEAD, or continue/abort an in-progress conflict.

```bash
# Start a cherry-pick
./bin/pitmaster cherry-pick abc1234

# Continue after resolving conflicts
./bin/pitmaster add a.txt
./bin/pitmaster cherry-pick --continue

# Abort and restore HEAD
./bin/pitmaster cherry-pick --abort
```

### `revert`

Create a revert commit, or continue/abort an in-progress conflict.

```bash
# Start a revert
./bin/pitmaster revert abc1234

# Continue after resolving conflicts
./bin/pitmaster add a.txt
./bin/pitmaster revert --continue

# Abort and restore HEAD
./bin/pitmaster revert --abort
```

### `rebase`

Rebase the current branch onto another ref.

```bash
# Start a rebase
./bin/pitmaster rebase main

# Continue after resolving conflicts
./bin/pitmaster rebase --continue

# Skip the current commit
./bin/pitmaster rebase --skip

# Abort and restore the original branch tip
./bin/pitmaster rebase --abort
```

### `init`

Initialize a new repository.

```bash
# Initialize in the current directory
./bin/pitmaster init

# Initialize at a specific path
./bin/pitmaster init /path/to/new-project
```

Output:

```
Initialized empty Pitmaster repository in /path/to/new-project/.git/
```

## Exit codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Command error (bad arguments, operation failed) |
| 128 | Not a git repository |
