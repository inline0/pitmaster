<?php

declare(strict_types=1);

namespace Pitmaster\Bench;

final class GitHttpServer
{
    /** @var resource|null */
    private $process = null;

    private string $baseUrl = '';
    private string $stdoutLog = '';
    private string $stderrLog = '';

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $router,
        private readonly ?string $logDir = null,
    ) {
    }

    public function start(): void
    {
        $port = $this->findFreePort();
        $this->baseUrl = "http://127.0.0.1:{$port}";
        $logDir = $this->logDir ?? ($this->projectRoot . '/.bench-logs');
        BenchmarkFilesystem::ensureDirectory($logDir);
        $token = bin2hex(random_bytes(4));
        $this->stdoutLog = $logDir . '/pitmaster-bench-http-' . $token . '.log';
        $this->stderrLog = $logDir . '/pitmaster-bench-http-' . $token . '.err.log';

        $command = sprintf(
            '%s -S 127.0.0.1:%d %s',
            escapeshellarg(PHP_BINARY),
            $port,
            escapeshellarg($this->router),
        );

        $this->process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['file', $this->stdoutLog, 'a'],
                2 => ['file', $this->stderrLog, 'a'],
            ],
            $pipes,
            dirname(__DIR__, 2),
            [
                'PITMASTER_GIT_HTTP_PROJECT_ROOT' => $this->projectRoot,
                'PITMASTER_GIT_HTTP_BACKEND' => BenchmarkShell::gitExecPath() . '/git-http-backend',
            ],
        );

        if (!is_resource($this->process)) {
            throw new \RuntimeException('Failed to start benchmark git-http-backend server');
        }

        fclose($pipes[0]);
        $this->waitUntilReady();
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function stop(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
            $this->process = null;
        }

        if ($this->stdoutLog !== '') {
            @unlink($this->stdoutLog);
        }

        if ($this->stderrLog !== '') {
            @unlink($this->stderrLog);
        }
    }

    private function waitUntilReady(): void
    {
        $context = stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'timeout' => 1,
            ],
        ]);

        for ($i = 0; $i < 50; $i++) {
            $response = @file_get_contents($this->baseUrl . '/health', false, $context);

            if ($response !== false) {
                return;
            }

            usleep(100000);
        }

        $stderr = is_file($this->stderrLog) ? file_get_contents($this->stderrLog) : '';
        throw new \RuntimeException('Benchmark git-http-backend server did not become ready: ' . trim((string) $stderr));
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
