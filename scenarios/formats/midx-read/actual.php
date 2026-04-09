<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pack\MultiPackIndex;

$midx = MultiPackIndex::open(getcwd() . '/.git/objects/pack/multi-pack-index');
$blob = trim((string) shell_exec('git rev-parse HEAD:tracked.txt'));

$payload = [
    'objectCount' => $midx?->objectCount() ?? 0,
    'packNames' => $midx?->packNames() ?? [],
    'lookup' => $midx?->findObject($blob),
];

file_put_contents(getcwd() . '/.midx.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
