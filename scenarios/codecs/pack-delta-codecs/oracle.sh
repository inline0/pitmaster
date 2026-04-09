#!/usr/bin/env bash
set -euo pipefail

php <<'PHP'
<?php

$indexPath = glob('.git/objects/pack/*.idx')[0] ?? null;
$entries = [];
exec('git verify-pack -v ' . escapeshellarg((string) $indexPath) . ' 2>/dev/null', $output, $exitCode);
if ($exitCode !== 0) {
    fwrite(STDERR, "git verify-pack failed\n");
    exit($exitCode);
}

foreach ($output as $line) {
    if (preg_match('/^([0-9a-f]+)\s+\w+\s+(\d+)\s+\d+\s+(\d+)(?:\s+(\d+)\s+([0-9a-f]+))?$/', trim($line), $matches) !== 1) {
        continue;
    }

    $entries[$matches[1]] = [
        'hash' => $matches[1],
        'size' => (int) $matches[2],
        'offset' => (int) $matches[3],
        'baseHash' => isset($matches[5]) && $matches[5] !== '' ? $matches[5] : null,
    ];
}

$delta = null;
foreach ($entries as $entry) {
    if ($entry['baseHash'] !== null) {
        $delta = $entry;
        break;
    }
}

if ($delta === null) {
    fwrite(STDERR, "no delta entry found\n");
    exit(1);
}

$payload = [
    'size' => $delta['size'],
    'baseOffset' => $entries[$delta['baseHash']]['offset'],
    'baseSize' => (int) trim((string) shell_exec('git cat-file -s ' . escapeshellarg($delta['baseHash']))),
    'resultSize' => (int) trim((string) shell_exec('git cat-file -s ' . escapeshellarg($delta['hash']))),
];

file_put_contents('.codec.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
PHP
