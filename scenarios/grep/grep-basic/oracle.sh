#!/usr/bin/env bash
set -euo pipefail

php <<'PHP'
<?php
exec('git grep -n -- hello 2>/dev/null', $output, $exitCode);

if (!in_array($exitCode, [0, 1], true)) {
    fwrite(STDERR, "git grep failed\n");
    exit($exitCode);
}

$result = [];

foreach ($output as $line) {
    if (preg_match('/^Binary file (.+) matches$/', $line, $matches) === 1) {
        $result[] = [
            'path' => $matches[1],
            'line' => 0,
            'content' => '',
        ];
        continue;
    }

    [$path, $lineNumber, $content] = array_pad(explode(':', $line, 3), 3, '');
    $result[] = [
        'path' => $path,
        'line' => (int) $lineNumber,
        'content' => $content,
    ];
}

usort($result, static fn (array $left, array $right): int => [$left['path'], $left['line']] <=> [$right['path'], $right['line']]);
file_put_contents('.grep.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
PHP
