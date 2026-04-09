<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$port = free_port();
$server = startSshServer($port);
$url = sprintf(
    'ssh://%s@127.0.0.1:%d%s',
    trim((string) shell_exec('id -un')),
    $port,
    getcwd() . '/remote.git',
);
$cloneDir = getcwd() . '/pit-clone';

writeKnownHostsFile($port, $server['runtimeDir']);

putenv('PITMASTER_SSH_COMMAND=ssh');
putenv('PITMASTER_SSH_IDENTITY_FILE=' . $server['runtimeDir'] . '/client_key');
putenv('PITMASTER_SSH_KNOWN_HOSTS=' . $server['runtimeDir'] . '/known_hosts');
putenv('PITMASTER_SSH_STRICT_HOST_KEY_CHECKING=yes');

try {
    $repo = Pitmaster::clone($url, $cloneDir);

    git('config user.email test@pitmaster.dev', getcwd() . '/source');
    git('config user.name "Test User"', getcwd() . '/source');
    file_put_contents(getcwd() . '/source/README.md', "ssh fetch update\n");
    git('add README.md', getcwd() . '/source');
    git('commit -m fetch-update', getcwd() . '/source', [
        'GIT_AUTHOR_DATE' => '@1701000100 +0000',
        'GIT_COMMITTER_DATE' => '@1701000100 +0000',
    ]);
    git('push origin main', getcwd() . '/source');

    $repo->fetch();
    $repo->reset('refs/remotes/origin/main', 'hard');

    $config = $repo->config();
    $config->set('user.name', 'Test User');
    $config->set('user.email', 'test@pitmaster.dev');
    $config->writeToFile($cloneDir . '/.git/config');
    putenv('GIT_AUTHOR_DATE=@1701000200 +0000');
    putenv('GIT_COMMITTER_DATE=@1701000200 +0000');
    file_put_contents($cloneDir . '/pit.txt', "ssh push\n");
    $repo->add('pit.txt');
    $head = $repo->commit('ssh push');
    $repo->push();

    $lines = [
        'head=' . $head->hex,
        'remote_head=' . trim(git('rev-parse refs/heads/main', getcwd() . '/remote.git')),
        'origin_main=' . trim(git('rev-parse refs/remotes/origin/main', $cloneDir)),
        'tags=' . trim(git('tag --list --sort=refname', $cloneDir)),
    ];

    file_put_contents(getcwd() . '/.ssh-state', implode("\n", $lines) . "\n");
} finally {
    stopServer($server);
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
 * @return array{process: resource, runtimeDir: string}
 */
function startSshServer(int $port)
{
    $userName = trim((string) shell_exec('id -un'));
    $configPath = getcwd() . '/.scenario-sshd-config';
    $logPath = getcwd() . '/.scenario-sshd.log';
    $runtimeDir = sys_get_temp_dir() . '/pitmaster-ssh-runtime-' . bin2hex(random_bytes(4));

    if (!mkdir($runtimeDir, 0777, true) && !is_dir($runtimeDir)) {
        throw new RuntimeException('Failed to create SSH runtime directory');
    }

    copy(getcwd() . '/.scenario-host-key', $runtimeDir . '/host_key');
    copy(getcwd() . '/.scenario-authorized-keys', $runtimeDir . '/authorized_keys');
    copy(getcwd() . '/.scenario-client-key', $runtimeDir . '/client_key');
    chmod($runtimeDir . '/host_key', 0600);
    chmod($runtimeDir . '/authorized_keys', 0600);
    chmod($runtimeDir . '/client_key', 0600);

    file_put_contents($configPath, implode("\n", [
        'Port ' . $port,
        'ListenAddress 127.0.0.1',
        'HostKey ' . $runtimeDir . '/host_key',
        'PidFile ' . $runtimeDir . '/sshd.pid',
        'AuthorizedKeysFile ' . $runtimeDir . '/authorized_keys',
        'PasswordAuthentication no',
        'KbdInteractiveAuthentication no',
        'ChallengeResponseAuthentication no',
        'PubkeyAuthentication yes',
        'PermitRootLogin no',
        'StrictModes no',
        'UsePAM no',
        'AllowUsers ' . $userName,
        'LogLevel VERBOSE',
        '',
    ]));

    $process = proc_open(
        sprintf(
            '/usr/sbin/sshd -D -f %s -E %s',
            escapeshellarg($configPath),
            escapeshellarg($logPath),
        ),
        [
            0 => ['pipe', 'r'],
            1 => ['file', getcwd() . '/.scenario-sshd.stdout.log', 'a'],
            2 => ['file', getcwd() . '/.scenario-sshd.stderr.log', 'a'],
        ],
        $pipes,
        getcwd(),
    );

    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start scenario sshd');
    }

    fclose($pipes[0]);
    waitUntilSshReady($port, $logPath);

    return ['process' => $process, 'runtimeDir' => $runtimeDir];
}

function waitUntilSshReady(int $port, string $logPath): void
{
    for ($i = 0; $i < 50; $i++) {
        $socket = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 1);

        if (is_resource($socket)) {
            fclose($socket);
            return;
        }

        usleep(100000);
    }

    throw new RuntimeException('scenario sshd did not become ready: ' . trim((string) @file_get_contents($logPath)));
}

function writeKnownHostsFile(int $port, string $runtimeDir): void
{
    $publicKey = trim((string) file_get_contents(getcwd() . '/.scenario-host-key.pub'));

    if ($publicKey === '') {
        throw new RuntimeException('Missing SSH host public key');
    }

    $parts = preg_split('/\s+/', $publicKey);

    if (!is_array($parts) || count($parts) < 2) {
        throw new RuntimeException('Malformed SSH host public key');
    }

    file_put_contents(
        $runtimeDir . '/known_hosts',
        sprintf('[127.0.0.1]:%d %s %s', $port, $parts[0], $parts[1]) . "\n",
    );
}

/**
 * @param array{process: resource, runtimeDir: string} $server
 */
function stopServer(array $server): void
{
    proc_terminate($server['process']);
    proc_close($server['process']);
    exec('rm -rf ' . escapeshellarg($server['runtimeDir']));
}

function git(string $command, string $dir, array $env = []): string
{
    $prefix = '';

    foreach ($env as $name => $value) {
        $prefix .= $name . '=' . escapeshellarg($value) . ' ';
    }

    exec(sprintf('cd %s && %sgit %s 2>&1', escapeshellarg($dir), $prefix, $command), $output, $exitCode);
    $result = implode("\n", $output);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed in {$dir}:\n{$result}");
    }

    return $result;
}
