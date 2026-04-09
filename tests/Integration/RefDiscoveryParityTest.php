<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Protocol\Capability;
use Pitmaster\Protocol\GitProtocolClient;
use Pitmaster\Protocol\PktLine;
use Pitmaster\Protocol\RefDiscovery;
use Pitmaster\Protocol\SmartHttpClient;
use Pitmaster\Tests\Integration\Support\GitTestRuntime;

final class RefDiscoveryParityTest extends TestCase
{
    private string $tmpDir;

    /** @var resource|null */
    private $httpServer = null;

    /** @var resource|null */
    private $daemon = null;

    private string $httpServerLog = '';
    private string $httpServerErrLog = '';
    private string $daemonLog = '';
    private string $daemonErrLog = '';
    private string $baseUrl = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-ref-discovery-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->httpServer)) {
            proc_terminate($this->httpServer);
            proc_close($this->httpServer);
        }

        if (is_resource($this->daemon)) {
            proc_terminate($this->daemon);
            proc_close($this->daemon);
        }

        foreach ([$this->httpServerLog, $this->httpServerErrLog, $this->daemonLog, $this->daemonErrLog] as $log) {
            if ($log !== '') {
                @unlink($log);
            }
        }

        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function smartHttpAdvertisementAndDiscoveryMatchGit(): void
    {
        $projectRoot = $this->tmpDir . '/projects';
        $remoteDir = $this->seedRemote($projectRoot);
        $this->startGitHttpBackendServer($projectRoot);
        $remoteUrl = $this->baseUrl . '/remote.git';

        $trace = $this->gitTrace('-c protocol.version=1 ls-remote --symref ' . escapeshellarg($remoteUrl), $this->tmpDir);
        $gitLines = $this->extractTracePayloads($trace, 'git<');
        $actualResponse = file_get_contents($remoteUrl . '/info/refs?service=git-upload-pack');
        $this->assertNotFalse($actualResponse);
        $actualLines = $this->normalizeSmartHttpAdvertisement((string) $actualResponse);

        $this->assertSame($gitLines, $actualLines);

        $client = new SmartHttpClient();
        $discovery = $client->discoverRefs($remoteUrl);

        $this->assertSame($this->serializeDiscoveryFromAdvertisement($gitLines), $this->serializeDiscovery($discovery));
        $this->assertSame(trim($this->git('rev-parse refs/heads/main', $remoteDir)), $discovery->ref('refs/heads/main')?->hex);
        $this->assertSame('refs/heads/main', $discovery->headSymref());
    }

    #[Test]
    public function gitProtocolAdvertisementAndDiscoveryMatchGit(): void
    {
        $exportRoot = $this->tmpDir . '/export';
        $remoteDir = $this->seedRemote($exportRoot);
        $port = $this->startGitDaemon($exportRoot);
        $url = "git://127.0.0.1:{$port}/remote.git";

        $trace = $this->gitTrace('-c protocol.version=1 ls-remote --symref ' . escapeshellarg($url), $this->tmpDir);
        $gitLines = $this->extractTracePayloads($trace, 'ls-remote<');
        $actualLines = $this->normalizeGitAdvertisement($this->rawGitProtocolAdvertisement($url));

        $this->assertSame($gitLines, $actualLines);

        $client = new GitProtocolClient(10);
        $discovery = $client->discoverRefs($url);

        $this->assertSame($this->serializeDiscoveryFromAdvertisement($gitLines), $this->serializeDiscovery($discovery));
        $this->assertSame(trim($this->git('rev-parse refs/heads/main', $remoteDir)), $discovery->ref('refs/heads/main')?->hex);
        $this->assertSame('refs/heads/main', $discovery->headSymref());
    }

    private function seedRemote(string $root): string
    {
        $sourceDir = $this->tmpDir . '/source-' . bin2hex(random_bytes(2));
        $remoteDir = $root . '/remote.git';

        mkdir($root, 0777, true);
        $this->git('init --initial-branch=main ' . escapeshellarg($sourceDir), $this->tmpDir);
        $this->git('init --bare --initial-branch=main ' . escapeshellarg($remoteDir), $this->tmpDir);
        $this->git('config user.email test@example.com', $sourceDir);
        $this->git('config user.name Test', $sourceDir);
        file_put_contents($sourceDir . '/README.md', "ref discovery\n");
        $this->git('add README.md', $sourceDir);
        $this->git('commit -m initial', $sourceDir);
        $this->git('tag v1.0', $sourceDir);
        $this->git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
        $this->git('push origin main --tags', $sourceDir);

        return $remoteDir;
    }

    private function startGitHttpBackendServer(string $projectRoot): void
    {
        $port = $this->findFreePort();
        $router = dirname(__DIR__) . '/Fixtures/git_http_backend_router.php';
        $this->baseUrl = "http://127.0.0.1:{$port}";
        $this->httpServerLog = sys_get_temp_dir() . '/pitmaster-ref-http-' . bin2hex(random_bytes(4)) . '.log';
        $this->httpServerErrLog = sys_get_temp_dir() . '/pitmaster-ref-http-' . bin2hex(random_bytes(4)) . '.err.log';

        $command = sprintf(
            '%s -S 127.0.0.1:%d %s',
            escapeshellarg(PHP_BINARY),
            $port,
            escapeshellarg($router),
        );

        $this->httpServer = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['file', $this->httpServerLog, 'a'],
                2 => ['file', $this->httpServerErrLog, 'a'],
            ],
            $pipes,
            dirname(__DIR__, 2),
            [
                'PITMASTER_GIT_HTTP_PROJECT_ROOT' => $projectRoot,
                'PITMASTER_GIT_HTTP_BACKEND' => GitTestRuntime::gitHttpBackend(),
            ],
        );

        if (!is_resource($this->httpServer)) {
            $this->fail('Failed to start git-http-backend test server');
        }

        fclose($pipes[0]);
        $this->waitUntilHttpReady($this->baseUrl . '/health');
    }

    private function startGitDaemon(string $exportRoot): int
    {
        $port = $this->findFreePort();
        $this->daemonLog = sys_get_temp_dir() . '/pitmaster-ref-daemon-' . bin2hex(random_bytes(4)) . '.log';
        $this->daemonErrLog = sys_get_temp_dir() . '/pitmaster-ref-daemon-' . bin2hex(random_bytes(4)) . '.err.log';

        $command = sprintf(
            '%s --verbose --reuseaddr --export-all --base-path=%s --listen=127.0.0.1 --port=%d %s',
            GitTestRuntime::gitDaemonCommand(),
            escapeshellarg($exportRoot),
            $port,
            escapeshellarg($exportRoot),
        );

        $this->daemon = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['file', $this->daemonLog, 'a'],
                2 => ['file', $this->daemonErrLog, 'a'],
            ],
            $pipes,
            $exportRoot,
        );

        if (!is_resource($this->daemon)) {
            $this->fail('Failed to start git daemon');
        }

        fclose($pipes[0]);
        $this->waitUntilGitDaemonReady($port);

        return $port;
    }

    private function waitUntilHttpReady(string $healthUrl): void
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

        $stderr = is_file($this->httpServerErrLog) ? file_get_contents($this->httpServerErrLog) : '';
        $this->fail('git-http-backend test server did not become ready: ' . trim((string) $stderr));
    }

    private function waitUntilGitDaemonReady(int $port): void
    {
        for ($i = 0; $i < 50; $i++) {
            $socket = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 1);

            if (is_resource($socket)) {
                fclose($socket);
                return;
            }

            usleep(100000);
        }

        $stderr = is_file($this->daemonErrLog) ? file_get_contents($this->daemonErrLog) : '';
        $this->fail('git daemon did not become ready: ' . trim((string) $stderr));
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

    private function gitTrace(string $command, string $dir): string
    {
        exec(
            sprintf('cd %s && GIT_TRACE_PACKET=1 git %s 2>&1', escapeshellarg($dir), $command),
            $output,
            $exitCode,
        );

        $trace = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->fail("git {$command} failed in {$dir}:\n{$trace}");
        }

        return $trace;
    }

    /**
     * @return list<string>
     */
    private function extractTracePayloads(string $trace, string $marker): array
    {
        $lines = [];

        foreach (explode("\n", $trace) as $line) {
            $pos = strpos($line, $marker);

            if ($pos === false) {
                continue;
            }

            $payload = trim(substr($line, $pos + strlen($marker)));

            if ($payload === '') {
                continue;
            }

            if (preg_match('/^packet:/', $payload) === 1 || in_array($payload, ['version 1', 'version 2'], true)) {
                continue;
            }

            $lines[] = preg_replace('/agent=[^ ]+/', 'agent=<normalized>', $payload) ?? $payload;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function normalizeSmartHttpAdvertisement(string $response): array
    {
        $lines = [];

        foreach (PktLine::decode($response) as $line) {
            if ($line === null) {
                $lines[] = '0000';
                continue;
            }

            if (!is_string($line)) {
                continue;
            }

            $normalized = str_replace("\0", '\\0', $line);
            $normalized = preg_replace('/agent=[^ ]+/', 'agent=<normalized>', $normalized) ?? $normalized;
            $lines[] = $normalized;
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function normalizeGitAdvertisement(string $response): array
    {
        $lines = [];

        foreach (PktLine::decode($response) as $line) {
            if ($line === null) {
                $lines[] = '0000';
                continue;
            }

            if (!is_string($line)) {
                continue;
            }

            $normalized = str_replace("\0", '\\0', $line);
            $normalized = preg_replace('/agent=[^ ]+/', 'agent=<normalized>', $normalized) ?? $normalized;
            $lines[] = $normalized;
        }

        return $lines;
    }

    private function rawGitProtocolAdvertisement(string $url): string
    {
        $parsed = GitProtocolClient::parseUrl($url);
        $socket = @stream_socket_client("tcp://{$parsed['host']}:{$parsed['port']}", $errno, $errstr, 10);

        if (!is_resource($socket)) {
            $this->fail("git:// connection failed: {$errstr}");
        }

        fwrite($socket, PktLine::encode("git-upload-pack {$parsed['path']}\0host={$parsed['host']}\0"));
        $data = '';

        while (!feof($socket)) {
            $hexLen = fread($socket, 4);

            if ($hexLen === false || strlen($hexLen) < 4) {
                break;
            }

            $data .= $hexLen;

            if ($hexLen === PktLine::FLUSH) {
                break;
            }

            $lineLen = (int) hexdec($hexLen);

            if ($lineLen < 4) {
                break;
            }

            $remaining = $lineLen - 4;
            $payload = '';

            while (strlen($payload) < $remaining) {
                $chunk = fread($socket, $remaining - strlen($payload));

                if ($chunk === false || $chunk === '') {
                    break 2;
                }

                $payload .= $chunk;
            }

            $data .= $payload;
        }

        fclose($socket);

        return $data;
    }

    private function serializeDiscovery(RefDiscovery $discovery): string
    {
        $lines = [
            'head=' . ($discovery->headSymref() ?? '<detached>'),
        ];

        $capabilities = $discovery->capabilities()?->all() ?? [];
        ksort($capabilities);

        foreach ($capabilities as $name => $value) {
            if ($name === 'agent' && $value !== null) {
                $value = '<normalized>';
            }

            $lines[] = 'cap ' . $name . ($value !== null ? '=' . $value : '');
        }

        $refs = $discovery->refs();
        ksort($refs);

        foreach ($refs as $name => $id) {
            $lines[] = 'ref ' . $name . '=' . $id->hex;
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param list<string> $lines
     */
    private function serializeDiscoveryFromAdvertisement(array $lines): string
    {
        $capabilities = null;
        $refs = [];
        $headSymref = null;

        foreach ($lines as $index => $line) {
            if ($line === '0000' || str_starts_with($line, '# service=')) {
                continue;
            }

            $workingLine = str_replace('\\0', "\0", $line);

            if (str_contains($workingLine, "\0")) {
                [$refPart, $capPart] = explode("\0", $workingLine, 2);
                $line = $refPart;
                $capabilities = Capability::parse($capPart);
                $symref = $capabilities->get('symref');

                if ($symref !== null && str_starts_with($symref, 'HEAD:')) {
                    $headSymref = substr($symref, 5);
                }
            }

            $parts = explode(' ', $line, 2);

            if (count($parts) !== 2 || strlen($parts[0]) !== 40 || !ctype_xdigit($parts[0])) {
                continue;
            }

            $refs[$parts[1]] = \Pitmaster\Object\ObjectId::fromHex($parts[0]);
        }

        return $this->serializeDiscovery(RefDiscovery::fromParsed($refs, $capabilities, $headSymref));
    }
}
