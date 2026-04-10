<?php

declare(strict_types=1);

namespace Pitmaster\Bench;

final class GitDaemonServer
{
    /** @var resource|null */
    private $process = null;

    private string $daemonLog = '';
    private string $daemonErrLog = '';
    private int $port = 0;

    public function __construct(
        private readonly string $exportRoot,
        private readonly string $logDir,
    ) {
    }

    public function start(): void
    {
        $this->port = $this->findFreePort();
        BenchmarkFilesystem::ensureDirectory($this->logDir);
        $token = bin2hex(random_bytes(4));
        $this->daemonLog = $this->logDir . '/pitmaster-git-daemon-' . $token . '.log';
        $this->daemonErrLog = $this->logDir . '/pitmaster-git-daemon-' . $token . '.err.log';

        $command = sprintf(
            '%s daemon --verbose --reuseaddr --export-all --base-path=%s --listen=127.0.0.1 --port=%d %s',
            escapeshellarg(BenchmarkShell::gitBinary()),
            escapeshellarg($this->exportRoot),
            $this->port,
            escapeshellarg($this->exportRoot),
        );

        $this->process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['file', $this->daemonLog, 'a'],
                2 => ['file', $this->daemonErrLog, 'a'],
            ],
            $pipes,
            $this->exportRoot,
        );

        if (!is_resource($this->process)) {
            throw new \RuntimeException('Failed to start benchmark git daemon');
        }

        fclose($pipes[0]);
        $this->waitUntilReady();
    }

    public function port(): int
    {
        return $this->port;
    }

    public function url(string $path): string
    {
        return sprintf('git://127.0.0.1:%d/%s', $this->port, ltrim($path, '/'));
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }

        if ($this->daemonLog !== '') {
            @unlink($this->daemonLog);
        }

        if ($this->daemonErrLog !== '') {
            @unlink($this->daemonErrLog);
        }
    }

    private function waitUntilReady(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $socket = @stream_socket_client("tcp://127.0.0.1:{$this->port}", $errno, $errstr, 1);

            if (is_resource($socket)) {
                fclose($socket);
                return;
            }

            usleep(100000);
        }

        $stderr = is_file($this->daemonErrLog) ? file_get_contents($this->daemonErrLog) : '';
        throw new \RuntimeException('Benchmark git daemon did not become ready: ' . trim((string) $stderr));
    }

    private function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($socket === false) {
            throw new \RuntimeException("Failed to allocate benchmark port: {$errstr}");
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        if ($name === false) {
            throw new \RuntimeException('Failed to read allocated benchmark port');
        }

        return (int) substr((string) strrchr($name, ':'), 1);
    }
}
