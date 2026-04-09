<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Ref\Reftable;

$files = glob(getcwd() . '/.git/reftable/*.ref') ?: [];
sort($files);
$table = Reftable::open((string) end($files));
$refs = [];

foreach ($table?->refs() ?? [] as $name => $id) {
    $refs[$name] = $id->hex;
}

ksort($refs);

$payload = [
    'symref' => $table?->resolveSymbolic('HEAD'),
    'refs' => $refs,
];

file_put_contents(getcwd() . '/.reftable.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
