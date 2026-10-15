<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

$repo = Pitmaster\Pitmaster::open(getcwd());
$commits = match ($argv[1] ?? '') {
    'head' => $repo->log(2, oldest: true),
    'all' => $repo->logAll(2, oldest: true),
    'path' => $repo->logPath('app', 2, oldest: true),
    'zero' => $repo->log(0, oldest: true),
    default => throw new RuntimeException('Unknown history mode'),
};
foreach ($commits as $commit) {
    echo $commit->id->hex . "\n";
}
