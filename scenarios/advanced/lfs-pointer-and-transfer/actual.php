<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Lfs\LfsClient;
use Pitmaster\Lfs\LfsPointer;

$storage = getcwd() . '/.lfs-storage';

if (!is_dir($storage)) {
    mkdir($storage, 0777, true);
}

$port = (function (): int {
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

    if ($socket === false) {
        throw new RuntimeException("Unable to allocate LFS test port: {$errstr}");
    }

    $name = stream_socket_get_name($socket, false);
    fclose($socket);

    if ($name === false) {
        throw new RuntimeException('Unable to read allocated LFS test port');
    }

    return (int) substr((string) strrchr($name, ':'), 1);
})();

$router = dirname(__DIR__, 3) . '/tests/Fixtures/lfs_router.php';
$process = proc_open(
    sprintf('%s -S 127.0.0.1:%d %s', escapeshellarg(PHP_BINARY), $port, escapeshellarg($router)),
    [
        0 => ['file', '/dev/null', 'r'],
        1 => ['file', getcwd() . '/.lfs-server.out.log', 'a'],
        2 => ['file', getcwd() . '/.lfs-server.err.log', 'a'],
    ],
    $pipes,
    null,
    ['LFS_STORAGE_DIR' => $storage],
);

if (!is_resource($process)) {
    throw new RuntimeException('Failed to start LFS scenario server');
}

$baseUrl = "http://127.0.0.1:{$port}";

try {
    for ($i = 0; $i < 40; $i++) {
        $health = @file_get_contents($baseUrl . '/__health');

        if ($health === "ok\n") {
            break;
        }

        usleep(100000);
    }

    $payload = "payload from lfs\n";
    $pointer = LfsPointer::forContent($payload);
    $client = new LfsClient($baseUrl . '/repo.git/info/lfs');

    $client->upload($pointer->oid, $pointer->size, $payload);

    file_put_contents(getcwd() . '/pointer.txt', $pointer->serialize());
    file_put_contents(getcwd() . '/downloaded.txt', $client->download($pointer->oid, $pointer->size));
} finally {
    proc_terminate($process);
    proc_close($process);
}
