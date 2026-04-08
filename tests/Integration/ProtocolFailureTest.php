<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Exceptions\ProtocolException;
use Pitmaster\Object\ObjectId;
use Pitmaster\Protocol\ReceivePackClient;
use Pitmaster\Protocol\SmartHttpClient;
use Pitmaster\Protocol\UploadPackClient;

final class ProtocolFailureTest extends TestCase
{
    /** @var resource|null */
    private $server = null;

    private string $routerLog;
    private string $routerErrLog;
    private string $baseUrl;

    protected function setUp(): void
    {
        $port = $this->findFreePort();
        $router = dirname(__DIR__) . '/Fixtures/protocol_router.php';
        $this->baseUrl = "http://127.0.0.1:{$port}";
        $this->routerLog = sys_get_temp_dir() . '/pitmaster-protocol-router-' . bin2hex(random_bytes(4)) . '.log';
        $this->routerErrLog = sys_get_temp_dir() . '/pitmaster-protocol-router-' . bin2hex(random_bytes(4)) . '.err.log';

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
                1 => ['file', $this->routerLog, 'a'],
                2 => ['file', $this->routerErrLog, 'a'],
            ],
            $pipes,
            dirname(__DIR__, 2),
        );

        if (!is_resource($this->server)) {
            $this->fail('Failed to start protocol test server');
        }

        fclose($pipes[0]);
        $this->waitUntilServerReady();
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }

        @unlink($this->routerLog);
        @unlink($this->routerErrLog);
    }

    #[Test]
    public function discoverRefsRejectsUnexpectedStatus(): void
    {
        $client = new SmartHttpClient();

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Unexpected HTTP status 404');

        $client->discoverRefs($this->baseUrl . '/discover-404');
    }

    #[Test]
    public function discoverRefsRejectsUnexpectedContentType(): void
    {
        $client = new SmartHttpClient();

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Unexpected Content-Type');

        $client->discoverRefs($this->baseUrl . '/discover-wrong-type');
    }

    #[Test]
    public function discoverRefsParsesExpectedAdvertisement(): void
    {
        $client = new SmartHttpClient();
        $discovery = $client->discoverRefs($this->baseUrl . '/discover-ok');

        $this->assertSame('refs/heads/main', $discovery->headSymref());
        $this->assertSame(str_repeat('a', 40), $discovery->ref('HEAD')?->hex);
        $this->assertSame(str_repeat('a', 40), $discovery->ref('refs/heads/main')?->hex);
    }

    #[Test]
    public function uploadPackSurfacesSideBandErrors(): void
    {
        $client = new UploadPackClient(new SmartHttpClient());

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('upload-pack rejected');

        $client->fetch($this->baseUrl . '/upload-error', [ObjectId::fromHex(str_repeat('a', 40))]);
    }

    #[Test]
    public function receivePackRejectsNgStatus(): void
    {
        $client = new ReceivePackClient(new SmartHttpClient());

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('non-fast-forward');

        $client->push(
            $this->baseUrl . '/receive-ng',
            [[
                'old' => ObjectId::fromHex(str_repeat('0', 40)),
                'new' => ObjectId::fromHex(str_repeat('b', 40)),
                'ref' => 'refs/heads/main',
            ]],
            '',
        );
    }

    #[Test]
    public function receivePackAcceptsOkStatus(): void
    {
        $client = new ReceivePackClient(new SmartHttpClient());

        $response = $client->push(
            $this->baseUrl . '/receive-ok',
            [[
                'old' => ObjectId::fromHex(str_repeat('0', 40)),
                'new' => ObjectId::fromHex(str_repeat('b', 40)),
                'ref' => 'refs/heads/main',
            ]],
            '',
        );

        $this->assertStringContainsString('unpack ok', $response);
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

        $stderr = is_file($this->routerErrLog) ? file_get_contents($this->routerErrLog) : '';
        $this->fail('Protocol test server did not become ready: ' . trim((string) $stderr));
    }
}
