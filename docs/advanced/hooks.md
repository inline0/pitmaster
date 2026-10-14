---
title: "Hooks"
description: "Git hook detection, execution, installation, and supported hook types."
path: "advanced/hooks"
order: 170
section: "Advanced"
meta_title: "Hooks"
meta_description: "Git hook detection, execution, installation, and supported hook types."
---

# Hooks

Git hooks are executable scripts in `.git/hooks/` that run at specific points in git operations. Pitmaster detects and invokes hooks using PHP's process control (`proc_open`).

## Hooks run by default

Repository operations run the hooks listed under [Hooks Pitmaster runs](#hooks-pitmaster-runs) automatically. To turn them off for a repository handle, pass options when opening it:

```php
use Pitmaster\Pitmaster;

// Skip .git/hooks scripts for this handle
$repo = Pitmaster::open('/path/to/project', ['hooks' => false]);

// Start no processes at all: hooks, the fsmonitor hook, clone/fetch/push, and SSH are disabled
$repo = Pitmaster::open('/path/to/project', Pitmaster::processFreeOptions());

$repo->hooksEnabled();      // false
$repo->processesEnabled();  // false for the second handle
```

`Pitmaster::init()` and `Pitmaster::clone()` accept the same options. Running a hook needs `proc_open()`; on hosts that disable it, open repositories with `['hooks' => false]`. The CLI always opens the repository with hooks enabled.

Pitmaster resolves `core.hooksPath` when configured; relative paths are resolved from the working tree. Otherwise it uses the common Git directory's hooks, including in linked worktrees.

`HookRunner`, described below, ignores these options: calling it directly always runs the hook.

## HookRunner

```php
use Pitmaster\Hooks\HookRunner;

$hooks = new HookRunner($repo->gitDir());
```

## Check if a hook exists

```php
if ($hooks->exists('pre-commit')) {
    echo "pre-commit hook is installed\n";
}
```

A hook exists if the file is present at `.git/hooks/<name>` and is executable.

## Run a hook

```php
$result = $hooks->run('pre-commit');

echo $result['exitCode'];  // 0 = success
echo $result['stdout'];    // Hook's standard output
echo $result['stderr'];    // Hook's standard error
```

### With arguments

```php
$result = $hooks->run('commit-msg', ['/path/to/COMMIT_EDITMSG']);
```

### With stdin

```php
$result = $hooks->run('pre-push', ['origin', 'https://github.com/user/repo.git'], $stdinData);
```

The `pre-push` hook receives ref information on stdin.

### Check success

```php
if ($hooks->runAndCheck('pre-commit')) {
    // Hook passed (exit code 0), proceed with commit
} else {
    // Hook rejected the operation
}
```

`runAndCheck()` is a convenience that runs the hook and returns `true` if the exit code is 0. If the hook does not exist, it returns `true` (no hook means no objection).

## List installed hooks

```php
$installed = $hooks->listHooks();
// ['pre-commit', 'commit-msg', 'post-checkout']
```

Lists all files in `.git/hooks/` that are executable (excludes `.sample` files).

`run()` on a missing or non-executable hook returns exit code 0 with empty output. If the process cannot be started, it returns exit code 1 with `Failed to start hook` on stderr.

## Install a hook

```php
$hooks->install('pre-commit', "#!/bin/bash\nphp vendor/bin/phpcs --standard=PSR12 .\n");
```

Writes the script content to `.git/hooks/<name>` and sets the executable permission (`chmod 0755`).

```php
// Install a PHP hook
$hooks->install('pre-commit', <<<'HOOK'
#!/usr/bin/env php
<?php
// Run static analysis before commit
$output = [];
$returnCode = 0;
exec('php vendor/bin/phpstan analyse --no-progress 2>&1', $output, $returnCode);

if ($returnCode !== 0) {
    echo "PHPStan found errors:\n";
    echo implode("\n", $output) . "\n";
    exit(1);
}
HOOK);
```

## Hooks Pitmaster runs

`HookRunner` can run any hook by name. `Repository` runs these automatically:

| Hook | Run by | Arguments | Can abort? |
|------|--------|-----------|-----------|
| `pre-commit` | `commit()` | none | Yes (non-zero exit) |
| `prepare-commit-msg` | `commit()` | message file, then `message` (or `merge` when completing a merge) | Yes |
| `commit-msg` | `commit()` | message file (`.git/COMMIT_EDITMSG`); edits to the file are used | Yes (non-zero exit) |
| `post-commit` | `commit()` | none | No (informational) |
| `pre-rebase` | `rebase()` | the upstream revision | Yes |
| `post-checkout` | `checkout()` | old HEAD, new HEAD, `1` | No (informational) |
| `post-merge` | `merge()`, `mergeOctopus()`, `mergeContinue()` | `0` | No (informational) |
| `pre-push` | `push()` and its variants | remote name, URL; ref lines on stdin | Yes (non-zero exit) |

A failing hook makes the operation throw `RuntimeException('<name> hook failed')`; the hook's output is not included. The commit hooks run only from `commit()`: merge commits created by `merge()` do not run them. `post-checkout` does not run for `addWorktree()`, `reset()`, or `restore()`.

Server-side hooks (`pre-receive`, `update`, `post-receive`, `post-update`), `pre-auto-gc`, and `post-rewrite` are never run automatically.

## Hook environment

When Pitmaster runs a hook, it passes this environment:

```php
$env = getenv();
$env['GIT_DIR'] = $repo->gitDir();
$env['GIT_WORK_TREE'] = $repo->workDir();
```

Hooks inherit the PHP process environment, including `PATH`, `HOME`, locale settings, and `SSH_AUTH_SOCK`. Pitmaster overrides `GIT_DIR` and `GIT_WORK_TREE` for the selected repository handle.

The hook runs in the selected working tree, or the repository directory for a bare repository.

## Hook script format

Hooks can be written in any language. The first line must be a shebang:

```bash
#!/bin/bash
# Bash hook
```

```php
#!/usr/bin/env php
<?php
// PHP hook
```

```python
#!/usr/bin/env python3
# Python hook
```

## Example: pre-commit with Pitmaster

`commit()` already runs `pre-commit`, `prepare-commit-msg`, `commit-msg`, and `post-commit`, so application code only needs to handle the failure:

```php
try {
    $commitId = $repo->commit('Add feature');
} catch (\RuntimeException $e) {
    echo $e->getMessage(); // "pre-commit hook failed"
}
```

Call `HookRunner` yourself only on a handle opened with `['hooks' => false]`, or for hooks Pitmaster does not run.

## Sample hooks

Git initializes repositories with `.sample` hook files; `Pitmaster::init()` creates an empty `hooks/` directory. Pitmaster's `listHooks()` excludes `.sample` files:

```
.git/hooks/
  pre-commit.sample       (excluded from listHooks)
  commit-msg.sample       (excluded from listHooks)
  pre-commit              (included - installed by user)
```
