#!/usr/bin/env bash
set -euo pipefail

php <<'PHP'
<?php

$refs = [];
foreach (array_filter(explode("\n", trim((string) shell_exec('git show-ref')))) as $line) {
    [$hash, $name] = explode(' ', $line, 2);
    $refs[$name] = $hash;
}
ksort($refs);

$payload = [
    'symref' => trim((string) shell_exec('git symbolic-ref HEAD')),
    'refs' => $refs,
];

file_put_contents('.reftable.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
PHP
