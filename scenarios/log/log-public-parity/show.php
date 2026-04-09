<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());
$result = $repo->show($argv[1] ?? 'HEAD');
$paths = [];

foreach ($result['diff'] as $diff) {
    $path = $diff->newPath ?? $diff->oldPath;

    if ($path !== null) {
        $paths[] = $path;
    }
}

sort($paths);

$lines = [
    'hash=' . $result['commit']->id->hex,
    'subject=' . strtok(trim($result['commit']->message), "\n"),
];

if (isset($result['tag'])) {
    $lines[] = 'tag=' . $result['tag']->name;
}

foreach ($paths as $path) {
    $lines[] = 'path=' . $path;
}

echo implode("\n", $lines) . "\n";
