<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;

final class SshTransportParityTest extends TestCase
{
    private string $tmpDir;
    private string $sourceDir = '';
    private string $remoteDir = '';
    private string $userName = '';
    private string $privateKey = '';
    private string $knownHosts = '';
    private int $port = 0;
    private string $sshdConfig = '';
    private string $sshdLog = '';

    /** @var resource|null */
    private $sshd = null;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-ssh-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->restorePitmasterSshEnv();

        if (is_resource($this->sshd)) {
            proc_terminate($this->sshd);
            proc_close($this->sshd);
        }

        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function cloneAndFetchOverSshMatchGit(): void
    {
        $this->seedRemote();
        $this->startSshServer();
        $url = $this->remoteUrl();
        $gitClone = $this->tmpDir . '/git-clone';
        $pitClone = $this->tmpDir . '/pit-clone';

        $this->gitWithSsh('clone ' . escapeshellarg($url) . ' ' . escapeshellarg($gitClone), $this->tmpDir);
        $this->withPitmasterSshEnv(static function () use ($url, $pitClone): void {
            Pitmaster::clone($url, $pitClone);
        });

        $this->assertSame($this->git('rev-parse HEAD', $gitClone), $this->git('rev-parse HEAD', $pitClone));
        $this->assertSame($this->git('status --porcelain=v2', $gitClone), $this->git('status --porcelain=v2', $pitClone));
        $this->assertSame($this->git('tag --list --sort=refname', $gitClone), $this->git('tag --list --sort=refname', $pitClone));

        $this->advanceRemote('fetch-update', "fetch update\n");

        $this->gitWithSsh('fetch origin', $gitClone);
        $this->withPitmasterSshEnv(static function () use ($pitClone): void {
            Pitmaster::open($pitClone)->fetch();
        });

        $this->assertSame(
            $this->git('rev-parse refs/remotes/origin/main', $gitClone),
            $this->git('rev-parse refs/remotes/origin/main', $pitClone),
        );
        $this->assertSame($this->git('status --porcelain=v2', $gitClone), $this->git('status --porcelain=v2', $pitClone));
    }

    #[Test]
    public function pushOverSshUpdatesRemoteAndIsAcceptedByGit(): void
    {
        $this->seedRemote();
        $this->startSshServer();
        $url = $this->remoteUrl();
        $pitClone = $this->tmpDir . '/pit-clone';

        $this->withPitmasterSshEnv(static function () use ($url, $pitClone): void {
            Pitmaster::clone($url, $pitClone);
        });

        $repo = Pitmaster::open($pitClone);
        $config = $repo->config();
        $config->set('user.name', 'Test User');
        $config->set('user.email', 'test@pitmaster.dev');
        $config->writeToFile($pitClone . '/.git/config');

        file_put_contents($pitClone . '/pit.txt', "ssh push\n");
        $repo->add('pit.txt');
        $head = $repo->commit('ssh push');

        $this->withPitmasterSshEnv(static function () use ($repo): void {
            $repo->push();
        });

        $this->assertSame($head->hex . "\n", $this->git('rev-parse refs/heads/main', $this->remoteDir));

        $gitClone = $this->tmpDir . '/git-verify';
        $this->gitWithSsh('clone ' . escapeshellarg($url) . ' ' . escapeshellarg($gitClone), $this->tmpDir);
        $this->assertSame($head->hex . "\n", $this->git('rev-parse HEAD', $gitClone));
        $this->assertSame("ssh push\n", file_get_contents($gitClone . '/pit.txt'));
    }

