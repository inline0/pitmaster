<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());
$refs = [];

foreach ($repo->refDatabase()->list() as $name => $id) {
    $refs[$name] = $id->hex;
}

ksort($refs);

$payload = [
    'symref' => $repo->refDatabase()->readHead()?->target,
    'branch' => $repo->branch(),
    'refs' => $refs,
];

file_put_contents(getcwd() . '/.reftable.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
