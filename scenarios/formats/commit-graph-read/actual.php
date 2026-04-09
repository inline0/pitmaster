<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pack\CommitGraph;

$graph = CommitGraph::open(getcwd() . '/.git/objects/info/commit-graph');
$head = trim((string) shell_exec('git rev-parse HEAD'));
$lookup = $graph?->lookup($head);
$parents = trim((string) shell_exec('git show -s --format=%P HEAD'));

$payload = [
    'objectCount' => $graph?->objectCount() ?? 0,
    'head' => [
        'hash' => $head,
        'tree' => $lookup['tree'] ?? null,
        'timestamp' => $lookup['timestamp'] ?? null,
        'parentCount' => $parents === '' ? 0 : count(array_filter(explode(' ', $parents))),
    ],
];

file_put_contents(getcwd() . '/.commit-graph.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
