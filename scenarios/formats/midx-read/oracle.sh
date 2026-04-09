#!/usr/bin/env bash
set -euo pipefail

php <<'PHP'
<?php

$packNames = array_values(array_map('basename', glob('.git/objects/pack/*.idx') ?: []));
sort($packNames);
$blob = trim((string) shell_exec('git rev-parse HEAD:tracked.txt'));
$lookup = null;

foreach ($packNames as $index => $name) {
    $lines = [];
    exec('git verify-pack -v ' . escapeshellarg('.git/objects/pack/' . $name) . ' 2>/dev/null', $lines, $exitCode);

    if ($exitCode !== 0) {
        continue;
    }

    foreach ($lines as $line) {
        if (preg_match('/^' . preg_quote($blob, '/') . '\s+\w+\s+\d+\s+\d+\s+(\d+)/', $line, $matches) === 1) {
            $lookup = ['pack' => $index, 'offset' => (int) $matches[1]];
            break 2;
        }
    }
}

$payload = [
    'objectCount' => count(array_filter(explode("\n", trim((string) shell_exec('git rev-list --objects --all'))))),
    'packNames' => $packNames,
    'lookup' => $lookup,
];

file_put_contents('.midx.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
PHP
