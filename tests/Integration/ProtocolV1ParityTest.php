<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Protocol\PktLine;
use Pitmaster\Protocol\ProtocolV1;
use Pitmaster\Protocol\SmartHttpClient;
use Pitmaster\Tests\Integration\Support\GitTestRuntime;

final class ProtocolV1ParityTest extends TestCase
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
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-protocol-v1-' . bin2hex(random_bytes(4));
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
    public function uploadPackRequestMatchesGitV1TraceShape(): void
    {
        $projectRoot = $this->tmpDir . '/projects';
        $sourceDir = $this->tmpDir . '/source';
        $remoteDir = $projectRoot . '/remote.git';
        $gitClone = $this->tmpDir . '/git-clone';
        $pitClone = $this->tmpDir . '/pit-clone';

        mkdir($projectRoot, 0777, true);
        $this->git('init --initial-branch=main ' . escapeshellarg($sourceDir), $this->tmpDir);
        $this->git('init --bare --initial-branch=main ' . escapeshellarg($remoteDir), $this->tmpDir);
        $this->git('config user.email test@example.com', $sourceDir);
        $this->git('config user.name Test', $sourceDir);
        file_put_contents($sourceDir . '/README.md', "hello protocol v1\n");
        $this->git('add README.md', $sourceDir);
        $this->git('commit -m initial', $sourceDir);
        $this->git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
        $this->git('push origin main', $sourceDir);

        $this->startGitHttpBackendServer($projectRoot);
        $remoteUrl = $this->baseUrl . '/remote.git';

        $gitTrace = $this->gitTrace(
            sprintf('git -c protocol.version=1 clone %s %s', escapeshellarg($remoteUrl), escapeshellarg($gitClone)),
            $this->tmpDir,
        );
        $this->clearCaptures();
        Pitmaster::clone($remoteUrl, $pitClone);

        $gitLines = array_slice($this->extractTracePayloads($gitTrace, 'fetch-pack>'), 0, 3);
        $pitLines = $this->decodeRequestLines(file_get_contents($this->capturePath('remote.git_git-upload-pack.body')) ?: '');

        $this->assertSame(['want', '0000', 'done'], $this->lineKinds($pitLines));
        $this->assertSame(['want', '0000', 'done'], $this->lineKinds($gitLines));
        $this->assertSame(
            $this->normalizeAgentLine($gitLines[0]),
            $this->normalizeAgentLine($pitLines[0]),
        );
        $this->assertSame('0000', $pitLines[1]);
        $this->assertSame('done', $pitLines[2]);
    }

    #[Test]
    public function receivePackRequestMatchesGitV1TraceShape(): void
    {
        $projectRoot = $this->tmpDir . '/projects';
        $gitSource = $this->tmpDir . '/git-source';
        $pitSource = $this->tmpDir . '/pit-source';
        $pitClone = $this->tmpDir . '/pit-clone';
        $gitRemote = $projectRoot . '/git-remote.git';
        $pitRemote = $projectRoot . '/pit-remote.git';

        mkdir($projectRoot, 0777, true);
        $this->seedHttpPushRemote($gitSource, $gitRemote);
        $this->seedHttpPushRemote($pitSource, $pitRemote);

        $this->startGitHttpBackendServer($projectRoot);

        $gitUrl = $this->baseUrl . '/git-remote.git';
        $pitUrl = $this->baseUrl . '/pit-remote.git';

        $this->git('remote set-url origin ' . escapeshellarg($gitUrl), $gitSource);
        file_put_contents($gitSource . '/git-push.txt', "git push\n");
        $this->git('add git-push.txt', $gitSource);
        $this->git('commit -m git-push', $gitSource);
        $gitExpectedOld = trim($this->git('rev-parse refs/remotes/origin/main', $gitSource));
        $gitExpectedNew = trim($this->git('rev-parse HEAD', $gitSource));
        $gitTrace = $this->gitTrace('git -c protocol.version=1 push origin main', $gitSource);

        $repo = Pitmaster::clone($pitUrl, $pitClone);
        file_put_contents($pitClone . '/pit-push.txt', "pitmaster push\n");
        $repo->add('pit-push.txt');
        $pitExpectedOld = trim($this->git('rev-parse refs/remotes/origin/main', $pitClone));
        $pitExpectedNew = $repo->commit("Pitmaster push\n")->hex;
        $repo->push();

        $gitLine = $this->extractPushUpdateLine($gitTrace);
        $pitLines = $this->decodeRequestLines($this->requestPrefix(file_get_contents($this->capturePath('pit-remote.git_git-receive-pack.body')) ?: ''));
        $pitLine = $pitLines[0] ?? '';

        [$gitOld, $gitNew, $gitRef, $gitCaps] = $this->parsePushLine($gitLine);
        [$pitOld, $pitNew, $pitRef, $pitCaps] = $this->parsePushLine($pitLine);

        $this->assertSame($gitExpectedOld, $gitOld);
        $this->assertSame($gitExpectedNew, $gitNew);
        $this->assertSame($pitExpectedOld, $pitOld);
        $this->assertSame($pitExpectedNew, $pitNew);
        $this->assertSame($gitRef, $pitRef);
        $this->assertSame($this->normalizeCapabilities($gitCaps), $this->normalizeCapabilities($pitCaps));
        $this->assertSame('0000', $pitLines[1] ?? '');
        $this->assertTrue(str_contains(file_get_contents($this->capturePath('pit-remote.git_git-receive-pack.body')) ?: '', 'PACK'));
    }

    #[Test]
    public function smartHttpRefDiscoveryMatchesGitLsRemoteSymrefs(): void
    {
        $projectRoot = $this->tmpDir . '/projects';
        $sourceDir = $this->tmpDir . '/source';
        $remoteDir = $projectRoot . '/remote.git';

        mkdir($projectRoot, 0777, true);
        $this->git('init --initial-branch=main ' . escapeshellarg($sourceDir), $this->tmpDir);
        $this->git('init --bare --initial-branch=main ' . escapeshellarg($remoteDir), $this->tmpDir);
        $this->git('config user.email test@example.com', $sourceDir);
        $this->git('config user.name Test', $sourceDir);
        file_put_contents($sourceDir . '/README.md', "hello discovery\n");
        $this->git('add README.md', $sourceDir);
        $this->git('commit -m initial', $sourceDir);
        $this->git('tag v1.0', $sourceDir);
        $this->git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
        $this->git('push origin main --tags', $sourceDir);

        $this->startGitHttpBackendServer($projectRoot);
        $remoteUrl = $this->baseUrl . '/remote.git';

        $client = new SmartHttpClient();
        $discovery = $client->discoverRefs($remoteUrl);
        $lsRemote = $this->git('ls-remote --symref ' . escapeshellarg($remoteUrl), $this->tmpDir);

        $this->assertSame('refs/heads/main', $discovery->headSymref());
        $this->assertStringContainsString("ref: refs/heads/main\tHEAD", $lsRemote);
        $this->assertSame(
            trim($this->git('rev-parse refs/heads/main', $remoteDir)),
            $discovery->ref('refs/heads/main')?->hex,
        );
        $this->assertSame(
            trim($this->git('rev-parse refs/tags/v1.0', $remoteDir)),
            $discovery->ref('refs/tags/v1.0')?->hex,
        );
        $this->assertTrue($discovery->capabilities()?->has('multi_ack_detailed') ?? false);
        $this->assertTrue($discovery->capabilities()?->has('side-band-64k') ?? false);
        $this->assertSame('HEAD:refs/heads/main', $discovery->capabilities()?->get('symref'));
    }

    private function seedHttpPushRemote(string $sourceDir, string $remoteDir): void
    {
        $this->git('init --initial-branch=main ' . escapeshellarg($sourceDir), $this->tmpDir);
        $this->git('init --bare --initial-branch=main ' . escapeshellarg($remoteDir), $this->tmpDir);
        $this->git('config --file ' . escapeshellarg($remoteDir . '/config') . ' http.receivepack true', $this->tmpDir);
        $this->git('config user.email test@example.com', $sourceDir);
        $this->git('config user.name Test', $sourceDir);
        file_put_contents($sourceDir . '/README.md', "seed remote\n");
        $this->git('add README.md', $sourceDir);
        $this->git('commit -m initial', $sourceDir);
        $this->git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
        $this->git('push origin main', $sourceDir);
    }

    private function startGitHttpBackendServer(string $projectRoot): void
    {
        $port = $this->findFreePort();
        $router = dirname(__DIR__) . '/Fixtures/git_http_backend_router.php';
        $this->baseUrl = "http://127.0.0.1:{$port}";
        $this->serverLog = sys_get_temp_dir() . '/pitmaster-protocol-v1-' . bin2hex(random_bytes(4)) . '.log';
        $this->serverErrLog = sys_get_temp_dir() . '/pitmaster-protocol-v1-' . bin2hex(random_bytes(4)) . '.err.log';

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
        $this->waitUntilServerReady();
    }

    private function waitUntilServerReady(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $response = @file_get_contents($this->baseUrl . '/health');

            if ($response !== false || isset($http_response_header)) {
                return;
            }

            usleep(100000);
        }

        $stderr = is_file($this->serverErrLog) ? file_get_contents($this->serverErrLog) : '';
        $this->fail('git-http-backend server did not become ready: ' . trim((string) $stderr));
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

    private function gitTrace(string $command, string $dir): string
    {
        return $this->sh(
            sprintf('GIT_TRACE_PACKET=1 %s 2>&1', $command),
            $dir,
        );
    }

    private function git(string $command, string $dir): string
    {
        return $this->sh(sprintf('git %s 2>&1', $command), $dir);
    }

    private function sh(string $command, string $dir): string
    {
        exec(
            sprintf('cd %s && %s', escapeshellarg($dir), $command),
            $output,
            $exitCode,
        );

        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->fail("Command failed in {$dir}:\n{$command}\n{$result}");
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function extractTracePayloads(string $traceOutput, string $needle): array
    {
        $lines = [];

        foreach (preg_split("/\r?\n/", $traceOutput) as $line) {
            $pos = strpos($line, $needle);

            if ($pos === false) {
                continue;
            }

            $lines[] = trim(substr($line, $pos + strlen($needle)));
        }

        return $lines;
    }

    /**
     * @return array<int, string>
     */
    private function decodeRequestLines(string $requestBody): array
    {
        $lines = [];

        foreach (PktLine::decode($requestBody) as $line) {
            $lines[] = $line === null ? '0000' : (is_string($line) ? rtrim($line, "\n") : '0001');
        }

        return $lines;
    }

    /**
     * @param array<int, string> $lines
     * @return array<int, string>
     */
    private function lineKinds(array $lines): array
    {
        return array_map(static function (string $line): string {
            if ($line === '0000') {
                return '0000';
            }

            if (str_starts_with($line, 'want ')) {
                return 'want';
            }

            if ($line === 'done') {
                return 'done';
            }

            return 'other';
        }, $lines);
    }

    private function normalizeAgentLine(string $line): string
    {
        return preg_replace('/agent=[^ ]+/', 'agent=<normalized>', $line) ?? $line;
    }

    private function extractPushUpdateLine(string $traceOutput): string
    {
        foreach (array_reverse($this->extractTracePayloads($traceOutput, 'git>')) as $line) {
            if (preg_match('/^[0-9a-f]{40} [0-9a-f]{40} refs\//', $line) === 1) {
                return $line;
            }
        }

        $this->fail("Could not find push update line in trace:\n{$traceOutput}");
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: array<int, string>}
     */
    private function parsePushLine(string $line): array
    {
        $split = preg_split('/(?:\\\\0|\0)/', $line, 2) ?: [$line];
        [$updatePart, $capsPart] = array_pad($split, 2, '');
        [$old, $new, $ref] = array_pad(explode(' ', trim($updatePart), 3), 3, '');
        $caps = preg_split('/\s+/', trim($capsPart)) ?: [];

        return [$old, $new, $ref, array_values(array_filter($caps, static fn (string $cap): bool => $cap !== ''))];
    }

    /**
     * @param array<int, string> $capabilities
     * @return array<int, string>
     */
    private function normalizeCapabilities(array $capabilities): array
    {
        return array_map(static function (string $capability): string {
            if (str_starts_with($capability, 'agent=')) {
                return 'agent=<normalized>';
            }

            return $capability;
        }, $capabilities);
    }

    private function requestPrefix(string $requestBody): string
    {
        $packPos = strpos($requestBody, 'PACK');

        if ($packPos === false) {
            return $requestBody;
        }

        return substr($requestBody, 0, $packPos);
    }

    private function capturePath(string $name): string
    {
        return $this->captureDir . '/' . $name;
    }

    private function clearCaptures(): void
    {
        foreach (glob($this->captureDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
    }
}
