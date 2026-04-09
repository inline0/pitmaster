<?php

declare(strict_types=1);

$php = escapeshellarg(PHP_BINARY);
$bin = escapeshellarg(dirname(__DIR__, 3) . '/bin/pitmaster');
$cwd = escapeshellarg(getcwd());

passthru("cd {$cwd} && {$php} {$bin} merge feature >/dev/null 2>&1", $exitCode);

if ($exitCode === 0) {
    throw new RuntimeException('Expected merge conflict');
}

passthru("cd {$cwd} && {$php} {$bin} merge --abort >/dev/null 2>&1", $exitCode);

if ($exitCode !== 0) {
    throw new RuntimeException('Failed to abort merge');
}
