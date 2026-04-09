<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Protocol\ProtocolV2;
use Pitmaster\Protocol\SmartHttpClient;
use Pitmaster\Protocol\UploadPackClient;
use Pitmaster\Tests\Integration\Support\GitTestRuntime;

final class ProtocolV2ParityTest extends TestCase
{
    private string $tmpDir;

    /** @var resource|null */
    private $server = null;

    private string $serverLog = '';
    private string $serverErrLog = '';
    private string $baseUrl = '';
    private string $captureDir = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-protocol-v2-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->captureDir = $this->tmpDir . '/captures';
        mkdir($this->captureDir, 0777, true);
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
    public function lsRefsRequestMatchesGitAndDiscoveryMatchesRemoteState(): void
    {
        [$sourceDir, $remoteDir] = $this->seedRemote('protocol-v2-lsrefs');
        $this->startGitHttpBackendServer(dirname($remoteDir));
        $remoteUrl = $this->baseUrl . '/remote.git';

        $this->git('-c protocol.version=2 clone --no-checkout ' . escapeshellarg($remoteUrl) . ' git-clone', $this->tmpDir);
        $gitLines = $this->normalizeRequestLines($this->requestBody('ls-refs'));
        $gitHeaders = $this->requestHeaders('ls-refs');
        $this->clearCaptures();

        $client = new SmartHttpClient();
        $discovery = $client->discoverRefsV2($remoteUrl);
        $pitLines = $this->normalizeRequestLines($this->requestBody('ls-refs'));
        $pitHeaders = $this->requestHeaders('ls-refs');

        $this->assertSame($gitLines, $pitLines);
        $this->assertSame('version=2', $gitHeaders['Git-Protocol'] ?? null);
        $this->assertSame('version=2', $pitHeaders['Git-Protocol'] ?? null);
        $this->assertSame('refs/heads/main', $discovery->headSymref());
        $this->assertSame(trim($this->git('rev-parse refs/heads/main', $remoteDir)), $discovery->ref('refs/heads/main')?->hex);
        $this->assertSame(trim($this->git('rev-parse refs/tags/v1.0', $remoteDir)), $discovery->ref('refs/tags/v1.0')?->hex);
        $this->assertSame('unborn', $discovery->capabilities()?->get('ls-refs'));
        $this->assertSame('shallow wait-for-done', $discovery->capabilities()?->get('fetch'));
        $this->assertSame('sha1', $discovery->capabilities()?->get('object-format'));
    }

    #[Test]
    public function fetchRequestMatchesGitAndReturnsPackData(): void
    {
        [, $remoteDir] = $this->seedRemote('protocol-v2-fetch');
        $this->startGitHttpBackendServer(dirname($remoteDir));
        $remoteUrl = $this->baseUrl . '/remote.git';

        $this->git('-c protocol.version=2 clone ' . escapeshellarg($remoteUrl) . ' git-clone', $this->tmpDir);
        $gitLines = $this->normalizeRequestLines($this->requestBody('fetch'));
        $gitHeaders = $this->requestHeaders('fetch');
        $this->clearCaptures();

        $client = new SmartHttpClient();
        $discovery = $client->discoverRefsV2($remoteUrl);
        $uploadPack = new UploadPackClient($client);
        $packData = $uploadPack->fetchV2($remoteUrl, array_values($discovery->refs()));
        $pitLines = $this->normalizeRequestLines($this->requestBody('fetch'));
        $pitHeaders = $this->requestHeaders('fetch');

        $this->assertSame($gitLines, $pitLines);
        $this->assertSame('version=2', $gitHeaders['Git-Protocol'] ?? null);
        $this->assertSame('version=2', $pitHeaders['Git-Protocol'] ?? null);
        $this->assertStringStartsWith('PACK', $packData);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function seedRemote(string $content): array
    {
        $projectRoot = $this->tmpDir . '/projects';
        $sourceDir = $this->tmpDir . '/source';
        $remoteDir = $projectRoot . '/remote.git';

        mkdir($projectRoot, 0777, true);
        $this->git('init --initial-branch=main ' . escapeshellarg($sourceDir), $this->tmpDir);
        $this->git('init --bare --initial-branch=main ' . escapeshellarg($remoteDir), $this->tmpDir);
        $this->git('config user.email test@example.com', $sourceDir);
        $this->git('config user.name Test', $sourceDir);
        file_put_contents($sourceDir . '/README.md', "{$content}\n");
        $this->git('add README.md', $sourceDir);
        $this->git('commit -m initial', $sourceDir);
        $this->git('tag v1.0', $sourceDir);
        $this->git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
        $this->git('push origin main --tags', $sourceDir);

        return [$sourceDir, $remoteDir];
    }

    private function startGitHttpBackendServer(string $projectRoot): void
    {
        $port = $this->findFreePort();
        $router = dirname(__DIR__) . '/Fixtures/git_http_backend_router.php';
        $this->baseUrl = "http://127.0.0.1:{$port}";
        $this->serverLog = sys_get_temp_dir() . '/pitmaster-protocol-v2-' . bin2hex(random_bytes(4)) . '.log';
        $this->serverErrLog = sys_get_temp_dir() . '/pitmaster-protocol-v2-' . bin2hex(random_bytes(4)) . '.err.log';

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
                'PITMASTER_CAPTURE_HTTP_BODIES_DIR' => $this->captureDir,
            ],
        );

        if (!is_resource($this->server)) {
            $this->fail('Failed to start git-http-backend test server');
        }

        fclose($pipes[0]);
        $this->waitUntilServerReady($this->baseUrl . '/health');
    }

    private function waitUntilServerReady(string $healthUrl): void
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

        return $result;
    }

    private function clearCaptures(): void
    {
        foreach (glob($this->captureDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
    }

    private function requestBody(string $command): string
    {
        foreach ($this->captureBodies() as $bodyPath) {
            $body = file_get_contents($bodyPath);

            if ($body === false) {
                continue;
            }

            $lines = $this->normalizeRequestLines($body);

            if (($lines[0] ?? null) === "command={$command}") {
                return $body;
            }
        }

        $this->fail("Did not capture protocol v2 {$command} request");
    }

    /**
     * @return array<string, string>
     */
    private function requestHeaders(string $command): array
    {
        foreach ($this->captureBodies() as $bodyPath) {
            $body = file_get_contents($bodyPath);

            if ($body === false) {
                continue;
            }

            $lines = $this->normalizeRequestLines($body);

            if (($lines[0] ?? null) !== "command={$command}") {
                continue;
            }

            $headersPath = substr($bodyPath, 0, -5) . '.headers.json';
            $headers = json_decode((string) file_get_contents($headersPath), true);

            if (!is_array($headers)) {
                return [];
            }

            /** @var array<string, string> $headers */
            return $headers;
        }

        $this->fail("Did not capture protocol v2 {$command} headers");
    }

    /**
     * @return list<string>
     */
    private function captureBodies(): array
    {
        $files = glob($this->captureDir . '/*.body') ?: [];
        sort($files);

        return array_values($files);
    }

    /**
     * @return list<string>
     */
    private function normalizeRequestLines(string $body): array
    {
        $lines = [];

        foreach (ProtocolV2::decode($body) as $packet) {
            if ($packet['type'] === 'data') {
                $line = $packet['payload'] ?? '';
                $line = preg_replace('/agent=[^ ]+/', 'agent=<normalized>', $line) ?? $line;
                $lines[] = $line;
                continue;
            }

            $lines[] = match ($packet['type']) {
                'delimiter' => '0001',
                'response-end' => '0002',
                default => '0000',
            };
        }

        return $lines;
    }
}
