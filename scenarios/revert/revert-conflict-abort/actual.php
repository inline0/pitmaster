<?php

declare(strict_types=1);

$php = escapeshellarg(PHP_BINARY);
$bin = escapeshellarg(dirname(__DIR__, 3) . '/bin/pitmaster');
$cwd = escapeshellarg(getcwd());
$revertId = trim((string) file_get_contents(getcwd() . '/.revert-id'));

passthru("cd {$cwd} && {$php} {$bin} revert " . escapeshellarg($revertId) . " >/dev/null 2>&1", $exitCode);

if ($exitCode === 0) {
    throw new RuntimeException('Expected revert conflict');
}

passthru("cd {$cwd} && {$php} {$bin} revert --abort >/dev/null 2>&1", $exitCode);

if ($exitCode !== 0) {
    throw new RuntimeException('Failed to abort revert');
}
