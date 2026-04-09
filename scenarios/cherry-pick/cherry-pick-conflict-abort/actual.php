<?php

declare(strict_types=1);

$php = escapeshellarg(PHP_BINARY);
$bin = escapeshellarg(dirname(__DIR__, 3) . '/bin/pitmaster');
$cwd = escapeshellarg(getcwd());
$pickId = trim((string) file_get_contents(getcwd() . '/.pick-id'));

passthru("cd {$cwd} && {$php} {$bin} cherry-pick " . escapeshellarg($pickId) . " >/dev/null 2>&1", $exitCode);

if ($exitCode === 0) {
    throw new RuntimeException('Expected cherry-pick conflict');
}

passthru("cd {$cwd} && {$php} {$bin} cherry-pick --abort >/dev/null 2>&1", $exitCode);

if ($exitCode !== 0) {
    throw new RuntimeException('Failed to abort cherry-pick');
}
