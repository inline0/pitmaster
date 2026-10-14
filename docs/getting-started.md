---
title: "Getting Started"
description: "Install Pitmaster and perform basic Git operations in PHP."
path: "getting-started"
order: 10
meta_title: "Getting Started"
meta_description: "Install Pitmaster and perform basic Git operations in PHP."
---

# Getting Started

## Installation

Install Pitmaster via Composer:

```bash
composer require pitmaster/pitmaster
```

## Requirements

- **PHP 8.2+** with readonly classes, enums, and constructor promotion
- **ext-zlib** for object compression/decompression (built-in on most installs)
- **ext-json** for operation state files and Git LFS API payloads
- **ext-mbstring**, declared in `composer.json`, so Composer requires it at install time
- **ext-ctype** for hash validation (enabled in most PHP builds, but not declared in `composer.json`)

Core repository operations do not require the `git` binary, FFI, or external helpers. Optional features have additional runtime requirements: hook execution uses `proc_open()`, and SSH transport uses `ext-ssh2` when it is loaded and otherwise starts the system `ssh` binary through `proc_open()`.

## Open an existing repository

```php
use Pitmaster\Pitmaster;

$repo = Pitmaster::open('/path/to/project');
```

This opens the repository at the given path. Pitmaster locates the `.git` directory (or resolves a `.git` file for linked worktrees) and initializes the object database, ref database, and config.

`open()`, `init()`, and `clone()` accept an options array as their last argument:

```php
// Do not run .git/hooks scripts for this handle
$repo = Pitmaster::open('/path/to/project', ['hooks' => false]);

// Do not start any process: no hooks, no fsmonitor hook, no clone/fetch/push, no SSH
$repo = Pitmaster::open('/path/to/project', Pitmaster::processFreeOptions());
```

Hooks run by default. `Pitmaster::processFreeOptions()` returns `['processes' => false, 'hooks' => false]`; local reads and writes keep working on such a handle.

## Check if a path is a repository

```php
if (Pitmaster::isRepository('/path/to/project')) {
    $repo = Pitmaster::open('/path/to/project');
}
```

## Initialize a new repository

```php
$repo = Pitmaster::init('/path/to/new-project');
```

This creates the `.git` directory structure with objects, refs, HEAD (pointing to `refs/heads/main`), and a default config.

To create a SHA-256 repository, pass the object format as the second argument:

```php
$repo = Pitmaster::init('/path/to/new-project', 'sha256');
```

Object IDs in that repository are 64 hex characters, and the config records `extensions.objectformat = sha256`. Repositories opened later pick the format up from that config entry.

## Read the HEAD commit

```php
$head = $repo->head();

echo "Commit: {$head->id->hex}\n";
echo "Author: {$head->author}\n";
echo "Message: {$head->message}\n";
```

The `head()` method resolves HEAD (following symbolic refs) and returns a `Commit` object.

## List branches and tags

```php
// All branch names
$branches = $repo->branches();
// ['main', 'feature/login', 'bugfix/typo']

// Current branch name (null if detached)
$current = $repo->branch();
// 'main'

// All tag names
$tags = $repo->tags();
// ['v1.0.0', 'v1.1.0']
```

## Walk commit history

```php
// Last 20 commits from HEAD
$commits = $repo->log(20);

foreach ($commits as $commit) {
    echo substr($commit->id->hex, 0, 7) . " {$commit->message}\n";
}
```

You can also start from a specific commit:

```php
$from = $repo->resolve('feature/login');
$commits = $repo->log(10, $from);
```

## Check working tree status

```php
use Pitmaster\Status\FileStatus;

$entries = $repo->status();

foreach ($entries as $entry) {
    if ($entry->index === FileStatus::Untracked) {
        echo "?? {$entry->path}\n";
    } elseif ($entry->index === FileStatus::Modified) {
        echo "M  {$entry->path}\n";
    } elseif ($entry->worktree === FileStatus::Modified) {
        echo " M {$entry->path}\n";
    }
}
```

## Stage files and commit

```php
// Stage specific files
$repo->add('src/Feature.php', 'tests/FeatureTest.php');

// Create a commit
$commitId = $repo->commit('Add new feature with tests');

echo "Created commit: {$commitId->hex}\n";
```

