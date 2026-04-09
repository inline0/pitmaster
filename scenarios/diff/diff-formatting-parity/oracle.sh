#!/usr/bin/env bash
set -euo pipefail

php <<'PHP'
<?php

$wordDiff = trim((string) shell_exec("git diff-tree --word-diff=plain --no-color --root --no-commit-id -p HEAD~1 HEAD -- article.txt | tail -n 1"));
$plain = (string) shell_exec('git show --format= --no-color --no-renames HEAD');
$color = base64_encode((string) shell_exec('git show --format= --color=always --no-renames HEAD'));

$payload = [
    'word' => $wordDiff,
    'plain' => $plain,
    'color' => $color,
];

file_put_contents('.diff-format.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
PHP
