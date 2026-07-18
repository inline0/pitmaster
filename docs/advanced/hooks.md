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

## Supported hooks

Pitmaster recognizes and can invoke the following hooks:

### Commit workflow

| Hook | When | Can abort? |
|------|------|-----------|
| `pre-commit` | Before commit is created | Yes (non-zero exit) |
| `prepare-commit-msg` | After default message is created, before editor | Yes |
| `commit-msg` | After message is entered | Yes (non-zero exit) |
| `post-commit` | After commit is created | No (informational) |

### Branch/checkout

| Hook | When | Can abort? |
|------|------|-----------|
| `pre-rebase` | Before rebase starts | Yes |
| `post-checkout` | After checkout/switch completes | No (informational) |
| `post-merge` | After merge completes | No (informational) |

### Push

| Hook | When | Can abort? |
|------|------|-----------|
| `pre-push` | Before push sends data | Yes (non-zero exit) |

### Server-side

| Hook | When | Can abort? |
|------|------|-----------|
| `pre-receive` | Before refs are updated on server | Yes |
| `update` | Per-ref, before each ref is updated | Yes |
| `post-receive` | After all refs are updated | No (informational) |
| `post-update` | After refs are updated (legacy) | No (informational) |

### Other

| Hook | When | Can abort? |
|------|------|-----------|
| `pre-auto-gc` | Before automatic garbage collection | Yes |
| `post-rewrite` | After commits are rewritten (rebase, amend) | No (informational) |

## Hook environment

When Pitmaster runs a hook, it sets environment variables:

```php
$env = [
    'GIT_DIR' => $this->gitDir,
    'GIT_WORK_TREE' => dirname($this->gitDir),
];
```

The hook runs in the working tree directory (`dirname($gitDir)`).

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

```php
// In your application code
$hooks = new HookRunner($repo->gitDir());

if (!$hooks->runAndCheck('pre-commit')) {
    echo "pre-commit hook failed, aborting commit\n";
    return;
}

$commitId = $repo->commit('Add feature');

$hooks->run('post-commit');
```

## Sample hooks

Git initializes repositories with `.sample` hook files. Pitmaster's `listHooks()` excludes these (files ending in `.sample`):

```
.git/hooks/
  pre-commit.sample       (excluded from listHooks)
  commit-msg.sample       (excluded from listHooks)
  pre-commit              (included - installed by user)
```
