#!/usr/bin/env bash
set -euo pipefail

php <<'PHP'
<?php

$payload = [
    'patience' => (string) shell_exec('git diff --patience --no-color -- article.txt'),
    'histogram' => (string) shell_exec('git diff --histogram --no-color -- article.txt'),
    'minimal' => (string) shell_exec('git diff --minimal --no-color -- article.txt'),
];

file_put_contents('.diff-algorithms.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
PHP
