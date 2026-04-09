<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Protocol\GitProtocolClient;

$port = free_port();
$state = 'failed=no';

try {
    (new GitProtocolClient(2))->discoverRefs("git://127.0.0.1:{$port}/missing.git");
} catch (\Throwable) {
    $state = 'failed=yes';
}

file_put_contents(getcwd() . '/.git-protocol-error-state', $state . "\n");

function free_port(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

    if ($socket === false) {
        throw new RuntimeException("Failed to allocate port: {$errstr}");
    }

    $name = stream_socket_get_name($socket, false);
    fclose($socket);

    if ($name === false) {
        throw new RuntimeException('Failed to read allocated port');
    }

    return (int) substr((string) strrchr($name, ':'), 1);
}
