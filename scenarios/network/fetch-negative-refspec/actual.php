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
    $config = $repo->config();
    $config->set('remote.origin.fetch', '+refs/heads/*:refs/remotes/origin/*');
    $config->append('remote.origin.fetch', '^refs/heads/feature');
    $config->writeToFile(getcwd() . '/pit-clone/.git/config');
    $repo = Pitmaster::open(getcwd() . '/pit-clone');

    git('remote set-url origin ' . escapeshellarg(getcwd() . '/projects/remote.git'), getcwd() . '/source');
    file_put_contents(getcwd() . '/source/main.txt', "main branch\n");
    git('add main.txt', getcwd() . '/source');
    git('commit -m main-update', getcwd() . '/source');
    git('push origin main', getcwd() . '/source');
    git('checkout -b feature', getcwd() . '/source');
    file_put_contents(getcwd() . '/source/feature.txt', "feature branch\n");
    git('add feature.txt', getcwd() . '/source');
    git('commit -m feature-update', getcwd() . '/source');
    git('push origin feature', getcwd() . '/source');
    git('checkout main', getcwd() . '/source');

    $repo->fetch();
    $originMain = trim(git('rev-parse refs/remotes/origin/main', getcwd() . '/pit-clone'));
    $remoteMain = trim(git('rev-parse refs/heads/main', getcwd() . '/projects/remote.git'));

    $lines = [
        'origin.main_matches_remote=' . ($originMain === $remoteMain ? 'yes' : 'no'),
        'origin.feature=' . (is_file(getcwd() . '/pit-clone/.git/refs/remotes/origin/feature') ? 'present' : 'absent'),
        'fetch.refspecs=' . trim(str_replace("\n", ',', git('config --get-all remote.origin.fetch', getcwd() . '/pit-clone'))),
    ];
    file_put_contents(getcwd() . '/.negative-refspec-state', implode("\n", $lines) . "\n");
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
            'PITMASTER_GIT_HTTP_BACKEND' => '/Applications/Xcode.app/Contents/Developer/usr/libexec/git-core/git-http-backend',
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
