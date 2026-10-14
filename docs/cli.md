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
./vendor/bin/pitmaster <command> [args...]
```

From a Composer install the script is `./vendor/bin/pitmaster`; since 0.2.11 it finds the project's autoloader there. In a checkout of the Pitmaster repository itself, run `./bin/pitmaster` after `composer install`. The examples below use `pitmaster` for either path. Running it without arguments prints the command list.

Revisions are resolved like `Repository::resolve()`: `HEAD`, branch and tag names, full ref names, full hashes, and `~N` / `^N` expressions. Abbreviated hashes are not accepted. Clone, fetch, and push are available only through the PHP API.

## Commands

### `log`

Show commit history, newest first.

```bash
# Default: last 10 commits
pitmaster log

# Limit the number of commits
pitmaster log 25
pitmaster log -n 3
pitmaster log -n3
pitmaster log --max-count 3
pitmaster log --max-count=3
pitmaster log -3

# Commits that touch one file
pitmaster log src/Repository.php
pitmaster log -- src/Repository.php

# Commits reachable from all refs, and one line per commit
pitmaster log --all
pitmaster log --oneline
```

The git limit spellings (`-n N`, `-nN`, `--max-count N`, `--max-count=N`, `-N`) were added in 0.2.11. A bare number is Pitmaster's own shorthand for the limit. The first other argument is a path filter; everything after `--` is treated as the path. Any other argument starting with `-` prints `fatal: unrecognized argument: <arg>` and exits with 128.

Output includes `commit <hash>` (yellow), a `Merge:` line for merge commits, `Author:` with the raw identity (name, email, timestamp and timezone), and the commit message indented. `--oneline` prints `abc1234 Subject`. The path filter matches one exact file path and examines only the newest five times the limit commits ([#7](https://github.com/inline0/pitmaster/issues/7)).

### `show`

Show commit details and diff against the first parent.

```bash
# Show HEAD
pitmaster show

# Show a specific commit
pitmaster show HEAD~3
pitmaster show v1.0.0
pitmaster show 3f2c1e0b9d6a4f8e7c5b2a1d0e9f8c7b6a5d4e3f
```

Displays the commit metadata (with a `Date:` line) followed by a unified diff. For an annotated tag, the tag header and message come first. Colors are used only when stdout is a terminal. Merge commits are shown without a diff.

### `cat-file`

Print the raw content of a git object.

```bash
pitmaster cat-file a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2
```

Takes a full hash. Prints the object's stored content: blobs, commits, and tags as text, trees in their binary form. Unlike `git cat-file -p`, trees are not pretty-printed.

### `status`

Show working tree status.

```bash
pitmaster status
pitmaster status --porcelain=v2
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

Staged files are shown in green, unstaged, unmerged, and untracked in red. An `Unmerged paths:` section appears during a conflicted merge. A detached HEAD prints `HEAD detached`, and a clean tree prints `nothing to commit, working tree clean`. `--porcelain=v2` prints the output of `Repository::statusPorcelainV2()`.

### `diff`

Show changes between the index and the working tree (unstaged changes).

```bash
# Unstaged changes
pitmaster diff

# Staged changes (index vs HEAD)
pitmaster diff --cached
pitmaster diff --staged

# Choose the algorithm
pitmaster diff --patience
pitmaster diff --histogram
pitmaster diff --minimal
```

