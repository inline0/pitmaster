<?php

declare(strict_types=1);

$php = escapeshellarg(PHP_BINARY);
$bin = escapeshellarg(dirname(__DIR__, 3) . '/bin/pitmaster');
$cwd = escapeshellarg(getcwd());

passthru("cd {$cwd} && {$php} {$bin} merge feature >/dev/null 2>&1", $exitCode);

if ($exitCode === 0) {
    throw new RuntimeException('Expected merge conflict');
}

file_put_contents(getcwd() . '/a.txt', "line 1\nresolved\nline 3\n");
passthru("cd {$cwd} && {$php} {$bin} add a.txt >/dev/null 2>&1", $exitCode);

if ($exitCode !== 0) {
    throw new RuntimeException('Failed to add resolved merge file');
}

putenv('GIT_AUTHOR_DATE=2024-01-15T00:00:10+0000');
putenv('GIT_COMMITTER_DATE=2024-01-15T00:00:10+0000');
passthru("cd {$cwd} && {$php} {$bin} merge --continue >/dev/null 2>&1", $exitCode);

if ($exitCode !== 0) {
    throw new RuntimeException('Failed to continue merge');
}
