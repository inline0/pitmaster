<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$port = free_port();
$baseUrl = "http://127.0.0.1:{$port}";
$router = getenv('PITMASTER_ROOT') . '/tests/Fixtures/git_http_backend_router.php';
$server = start_server($port, $router, getcwd() . '/projects');

try {
    $repo = Pitmaster::clone($baseUrl . '/remote.git', getcwd() . '/pit-clone');
    $before = pack_count(getcwd() . '/pit-clone/.git/objects/pack');

    git('remote set-url origin ' . escapeshellarg(getcwd() . '/projects/remote.git'), getcwd() . '/source');
    file_put_contents(getcwd() . '/source/remote.txt', "first advance\n");
    git('add remote.txt', getcwd() . '/source');
    git('commit -m remote-update', getcwd() . '/source');
    git('push origin main', getcwd() . '/source');

    $repo->fetch();
    $afterIncremental = pack_count(getcwd() . '/pit-clone/.git/objects/pack');
    $repo->fetch();
    $afterNoop = pack_count(getcwd() . '/pit-clone/.git/objects/pack');
    $originMain = trim(git('rev-parse refs/remotes/origin/main', getcwd() . '/pit-clone'));
    $remoteMain = trim(git('rev-parse refs/heads/main', getcwd() . '/projects/remote.git'));

    $lines = [
        'origin.main_matches_remote=' . ($originMain === $remoteMain ? 'yes' : 'no'),
        'incremental_added=' . ($afterIncremental > $before ? 'yes' : 'no'),
        'noop_stable=' . ($afterNoop === $afterIncremental ? 'yes' : 'no'),
    ];
    file_put_contents(getcwd() . '/.fetch-state', implode("\n", $lines) . "\n");
} finally {
    stop_server($server);
}

function pack_count(string $dir): int
{
    return count(glob($dir . '/*.pack') ?: []);
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
function start_server(int $port, string $router, string $projectRoot)
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
        [
            'PITMASTER_GIT_HTTP_PROJECT_ROOT' => $projectRoot,
            'PITMASTER_GIT_HTTP_BACKEND' => git_http_backend_path(),
        ],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start git-http-backend test server');
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

    throw new RuntimeException('git-http-backend test server did not become ready');
}

function git_http_backend_path(): string
{
    $execPath = trim((string) shell_exec(escapeshellarg(git_binary()) . ' --exec-path'));

    if ($execPath === '') {
        throw new RuntimeException('Unable to resolve git --exec-path');
    }

    return $execPath . '/git-http-backend';
}

function git_binary(): string
{
    $binary = trim((string) shell_exec('command -v git'));

    return $binary !== '' ? $binary : 'git';
}
