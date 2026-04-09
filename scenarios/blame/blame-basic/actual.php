<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Graph\Blame;
use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());
$blame = new Blame($repo->objectDatabase());
$result = array_map(
    static fn (array $entry): array => [
        'line' => $entry['line'],
        'hash' => $entry['hash'],
        'author' => $entry['author'],
        'content' => $entry['content'],
    ],
    $blame->blame($repo->head()->id, 'f.txt'),
);

file_put_contents(getcwd() . '/.blame.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
