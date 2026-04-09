#!/usr/bin/env bash
set -euo pipefail

php <<'PHP'
<?php
exec('git blame --line-porcelain -- f.txt 2>&1', $output, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, implode("\n", $output) . "\n");
    exit($exitCode);
}

$result = [];
$hash = null;
$author = null;
$lineNumber = 1;

foreach ($output as $line) {
    if (preg_match('/^[0-9a-f]{40}\s/', $line) === 1) {
        $hash = strtok($line, ' ');
        $author = null;
        continue;
    }

    if (str_starts_with($line, 'author ')) {
        $author = substr($line, 7);
        continue;
    }

    if (str_starts_with($line, 'author-mail ')) {
        $author .= ' ' . substr($line, 12);
        continue;
    }

    if (str_starts_with($line, 'author-time ')) {
        $author .= ' ' . substr($line, 12);
        continue;
    }

    if (str_starts_with($line, 'author-tz ')) {
        $author .= ' ' . substr($line, 10);
        continue;
    }

    if (str_starts_with($line, "\t")) {
        $result[] = [
            'line' => $lineNumber++,
            'hash' => $hash,
            'author' => $author,
            'content' => substr($line, 1),
        ];
    }
}

file_put_contents('.blame.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
PHP
