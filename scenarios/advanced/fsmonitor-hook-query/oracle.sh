#!/usr/bin/env bash
set -euo pipefail

git update-index --fsmonitor >/dev/null
git status --porcelain=v2 >/dev/null
git status --porcelain=v2 >/dev/null

php <<'PHP'
<?php

$lines = array_values(array_filter(explode("\n", trim((string) file_get_contents('.git/fsmonitor.log')))));

$payload = [
    'enabled' => trim((string) shell_exec('git config --get core.fsmonitor')) !== '',
    'token' => 'git-token',
    'files' => ['tracked.txt', 'nested/file.txt'],
    'log' => $lines === [] ? '' : end($lines),
];

file_put_contents('.fsmonitor.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
PHP
