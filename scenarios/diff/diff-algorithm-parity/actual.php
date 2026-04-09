<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());
$payload = [];

foreach (['patience', 'histogram', 'minimal'] as $algorithm) {
    $payload[$algorithm] = implode('', array_map(
        static fn ($entry) => $entry->format(),
        $repo->diff('article.txt', $algorithm),
    ));
}

file_put_contents(
    getcwd() . '/.diff-algorithms.json',
    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
);