    private function seedRemote(): void
    {
        $this->sourceDir = $this->tmpDir . '/source';
        $this->remoteDir = $this->tmpDir . '/remote.git';

        $this->git('init --initial-branch=main ' . escapeshellarg($this->sourceDir), $this->tmpDir);
        $this->git('init --bare --initial-branch=main ' . escapeshellarg($this->remoteDir), $this->tmpDir);
        $this->git('config user.email test@pitmaster.dev', $this->sourceDir);
        $this->git('config user.name "Test User"', $this->sourceDir);
        file_put_contents($this->sourceDir . '/README.md', "ssh transport\n");
        $this->git('add README.md', $this->sourceDir);
        $this->git('commit -m initial', $this->sourceDir);
        $this->git('tag v1.0', $this->sourceDir);
        $this->git('remote add origin ' . escapeshellarg($this->remoteDir), $this->sourceDir);
        $this->git('push origin main --tags', $this->sourceDir);
    }

    private function advanceRemote(string $message, string $content): void
    {
        file_put_contents($this->sourceDir . '/README.md', $content);
        $this->git('add README.md', $this->sourceDir);
        $this->git('commit -m ' . escapeshellarg($message), $this->sourceDir);
        $this->git('push origin main', $this->sourceDir);
    }

    private function startSshServer(): void
    {
        $this->userName = trim((string) shell_exec('id -un'));
        $hostKey = $this->tmpDir . '/host_key';
        $this->privateKey = $this->tmpDir . '/client_key';
        $authorizedKeys = $this->tmpDir . '/authorized_keys';
        $this->knownHosts = $this->tmpDir . '/known_hosts';
        $this->sshdConfig = $this->tmpDir . '/sshd_config';
        $this->sshdLog = $this->tmpDir . '/sshd.log';
        $this->port = $this->findFreePort();

        $this->runCommand(sprintf('ssh-keygen -q -t ed25519 -N "" -f %s', escapeshellarg($this->privateKey)), $this->tmpDir);
        $this->runCommand(sprintf('ssh-keygen -q -t ed25519 -N "" -f %s', escapeshellarg($hostKey)), $this->tmpDir);
        copy($this->privateKey . '.pub', $authorizedKeys);

        file_put_contents($this->sshdConfig, implode("\n", [
            'Port ' . $this->port,
            'ListenAddress 127.0.0.1',
            'HostKey ' . $hostKey,
            'PidFile ' . $this->tmpDir . '/sshd.pid',
            'AuthorizedKeysFile ' . $authorizedKeys,
            'PasswordAuthentication no',
            'KbdInteractiveAuthentication no',
            'ChallengeResponseAuthentication no',
            'PubkeyAuthentication yes',
            'PermitRootLogin no',
            'StrictModes no',
            'UsePAM no',
            'AllowUsers ' . $this->userName,
            'LogLevel VERBOSE',
            '',
        ]));

        $command = sprintf(
            '/usr/sbin/sshd -D -f %s -E %s',
            escapeshellarg($this->sshdConfig),
            escapeshellarg($this->sshdLog),
        );

        $this->sshd = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['file', $this->tmpDir . '/sshd.stdout.log', 'a'],
                2 => ['file', $this->tmpDir . '/sshd.stderr.log', 'a'],
            ],
            $pipes,
            $this->tmpDir,
        );

        if (!is_resource($this->sshd)) {
            $this->fail('Failed to start sshd');
        }

        fclose($pipes[0]);

        for ($i = 0; $i < 50; $i++) {
            if ($this->canConnect('127.0.0.1', $this->port)) {
                break;
            }

            usleep(100000);
        }

        if (!$this->canConnect('127.0.0.1', $this->port)) {
            $this->fail('sshd did not become ready: ' . trim((string) @file_get_contents($this->sshdLog)));
        }

        $this->runCommand(
            sprintf(
                'ssh-keyscan -t ed25519 -p %d 127.0.0.1 > %s',
                $this->port,
                escapeshellarg($this->knownHosts),
            ),
            $this->tmpDir,
        );
    }

    private function remoteUrl(): string
    {
        return sprintf(
            'ssh://%s@127.0.0.1:%d%s',
            $this->userName,
            $this->port,
            $this->remoteDir,
        );
    }

    private function withPitmasterSshEnv(callable $callback): mixed
    {
        $previous = [
            'PITMASTER_SSH_COMMAND' => getenv('PITMASTER_SSH_COMMAND'),
            'PITMASTER_SSH_IDENTITY_FILE' => getenv('PITMASTER_SSH_IDENTITY_FILE'),
            'PITMASTER_SSH_KNOWN_HOSTS' => getenv('PITMASTER_SSH_KNOWN_HOSTS'),
            'PITMASTER_SSH_STRICT_HOST_KEY_CHECKING' => getenv('PITMASTER_SSH_STRICT_HOST_KEY_CHECKING'),
        ];

        putenv('PITMASTER_SSH_COMMAND=ssh');
        putenv('PITMASTER_SSH_IDENTITY_FILE=' . $this->privateKey);
        putenv('PITMASTER_SSH_KNOWN_HOSTS=' . $this->knownHosts);
        putenv('PITMASTER_SSH_STRICT_HOST_KEY_CHECKING=yes');

        try {
            return $callback();
        } finally {
            $this->restoreEnvValue('PITMASTER_SSH_COMMAND', $previous['PITMASTER_SSH_COMMAND']);
            $this->restoreEnvValue('PITMASTER_SSH_IDENTITY_FILE', $previous['PITMASTER_SSH_IDENTITY_FILE']);
            $this->restoreEnvValue('PITMASTER_SSH_KNOWN_HOSTS', $previous['PITMASTER_SSH_KNOWN_HOSTS']);
            $this->restoreEnvValue('PITMASTER_SSH_STRICT_HOST_KEY_CHECKING', $previous['PITMASTER_SSH_STRICT_HOST_KEY_CHECKING']);
        }
    }

    private function restorePitmasterSshEnv(): void
    {
        foreach (
            [
            'PITMASTER_SSH_COMMAND',
            'PITMASTER_SSH_IDENTITY_FILE',
            'PITMASTER_SSH_KNOWN_HOSTS',
            'PITMASTER_SSH_STRICT_HOST_KEY_CHECKING',
            ] as $name
        ) {
            putenv($name);
        }
    }

    private function restoreEnvValue(string $name, string|false $value): void
    {
        if ($value === false) {
            putenv($name);
            return;
        }

        putenv($name . '=' . $value);
    }

    private function gitWithSsh(string $command, string $dir): string
    {
        $sshCommand = sprintf(
            'ssh -i %s -o BatchMode=yes -o StrictHostKeyChecking=yes -o UserKnownHostsFile=%s -p %d',
            escapeshellarg($this->privateKey),
            escapeshellarg($this->knownHosts),
            $this->port,
        );

        return $this->git($command, $dir, ['GIT_SSH_COMMAND' => $sshCommand]);
    }

    /**
     * @param array<string, string> $env
     */
    private function git(string $command, string $dir, array $env = []): string
    {
        $prefix = '';

        foreach ($env as $name => $value) {
            $prefix .= $name . '=' . escapeshellarg($value) . ' ';
        }

        exec(
            sprintf('cd %s && %sgit %s 2>&1', escapeshellarg($dir), $prefix, $command),
            $output,
            $exitCode,
        );

        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->fail("git {$command} failed in {$dir}:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }

    private function runCommand(string $command, string $dir): void
    {
        exec(sprintf('cd %s && %s 2>&1', escapeshellarg($dir), $command), $output, $exitCode);

        if ($exitCode !== 0) {
            $this->fail("command failed: {$command}\n" . implode("\n", $output));
        }
    }

    private function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($socket === false) {
            $this->fail("Failed to allocate test port: {$errstr}");
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        if ($name === false) {
            $this->fail('Failed to read allocated test port');
        }

        return (int) substr((string) strrchr($name, ':'), 1);
    }

    private function canConnect(string $host, int $port): bool
    {
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 1);

        if (!is_resource($socket)) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
