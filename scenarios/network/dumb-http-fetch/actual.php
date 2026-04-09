<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$port = free_port();
$baseUrl = "http://127.0.0.1:{$port}";
$server = start_static_server($port, getcwd() . '/docroot');

try {
    $cloneDir = getcwd() . '/pit-clone';
    $repo = Pitmaster::clone($baseUrl . '/remote.git', $cloneDir);
    git('remote set-url origin ' . escapeshellarg(getcwd() . '/docroot/remote.git'), getcwd() . '/source');

    file_put_contents(getcwd() . '/source/README.md', "dumb http fetch\nremote advance\n");
    file_put_contents(getcwd() . '/source/notes.txt', "fetched over dumb http\n");
    git('add README.md notes.txt', getcwd() . '/source');
    git('commit -m advance', getcwd() . '/source');
    git('push origin main', getcwd() . '/source');
    git('repack -ad', getcwd() . '/docroot/remote.git');
    git('update-server-info', getcwd() . '/docroot/remote.git');

    $repo->fetch();
    $originMain = trim(git('rev-parse refs/remotes/origin/main', $cloneDir));
    $remoteMain = trim(git('rev-parse refs/heads/main', getcwd() . '/docroot/remote.git'));

    $lines = [
        'origin.main_matches_remote=' . ($originMain === $remoteMain ? 'yes' : 'no'),
        'notes.exists=' . (is_file($cloneDir . '/notes.txt') ? 'yes' : 'no'),
    ];

    file_put_contents(getcwd() . '/.fetch-state', implode("\n", $lines) . "\n");
} finally {
    stop_server($server);
}

function git(string $command, string $dir): string
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command), $output, $exitCode);
    $result = implode("\n", $output);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed in {$dir}:\n{$result}");
    }

    return $result;
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
function start_static_server(int $port, string $docRoot)
{
    $process = proc_open(
        sprintf('%s -S 127.0.0.1:%d -t %s', escapeshellarg(PHP_BINARY), $port, escapeshellarg($docRoot)),
        [
            0 => ['pipe', 'r'],
            1 => ['file', getcwd() . '/.server.log', 'a'],
            2 => ['file', getcwd() . '/.server.err', 'a'],
        ],
        $pipes,
        $docRoot,
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start dumb HTTP static server');
    }

    fclose($pipes[0]);
    wait_until_ready("http://127.0.0.1:{$port}/health.txt");

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

    throw new RuntimeException('Dumb HTTP test server did not become ready');
}