Output is a colorized unified diff, also when stdout is not a terminal. It matches `git diff` for modified text files; new, deleted, binary, renamed, and mode-changed files are formatted differently (see [Diff](/docs/operations/diff)). `diff --cached` prints nothing before the first commit ([#5](https://github.com/inline0/pitmaster/issues/5)).

### `add`

Stage files for the next commit.

```bash
pitmaster add src/Feature.php
pitmaster add src/Feature.php tests/FeatureTest.php README.md
```

Each file is read from the working tree, written as a blob object, and the index is updated. Arguments are file paths; directories and `.` are not expanded.

### `commit`

Create a commit from the staged files.

```bash
pitmaster commit -m "Add new feature"
```

Output:

```
[main abc1234] Add new feature
```

`-m` is required, except when completing a stopped merge, cherry-pick, revert, or rebase, where the prepared message is used. The author and committer are resolved from environment variables, constants, and `.git/config`, as described in [Getting Started](/docs/getting-started).

### `branch`

List branches or create a new one.

```bash
# List all branches (current marked with *)
pitmaster branch

# Create a new branch from HEAD
pitmaster branch feature/new-thing

# Delete a branch
pitmaster branch -d feature/old
pitmaster branch -D feature/old
```

Output for listing:

```
  bugfix/typo
  feature/login
* main
```

Creating prints `Created branch <name>`, deleting prints `Deleted branch <name>`. `-d` and `-D` behave the same: neither checks whether the branch is merged, and both refuse to delete the checked-out branch.

### `tag`

List tags or create a new one.

```bash
# List all tags
pitmaster tag

# Create a lightweight tag at HEAD
pitmaster tag v1.0.0

# Create an annotated tag at HEAD
pitmaster tag v2.0.0 -m "Release version 2.0.0"
```

Creating prints `Created tag <name>`.

### `checkout`

Switch branches or detach HEAD.

```bash
# Switch to a branch
pitmaster checkout feature/login

# Detach HEAD at a tag or commit
pitmaster checkout v1.0.0
pitmaster checkout HEAD~2
```

Updates HEAD, the index, and the working tree to match the target, and prints `Switched to '<target>'`. It refuses when local changes or untracked files are in paths that differ between the two trees. Uncommitted changes in other tracked files are overwritten with the target's content, where git would keep them ([#2](https://github.com/inline0/pitmaster/issues/2)); commit or stash them first.

### `merge`

Merge one or more branches into the current HEAD, or continue/abort an in-progress merge conflict.

```bash
# Start a merge
pitmaster merge feature/login

# Merge with a strategy
pitmaster merge -s ours feature/login

# Octopus merge of several branches
pitmaster merge feature/a feature/b

# Continue after resolving conflicts
pitmaster add a.txt
pitmaster merge --continue

# Abort and restore the pre-merge state
pitmaster merge --abort
```

Output on success:

```
Merge successful.
```

Output on conflict (on stderr, exit code 1):

```
CONFLICT in: src/File.php, src/Other.php
```

`-s` accepts `ours`, `ort`, and `recursive`; `ort` and `recursive` both use the default merge. With `-s`, the CLI prints `Merge successful.` and exits with 0 even when the merge stopped on conflicts ([#21](https://github.com/inline0/pitmaster/issues/21)), so check `status` afterwards. `merge --continue` without a merge in progress exits with 128. Merging a branch that is already merged creates another merge commit ([#6](https://github.com/inline0/pitmaster/issues/6)), and uncommitted changes in tracked files are overwritten as with `checkout`.

### `cherry-pick`

Apply a commit on top of the current HEAD, or continue/abort an in-progress conflict.

```bash
# Start a cherry-pick
pitmaster cherry-pick feature~1

# Continue after resolving conflicts
pitmaster add a.txt
pitmaster cherry-pick --continue

# Abort and restore HEAD
pitmaster cherry-pick --abort
```

Prints nothing on success. `--continue` and `--abort` without a cherry-pick in progress exit with 128.

### `revert`

Create a revert commit, or continue/abort an in-progress conflict.

```bash
# Start a revert
pitmaster revert HEAD~1

# Continue after resolving conflicts
pitmaster add a.txt
pitmaster revert --continue

# Abort and restore HEAD
pitmaster revert --abort
```

Prints nothing on success. `--continue` and `--abort` without a revert in progress exit with 128.

### `rebase`

Rebase the current branch onto another ref.

```bash
# Start a rebase
pitmaster rebase main

# Continue after resolving conflicts
pitmaster rebase --continue

# Skip the current commit
pitmaster rebase --skip

# Abort and restore the original branch tip
pitmaster rebase --abort
```

Prints nothing on success. `--continue`, `--skip`, and `--abort` without a rebase in progress print `fatal: no rebase in progress` and exit with 128.

### `bisect`

Find the commit that introduced a change by binary search.

```bash
# Start with a bad and a good revision; checks out and prints the first candidate
pitmaster bisect start HEAD v1.0.0

# Mark the checked-out candidate (or a given revision)
pitmaster bisect good
pitmaster bisect bad
pitmaster bisect bad HEAD~3

# End the session and return to the starting branch
pitmaster bisect reset
```

`start`, `good`, and `bad` print the full hash of the next candidate, which is checked out. When only one suspect is left, the same hash is printed again: that commit is the first bad commit. Unlike git, no "is the first bad commit" summary is printed.

### `stash`

Save and restore working directory changes.

```bash
# Save current changes (push)
pitmaster stash
pitmaster stash push
pitmaster stash push "Work in progress on login"
pitmaster stash save "Work in progress on login"

# Apply and remove the top stash entry
pitmaster stash pop

# Apply without removing
pitmaster stash apply

# List stash entries
pitmaster stash list

# Drop the top stash entry
pitmaster stash drop
```

The message is the argument after `push` or `save`; `-m` is not supported and would be stored as the message. `pop`, `apply`, and `drop` always use `stash@{0}`. They print `Saved working directory to stash abc1234`, `Applied and dropped stash`, `Applied stash`, and `Dropped stash`.

Stash list output:

```
stash@{0}: On main: Work in progress on login
stash@{1}: WIP on main: abc1234 Add new feature
```

With more than one entry, `drop` and `pop` currently leave the entry in the list ([#10](https://github.com/inline0/pitmaster/issues/10)), and `apply` overwrites uncommitted changes in files the stash does not touch ([#11](https://github.com/inline0/pitmaster/issues/11)).

### `blame`

Show line-by-line authorship of a file at HEAD.

```bash
pitmaster blame src/Repository.php
```

Output:

```
a1b2c3d4 (Jane Smith <jane@example.com> ) <?php
a1b2c3d4 (Jane Smith <jane@example.com> )
f5e6d7c8 (Ann Lee <ann@example.com> 1700) declare(strict_types=1);
```

Each line shows the first 8 characters of the commit hash and the first 30 characters of the author identity (`Name <email> timestamp timezone`), unpadded.

### `grep`

Search file contents in the current HEAD tree.

```bash
pitmaster grep "function merge"
pitmaster grep -i -E "h.llo"
```

Output:

```
src/Repository.php:2257:    public function merge(string $branch, string $strategy = 'recursive'): MergeResult
src/Merge/ThreeWayMerge.php:23:    public static function merge(
```

Options must come before the pattern:

- `-i`, `--ignore-case`: case-insensitive matching
- `-E`, `--extended-regexp`, `--regex`: treat the pattern as a PCRE regular expression

Output is plain `path:line:content` without colors.

### `reflog`

Show reflog entries, newest first.

```bash
# HEAD reflog (default: 10 entries)
pitmaster reflog

# Another ref, a custom limit, and a format
pitmaster reflog show refs/heads/main
pitmaster reflog -n 5
pitmaster reflog --format="%h %gs"
```

The default format is `%gd: %gs` (`HEAD@{0}: commit: Add feature`). Supported placeholders: `%gd` and `%gD` (selector), `%gs` (message), `%H` and `%h` (full and short new hash). Limits accept `-n N`, `-nN`, and `--max-count N`; `-n 0` prints all entries.

Repositories created by `pitmaster init` do not record reflogs unless `core.logallrefupdates` is set to `true` in `.git/config` ([#8](https://github.com/inline0/pitmaster/issues/8)), so `reflog` prints nothing there until it is.

### `refs`

List all refs with their hashes.

```bash
pitmaster refs
```

Output:

```
abc123def456... refs/heads/main
789012abc345... refs/heads/feature/login
def456789012... refs/tags/v1.0.0
```

Hashes are printed in full.

### `reset`

Reset HEAD to a specific commit.

```bash
# Mixed reset (HEAD + index, default)
pitmaster reset HEAD~1
pitmaster reset --mixed HEAD~1

# Soft reset (HEAD only)
pitmaster reset --soft HEAD~1

# Hard reset (HEAD + index + worktree)
pitmaster reset --hard HEAD~3
```

A revision is required. Output:

```
HEAD is now at abc1234
```

### `init`

Initialize a new repository. This is the only command that does not need an existing repository.

```bash
# Initialize in the current directory
pitmaster init

# Initialize at a specific path
pitmaster init /path/to/new-project

# Create a SHA-256 repository
pitmaster init --object-format=sha256
pitmaster init /path/to/new-project --object-format=sha256
```

Output:

```
Initialized empty Pitmaster repository in /path/to/new-project/.git/
```

`--object-format` accepts `sha1` (default) and `sha256`.

## Exit codes

| Code | Meaning |
|------|---------|
| 0 | Success |
| 1 | Command error: usage error, unknown command, failed operation (`error: <message>`), or a merge that stopped on conflicts |
| 128 | Not a git repository (`fatal: <message>`), an unrecognized `log` argument, or `--continue` / `--abort` / `--skip` with no operation in progress |