The author name and email are resolved in this order: the `PITMASTER_AUTHOR_NAME` / `PITMASTER_AUTHOR_EMAIL` environment variables, the `GIT_AUTHOR_NAME` / `GIT_AUTHOR_EMAIL` environment variables, the `PITMASTER_AUTHOR_NAME` / `PITMASTER_AUTHOR_EMAIL` constants, the repository's own `.git/config` (`user.name` and `user.email`), and finally `Pitmaster <pitmaster@localhost>`. Only the repository config file (and files it includes) is read, not `~/.gitconfig`. The committer is resolved the same way from the `*_COMMITTER_*` names and falls back to the author constants.

## Compute diffs

```php
// Unstaged changes (worktree vs index)
$diffs = $repo->diff();

foreach ($diffs as $diff) {
    echo $diff->format(); // Unified diff output
}

// Staged changes (index vs HEAD)
$staged = $repo->diffStaged();
```

## Clone a remote repository

```php
$repo = Pitmaster::clone('https://github.com/user/repo.git', '/path/to/destination');
```

This discovers refs, fetches the pack file, sets up remote tracking branches, and checks out the default branch. HTTP(S) URLs use smart HTTP and fall back to dumb HTTP; SSH URLs use the SSH transport. Pass a depth as the third argument for a shallow clone: `Pitmaster::clone($url, $path, 1)`.

## Read any object by hash

```php
$object = $repo->readObject($fullHash); // full 40-character (or 64 for SHA-256) hex hash

if ($object instanceof \Pitmaster\Object\Blob) {
    echo $object->content;
} elseif ($object instanceof \Pitmaster\Object\Commit) {
    echo $object->message;
}
```

## Configuration

Pitmaster respects the repository's `.git/config` for author information and remote URLs. You can also set global defaults with PHP constants, defined before Pitmaster runs:

```php
define('PITMASTER_AUTHOR_NAME', 'Your Name');
define('PITMASTER_AUTHOR_EMAIL', 'you@example.com');
define('PITMASTER_HTTP_TIMEOUT', 60);        // Seconds
define('PITMASTER_MAX_DELTA_CHAIN', 50);     // Max delta chain depth
define('PITMASTER_OBJECT_CACHE_BYTES', '64M');
```

| Constant | Default | Effect |
|----------|---------|--------|
| `PITMASTER_AUTHOR_NAME`, `PITMASTER_AUTHOR_EMAIL` | none | Author identity (see the order above) |
| `PITMASTER_COMMITTER_NAME`, `PITMASTER_COMMITTER_EMAIL` | author values | Committer identity |
| `PITMASTER_HTTP_TIMEOUT` | `30` | Smart HTTP timeout in seconds (integer) |
| `PITMASTER_MAX_DELTA_CHAIN` | `50` | Maximum delta chain depth when reading packs (integer) |
| `PITMASTER_OBJECT_CACHE_ENTRIES` | `1024` | Objects kept in the in-memory object cache |
| `PITMASTER_OBJECT_CACHE_BYTES` | `16M` | Byte budget of the object cache |
| `PITMASTER_PACK_RESOLVED_CACHE_ENTRIES` | `4096` | Resolved delta objects cached per pack |
| `PITMASTER_PACK_RESOLVED_CACHE_BYTES` | `32M` | Byte budget of that pack cache |
| `PITMASTER_MAX_PACK_MEMORY` | none | Older name for the pack cache byte budget, used only when `PITMASTER_PACK_RESOLVED_CACHE_BYTES` is not defined |

Byte values accept an integer or a string with a `k`, `m`, or `g` suffix (`'256M'`). Invalid values fall back to the default.

Environment variables override the identity constants: `PITMASTER_AUTHOR_NAME`, `PITMASTER_AUTHOR_EMAIL`, `PITMASTER_AUTHOR_DATE`, the `PITMASTER_COMMITTER_*` equivalents, and git's own `GIT_AUTHOR_*` and `GIT_COMMITTER_*` variables. The SSH transport reads `PITMASTER_SSH_*` variables, described in [Protocols](/docs/network/protocols).

There is no constant for the hash algorithm. The object format is chosen when the repository is created (`Pitmaster::init($path, 'sha256')` or `init --object-format=sha256` on the CLI) and read from the repository config afterwards.
