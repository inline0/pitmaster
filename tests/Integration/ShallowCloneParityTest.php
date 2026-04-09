<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Tests\Integration\Support\GitTestRuntime;

final class ShallowCloneParityTest extends TestCase
{
    private string $tmpDir;

    /** @var resource|null */
    private $server = null;

    private string $serverLog = '';
    private string $serverErrLog = '';
    private string $baseUrl = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-shallow-clone-' . bin2hex(random_bytes(4));
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
    public function shallowCloneDepthOneMatchesGit(): void
    {
        [, $remoteDir] = $this->seedRemote();
        $this->startGitHttpBackendServer(dirname($remoteDir));
        $remoteUrl = $this->baseUrl . '/remote.git';
        $gitClone = $this->tmpDir . '/git-clone';
        $pitClone = $this->tmpDir . '/pit-clone';

        $this->git('clone --depth=1 ' . escapeshellarg($remoteUrl) . ' ' . escapeshellarg($gitClone), $this->tmpDir);
        Pitmaster::clone($remoteUrl, $pitClone, 1);

        $this->assertSame("true\n", $this->git('rev-parse --is-shallow-repository', $gitClone));
        $this->assertSame("true\n", $this->git('rev-parse --is-shallow-repository', $pitClone));
        $this->assertSame($this->git('rev-list --count HEAD', $gitClone), $this->git('rev-list --count HEAD', $pitClone));
        $this->assertSame($this->git('rev-parse HEAD', $gitClone), $this->git('rev-parse HEAD', $pitClone));
        $this->assertSame(
            file_get_contents($gitClone . '/.git/shallow'),
            file_get_contents($pitClone . '/.git/shallow'),
        );
    }

    #[Test]
    public function shallowFetchDepthTwoMatchesGit(): void
    {
        [, $remoteDir] = $this->seedRemote();
        $this->startGitHttpBackendServer(dirname($remoteDir));
        $remoteUrl = $this->baseUrl . '/remote.git';
        $gitClone = $this->tmpDir . '/git-clone';
        $pitClone = $this->tmpDir . '/pit-clone';

        $this->git('clone --depth=1 ' . escapeshellarg($remoteUrl) . ' ' . escapeshellarg($gitClone), $this->tmpDir);
        Pitmaster::clone($remoteUrl, $pitClone, 1);

        $this->git('fetch --depth=2 origin main', $gitClone);
        Pitmaster::open($pitClone)->fetch('origin', 2);

        $this->assertSame($this->git('rev-list --count HEAD', $gitClone), $this->git('rev-list --count HEAD', $pitClone));
        $this->assertSame($this->git('rev-parse HEAD', $gitClone), $this->git('rev-parse HEAD', $pitClone));
        $this->assertSame(
            file_get_contents($gitClone . '/.git/shallow'),
            file_get_contents($pitClone . '/.git/shallow'),
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function seedRemote(): array
    {
        $projectRoot = $this->tmpDir . '/projects';
        $sourceDir = $this->tmpDir . '/source';
        $remoteDir = $projectRoot . '/remote.git';

        mkdir($projectRoot, 0777, true);
        $this->git('init --initial-branch=main ' . escapeshellarg($sourceDir), $this->tmpDir);
        $this->git('init --bare --initial-branch=main ' . escapeshellarg($remoteDir), $this->tmpDir);
        $this->git('config user.email test@example.com', $sourceDir);
        $this->git('config user.name Test', $sourceDir);

        for ($i = 1; $i <= 4; $i++) {
            file_put_contents($sourceDir . '/history.txt', "commit {$i}\n");
            $this->git('add history.txt', $sourceDir);
            $this->git('commit -m c' . $i, $sourceDir);
        }

        $this->git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
        $this->git('push origin main', $sourceDir);

        return [$sourceDir, $remoteDir];
    }

    private function startGitHttpBackendServer(string $projectRoot): void
    {
        $port = $this->findFreePort();
        $router = dirname(__DIR__) . '/Fixtures/git_http_backend_router.php';
        $this->baseUrl = "http://127.0.0.1:{$port}";
        $this->serverLog = sys_get_temp_dir() . '/pitmaster-shallow-http-' . bin2hex(random_bytes(4)) . '.log';
        $this->serverErrLog = sys_get_temp_dir() . '/pitmaster-shallow-http-' . bin2hex(random_bytes(4)) . '.err.log';

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
                'PITMASTER_GIT_HTTP_BACKEND' => GitTestRuntime::gitHttpBackend(),
            ],
        );

        if (!is_resource($this->server)) {
            $this->fail('Failed to start git-http-backend test server');
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
        $this->fail('git-http-backend test server did not become ready: ' . trim((string) $stderr));
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

        return $result . ($result === '' ? '' : "\n");
    }
}
