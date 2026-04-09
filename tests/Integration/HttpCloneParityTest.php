<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;

final class HttpCloneParityTest extends TestCase
{
    private string $tmpDir;

    /** @var resource|null */
    private $server = null;

    private string $serverLog = '';
    private string $serverErrLog = '';
    private string $baseUrl = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-http-clone-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }

        if ($this->serverLog !== '') {
            @unlink($this->serverLog);
        }

        if ($this->serverErrLog !== '') {
            @unlink($this->serverErrLog);
        }

        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function failedCloneCleansUpNewTargetDirectory(): void
    {
        $this->startProtocolFailureServer();
        $target = $this->tmpDir . '/failed-clone';

        try {
            Pitmaster::clone($this->baseUrl . '/discover-404', $target);
            $this->fail('Expected clone to fail');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Unexpected HTTP status 404', $e->getMessage());
            $this->assertFileDoesNotExist($target);
        }
    }

    #[Test]
    public function cloneWritesTrackingConfigAndFetchRespectsSingleBranchRefspec(): void
    {
        $projectRoot = $this->tmpDir . '/projects';
        $sourceDir = $this->tmpDir . '/source';
        $cloneDir = $this->tmpDir . '/clone';
        $remoteDir = $projectRoot . '/remote.git';

        mkdir($projectRoot, 0777, true);
        $this->git('init --initial-branch=main ' . escapeshellarg($sourceDir), $this->tmpDir);
        $this->git('init --bare --initial-branch=main ' . escapeshellarg($remoteDir), $this->tmpDir);
        $this->git('config user.email test@example.com', $sourceDir);
        $this->git('config user.name Test', $sourceDir);
        file_put_contents($sourceDir . '/README.md', "http clone parity\n");
        $this->git('add README.md', $sourceDir);
        $this->git('commit -m initial', $sourceDir);
        $this->git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
        $this->git('push origin main', $sourceDir);

        $this->startGitHttpBackendServer($projectRoot);
        $remoteUrl = $this->baseUrl . '/remote.git';

        $repo = Pitmaster::clone($remoteUrl, $cloneDir);

        $this->assertSame($remoteUrl, trim($this->git('config --get remote.origin.url', $cloneDir)));
        $this->assertSame('+refs/heads/*:refs/remotes/origin/*', trim($this->git('config --get remote.origin.fetch', $cloneDir)));
        $this->assertSame('origin', trim($this->git('config --get branch.main.remote', $cloneDir)));
        $this->assertSame('refs/heads/main', trim($this->git('config --get branch.main.merge', $cloneDir)));

        $repoConfig = $repo->config();
        $repoConfig->set('remote.origin.fetch', '+refs/heads/main:refs/remotes/origin/main');
        $repoConfig->writeToFile($cloneDir . '/.git/config');

        file_put_contents($sourceDir . '/main.txt', "main branch\n");
        $this->git('add main.txt', $sourceDir);
        $this->git('commit -m main-update', $sourceDir);
        $this->git('push origin main', $sourceDir);

        $this->git('checkout -b feature', $sourceDir);
        file_put_contents($sourceDir . '/feature.txt', "feature branch\n");
        $this->git('add feature.txt', $sourceDir);
        $this->git('commit -m feature-update', $sourceDir);
        $this->git('push origin feature', $sourceDir);
        $this->git('checkout main', $sourceDir);

        $repo->fetch();

        $this->assertSame(trim($this->git('rev-parse refs/heads/main', $remoteDir)), trim($this->git('rev-parse refs/remotes/origin/main', $cloneDir)));
        $this->assertFileDoesNotExist($cloneDir . '/.git/refs/remotes/origin/feature');
    }

    private function startGitHttpBackendServer(string $projectRoot): void
    {
        $port = $this->findFreePort();
        $router = dirname(__DIR__) . '/Fixtures/git_http_backend_router.php';
        $this->baseUrl = "http://127.0.0.1:{$port}";
        $this->serverLog = sys_get_temp_dir() . '/pitmaster-http-clone-' . bin2hex(random_bytes(4)) . '.log';
        $this->serverErrLog = sys_get_temp_dir() . '/pitmaster-http-clone-' . bin2hex(random_bytes(4)) . '.err.log';

        $command = sprintf(
            '%s -S 127.0.0.1:%d %s',
            escapeshellarg(PHP_BINARY),
            $port,
            escapeshellarg($router),
        );

        $this->server = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['file', $this->serverLog, 'a'],
                2 => ['file', $this->serverErrLog, 'a'],
            ],
            $pipes,
            dirname(__DIR__, 2),
            [
                'PITMASTER_GIT_HTTP_PROJECT_ROOT' => $projectRoot,
                'PITMASTER_GIT_HTTP_BACKEND' => '/Applications/Xcode.app/Contents/Developer/usr/libexec/git-core/git-http-backend',
            ],
        );

        if (!is_resource($this->server)) {
            $this->fail('Failed to start git-http-backend test server');
        }

        fclose($pipes[0]);
        $this->waitUntilServerReady();
    }

    private function startProtocolFailureServer(): void
    {
        $port = $this->findFreePort();
        $router = dirname(__DIR__) . '/Fixtures/protocol_router.php';
        $this->baseUrl = "http://127.0.0.1:{$port}";
        $this->serverLog = sys_get_temp_dir() . '/pitmaster-http-clone-fail-' . bin2hex(random_bytes(4)) . '.log';
        $this->serverErrLog = sys_get_temp_dir() . '/pitmaster-http-clone-fail-' . bin2hex(random_bytes(4)) . '.err.log';

        $command = sprintf(
            '%s -S 127.0.0.1:%d %s',
            escapeshellarg(PHP_BINARY),
            $port,
            escapeshellarg($router),
        );

        $this->server = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['file', $this->serverLog, 'a'],
                2 => ['file', $this->serverErrLog, 'a'],
            ],
            $pipes,
            dirname(__DIR__, 2),
        );

        if (!is_resource($this->server)) {
            $this->fail('Failed to start protocol failure test server');
        }

        fclose($pipes[0]);
        $this->waitUntilServerReady();
    }

    private function waitUntilServerReady(): void
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

        $stderr = is_file($this->serverErrLog) ? file_get_contents($this->serverErrLog) : '';
        $this->fail('HTTP clone test server did not become ready: ' . trim((string) $stderr));
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

    private function git(string $command, string $dir): string
    {
        exec(
            sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command),
            $output,
            $exitCode,
        );

        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->fail("git {$command} failed in {$dir}:\n{$result}");
        }

        return $result;
    }
}
