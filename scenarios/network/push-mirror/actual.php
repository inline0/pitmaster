<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$projectRoot = getcwd() . '/projects';
$sourceDir = getcwd() . '/source';
$remoteDir = $projectRoot . '/remote.git';
$cloneDir = getcwd() . '/pit-clone';
mkdir($projectRoot, 0777, true);

git('init --initial-branch=main ' . escapeshellarg($sourceDir), getcwd());
git('init --bare --initial-branch=main ' . escapeshellarg($remoteDir), getcwd());
git('config user.email test@example.com', $sourceDir);
git('config user.name Test', $sourceDir);
git('config http.receivepack true', $remoteDir);
file_put_contents($sourceDir . '/README.md', "hello push parity\n");
git('add README.md', $sourceDir);
git('commit -m initial', $sourceDir);
git('checkout -b topic', $sourceDir);
file_put_contents($sourceDir . '/topic.txt', "topic base\n");
git('add topic.txt', $sourceDir);
git('commit -m topic-base', $sourceDir);
git('checkout main', $sourceDir);
git('tag oldtag', $sourceDir);
git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
git('push origin --all', $sourceDir);
git('push origin --tags', $sourceDir);

$port = free_port();
$server = start_server($port, $projectRoot);
$remoteUrl = "http://127.0.0.1:{$port}/remote.git";
$repo = Pitmaster::clone($remoteUrl, $cloneDir);
$repo->createBranch('topic', $repo->resolve('refs/remotes/origin/topic'));
$repo->deleteBranch('topic');
$repo->createBranch('feature');
$repo->createLightweightTag('newtag');
$repo->deleteTag('oldtag');
$repo->pushMirror();

file_put_contents(
    getcwd() . '/.remote-refs.txt',
    git("for-each-ref --format='%(refname)' refs/heads refs/tags | sort", $remoteDir)
    . "[main]\n"
    . git('ls-tree -r --full-tree refs/heads/main', $remoteDir)
    . "[feature]\n"
    . git('ls-tree -r --full-tree refs/heads/feature', $remoteDir)
    . "[newtag]\n"
    . git('ls-tree -r --full-tree refs/tags/newtag', $remoteDir),
);
stop_server($server);

function git(string $command, string $dir): string
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command), $output, $exitCode);
    $result = implode("\n", $output);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed in {$dir}:\n{$result}");
    }

    return $result . ($result === '' ? '' : "\n");
}

function free_port(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

    if ($socket === false) {
        throw new RuntimeException("Failed to allocate port: {$errstr}");
    }

    $name = stream_socket_get_name($socket, false);
    fclose($socket);

    return (int) substr((string) strrchr((string) $name, ':'), 1);
}

/**
 * @return resource
 */
function start_server(int $port, string $projectRoot)
{
    $router = getenv('PITMASTER_ROOT') . '/tests/Fixtures/git_http_backend_router.php';
    $process = proc_open(
        sprintf('%s -S 127.0.0.1:%d %s', escapeshellarg(PHP_BINARY), $port, escapeshellarg($router)),
        [
            0 => ['pipe', 'r'],
            1 => ['file', getcwd() . '/.server.log', 'a'],
            2 => ['file', getcwd() . '/.server.err', 'a'],
        ],
        $pipes,
        dirname(__DIR__, 3),
        [
            'PITMASTER_GIT_HTTP_PROJECT_ROOT' => $projectRoot,
            'PITMASTER_GIT_HTTP_BACKEND' => trim((string) shell_exec('git --exec-path')) . '/git-http-backend',
        ],
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start git-http-backend server');
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

    throw new RuntimeException('git-http-backend server did not become ready');
}
