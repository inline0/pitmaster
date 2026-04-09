<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Protocol\GitProtocolClient;
use Pitmaster\Protocol\ProtocolV1;

$port = free_port();
$daemon = start_daemon($port, getcwd() . '/export');

try {
    $url = "git://127.0.0.1:{$port}/remote.git";
    $client = new GitProtocolClient(10);
    $mainRef = $client->discoverRefs($url)->ref('refs/heads/main');

    if ($mainRef === null) {
        throw new RuntimeException('Missing refs/heads/main advertisement');
    }

    $response = '';

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $response = $client->uploadPack($url, ProtocolV1::buildFetchRequest([$mainRef]));

        if (str_contains($response, 'PACK')) {
            break;
        }

        usleep(100000);
    }

    $packOffset = strpos($response, 'PACK');

    if ($packOffset === false) {
        throw new RuntimeException('upload-pack response did not contain PACK');
    }

    file_put_contents(getcwd() . '/.git-protocol-fetch-state', 'pack_header=' . substr($response, $packOffset, 4) . "\n");
} finally {
    stop_daemon($daemon);
}

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
function start_daemon(int $port, string $exportRoot)
{
    $process = proc_open(
        sprintf(
            '%s --verbose --reuseaddr --export-all --base-path=%s --listen=127.0.0.1 --port=%d %s',
            escapeshellarg(git_binary()) . ' daemon',
            escapeshellarg($exportRoot),
            $port,
            escapeshellarg($exportRoot),
        ),
        [
            0 => ['pipe', 'r'],
            1 => ['file', getcwd() . '/.daemon.log', 'a'],
            2 => ['file', getcwd() . '/.daemon.err', 'a'],
        ],
        $pipes,
        $exportRoot,
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start git daemon');
    }

    fclose($pipes[0]);
    wait_until_ready($port);

    return $process;
}

/**
 * @param resource $process
 */
function stop_daemon($process): void
{
    proc_terminate($process);
    proc_close($process);
}

function wait_until_ready(int $port): void
{
    for ($i = 0; $i < 50; $i++) {
        $socket = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 1);

        if (is_resource($socket)) {
            fclose($socket);
            return;
        }

        usleep(100000);
    }

    throw new RuntimeException('git daemon did not become ready');
}

function git_binary(): string
{
    $binary = trim((string) shell_exec('command -v git'));

    return $binary !== '' ? $binary : 'git';
}
