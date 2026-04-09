<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Status\Fsmonitor;

$fsmonitor = new Fsmonitor(getcwd() . '/.git', getcwd());
$result = $fsmonitor->query('git-token');
$log = trim((string) file_get_contents(getcwd() . '/.git/fsmonitor.log'));

$payload = [
    'enabled' => $fsmonitor->isEnabled(),
    'token' => $result['token'],
    'files' => $result['files'],
    'log' => $log,
];

file_put_contents(getcwd() . '/.fsmonitor.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
