<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$port = free_port();
$baseUrl = "http://127.0.0.1:{$port}";
$router = getenv('PITMASTER_ROOT') . '/tests/Fixtures/protocol_router.php';
$server = start_server($port, $router);
$target = getcwd() . '/pit-clone';

try {
    Pitmaster::clone($baseUrl . '/discover-404', $target);
} catch (\Throwable) {
} finally {
    stop_server($server);
}

$state = file_exists($target) ? "target_exists=yes\n" : "target_exists=no\n";
file_put_contents(getcwd() . '/.cleanup-state', $state);

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

/**
 * @return resource
 */
function start_server(int $port, string $router)
{
    $process = proc_open(
        sprintf('%s -S 127.0.0.1:%d %s', escapeshellarg(PHP_BINARY), $port, escapeshellarg($router)),
        [
            0 => ['pipe', 'r'],
            1 => ['file', getcwd() . '/.server.log', 'a'],
            2 => ['file', getcwd() . '/.server.err', 'a'],
        ],
        $pipes,
        dirname($router, 3),
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start protocol failure server');
    }

    fclose($pipes[0]);
    wait_until_ready("http://127.0.0.1:{$port}/health");

    return $process;
}

/**
 * @param resource $process
 */
function stop_server($process): void
{
    proc_terminate($process);
    proc_close($process);
}

function wait_until_ready(string $healthUrl): void
{
    $context = stream_context_create([
        'http' => [
            'ignore_errors' => true,
            'timeout' => 1,
        ],
    ]);

    for ($i = 0; $i < 50; $i++) {
        $response = @file_get_contents($healthUrl, false, $context);

        if ($response !== false) {
            return;
        }

        usleep(100000);
    }

    throw new RuntimeException('Protocol failure server did not become ready');
}
