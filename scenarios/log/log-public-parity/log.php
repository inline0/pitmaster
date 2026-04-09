<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());
$mode = $argv[1] ?? 'head';
$path = $argv[2] ?? null;

$lines = match ($mode) {
    'head' => array_map(static fn ($commit) => $commit->id->hex, $repo->log(20)),
    'all' => array_map(static fn ($commit) => $commit->id->hex, $repo->logAll(20)),
    'path' => array_map(static fn ($commit) => $commit->id->hex, $repo->logPath((string) $path, 20)),
    'oneline-head' => $repo->logOneline(20),
    'oneline-all' => $repo->logOneline(20, true),
    'oneline-path' => $repo->logOneline(20, false, (string) $path),
    default => throw new RuntimeException("Unknown log mode: {$mode}"),
};

echo implode("\n", $lines);

if ($lines !== []) {
    echo "\n";
}
