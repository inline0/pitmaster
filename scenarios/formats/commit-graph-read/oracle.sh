#!/usr/bin/env bash
set -euo pipefail

php <<'PHP'
<?php

$head = trim((string) shell_exec('git rev-parse HEAD'));
$treeAndTime = trim((string) shell_exec('git show -s --format=%T\|%ct HEAD'));
[$tree, $timestamp] = explode('|', $treeAndTime, 2);
$parents = trim((string) shell_exec('git show -s --format=%P HEAD'));
$count = count(array_filter(explode("\n", trim((string) shell_exec('git rev-list --all')))));

$payload = [
    'objectCount' => $count,
    'head' => [
        'hash' => $head,
        'tree' => $tree,
        'timestamp' => (int) $timestamp,
        'parentCount' => $parents === '' ? 0 : count(array_filter(explode(' ', $parents))),
    ],
];

file_put_contents('.commit-graph.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
PHP
