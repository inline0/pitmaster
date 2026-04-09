<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Graph\Grep;
use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());
$grep = new Grep($repo->objectDatabase());
$result = $grep->grep($repo->head()->tree, 'hello');
usort($result, static fn (array $left, array $right): int => [$left['path'], $left['line']] <=> [$right['path'], $right['line']]);
file_put_contents(getcwd() . '/.grep.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
