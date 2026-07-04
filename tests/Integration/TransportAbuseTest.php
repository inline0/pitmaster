<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Exceptions\ProtocolException;
use Pitmaster\Object\ObjectId;
use Pitmaster\Protocol\GitProtocolClient;
use Pitmaster\Protocol\PktLine;
use Pitmaster\Protocol\ReceivePackClient;
use Pitmaster\Protocol\SmartHttpClient;
use Pitmaster\Protocol\SshClient;
use Pitmaster\Protocol\UploadPackClient;
use Pitmaster\Tests\Support\Workspace;

final class TransportAbuseTest extends TestCase
{
    /** @var array<int, resource> */
    private array $processes = [];

    /** @var list<string> */
    private array $paths = [];

    private string $baseUrl = '';

    protected function setUp(): void
    {
        $this->baseUrl = $this->startPhpServer(dirname(__DIR__) . '/Fixtures/protocol_abuse_router.php');
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }

        foreach ($this->paths as $path) {
            Workspace::remove($path);
        }

        foreach (
            [
                'PITMASTER_SSH_COMMAND',
                'PITMASTER_SSH_IDENTITY_FILE',
                'PITMASTER_SSH_KNOWN_HOSTS',
                'PITMASTER_SSH_STRICT_HOST_KEY_CHECKING',
                'PITMASTER_FAKE_SSH_MODE',
            ] as $name
        ) {
            putenv($name);
        }
    }

    #[Test]
    public function pktLineDecodeRejectsInvalidHexAndOversizedFrames(): void
    {
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Invalid pkt-line length: zzzz');

        PktLine::decode('zzzz');
    }

    #[Test]
    public function pktLineReadFromStreamRejectsInvalidHexHeaders(): void
    {
        $stream = fopen('php://temp', 'r+b');
        fwrite($stream, "zzzz");
        rewind($stream);

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Invalid pkt-line length: zzzz');

        try {
            PktLine::readFromStream($stream);
        } finally {
            fclose($stream);
        }
    }

    #[Test]
    public function smartHttpDiscoveryRejectsInvalidAndTruncatedAdvertisements(): void
    {
        $client = new SmartHttpClient();

        try {
            $client->discoverRefs($this->baseUrl . '/discover-invalid-hex');
            self::fail('Expected invalid pkt-line advertisement to fail');
        } catch (ProtocolException $e) {
            self::assertStringContainsString('Invalid pkt-line length', $e->getMessage());
        }

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Truncated pkt-line');

        $client->discoverRefs($this->baseUrl . '/discover-truncated');
    }

    #[Test]
    public function uploadPackRejectsTruncatedSideBandPacketsAndMissingPackData(): void
    {
        $client = new UploadPackClient(new SmartHttpClient());
        $want = ObjectId::fromHex(str_repeat('a', 40));

        try {
            $client->fetch($this->baseUrl . '/upload-truncated', [$want]);
            self::fail('Expected truncated side-band response to fail');
        } catch (ProtocolException $e) {
            self::assertStringContainsString('Truncated side-band packet', $e->getMessage());
        }

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('did not contain pack data');

        $client->fetch($this->baseUrl . '/upload-no-pack', [$want]);
    }

    #[Test]
    public function receivePackRejectsMissingStatusAndTruncatedResponses(): void
    {
        $client = new ReceivePackClient(new SmartHttpClient());
        $updates = [[
            'old' => ObjectId::zero(),
            'new' => ObjectId::fromHex(str_repeat('b', 40)),
            'ref' => 'refs/heads/main',
        ]];

        try {
            $client->push($this->baseUrl . '/receive-missing-status', $updates, '');
            self::fail('Expected missing ref status to fail');
        } catch (ProtocolException $e) {
            self::assertStringContainsString('missing status for refs/heads/main', $e->getMessage());
        }

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Invalid pkt-line length');

        $client->push($this->baseUrl . '/receive-truncated', $updates, '');
    }

    #[Test]
    public function gitProtocolDiscoveryRejectsMalformedAdvertisements(): void
    {
        $client = new GitProtocolClient(2);
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('git:// connection failed');

        $client->discoverRefs('git://127.0.0.1:' . $this->freePort() . '/repo.git');
    }

    #[Test]
    public function gitProtocolClientClassifiesNegotiationOnlyRepliesAndStopsOnEarlyEof(): void
    {
        $client = new GitProtocolClient(2);
        $isNegotiationOnly = new \ReflectionMethod(GitProtocolClient::class, 'isNegotiationOnlyResponse');

        self::assertTrue($isNegotiationOnly->invoke($client, "0008NAK\n"));
        self::assertTrue($isNegotiationOnly->invoke($client, "0008ACK ready\n0008NAK\n"));
        self::assertFalse($isNegotiationOnly->invoke($client, 'PACKpayload'));

        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        if ($pair === false) {
            self::markTestSkipped('stream_socket_pair is unavailable on this platform');
        }

        [$reader, $writer] = $pair;
        fwrite($writer, "0008NAK\n0010");
        fclose($writer);

        $readAll = new \ReflectionMethod(GitProtocolClient::class, 'readAll');
        $response = $readAll->invoke($client, $reader);
        fclose($reader);

        self::assertSame("0008NAK\n0010", $response);
    }

    #[Test]
    public function sshTransportSurfacesCommandFailureAndTruncatedAdvertisements(): void
    {
        $script = $this->createFakeSshCommand();
        $client = new SshClient(2);
        putenv('PITMASTER_SSH_COMMAND=' . $script);
        putenv('PITMASTER_SSH_STRICT_HOST_KEY_CHECKING=no');

        putenv('PITMASTER_FAKE_SSH_MODE=stderr-failure');

        try {
            $client->discoverRefs('ssh://git@example.com/repo.git');
            self::fail('Expected stderr-only ssh failure to propagate');
        } catch (ProtocolException $e) {
            self::assertStringContainsString('forced ssh failure', $e->getMessage());
        }

        putenv('PITMASTER_FAKE_SSH_MODE=truncated-upload-pack-advertisement');

        try {
            $client->discoverRefs('ssh://git@example.com/repo.git');
            self::fail('Expected truncated upload-pack advertisement to fail');
        } catch (ProtocolException $e) {
            self::assertStringContainsString('Truncated pkt-line', $e->getMessage());
        }

        putenv('PITMASTER_FAKE_SSH_MODE=truncated-receive-pack-advertisement');
        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('Truncated SSH receive-pack advertisement');

        $client->receivePack('ssh://git@example.com/repo.git', "0000");
    }

    private function startPhpServer(string $router): string
    {
        $dir = $this->createDirectory('transport-abuse-http-');
        $port = $this->freePort();
        $stdout = $dir . '/server.out.log';
        $stderr = $dir . '/server.err.log';
        $command = sprintf('%s -S 127.0.0.1:%d %s', escapeshellarg(PHP_BINARY), $port, escapeshellarg($router));
        $process = proc_open(
            $command,
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', $stdout, 'a'],
                2 => ['file', $stderr, 'a'],
            ],
            $pipes,
            dirname(__DIR__, 2),
        );

        if (!is_resource($process)) {
            self::fail('Failed to start protocol abuse server');
        }

        $this->processes[] = $process;
        $baseUrl = "http://127.0.0.1:{$port}";

        for ($i = 0; $i < 40; $i++) {
            $health = @file_get_contents($baseUrl . '/health');

            if ($health !== false) {
                return $baseUrl;
            }

            usleep(100000);
        }

        self::fail('Protocol abuse server did not become ready');
    }

    private function createFakeSshCommand(): string
    {
        $dir = $this->createDirectory('transport-abuse-ssh-');
        $script = $dir . '/fake-ssh.sh';
        file_put_contents($script, <<<'SH'
#!/usr/bin/env bash
set -euo pipefail

mode="${PITMASTER_FAKE_SSH_MODE:-}"

case "$mode" in
  stderr-failure)
    echo "forced ssh failure" >&2
    exit 1
    ;;
  truncated-upload-pack-advertisement)
    printf '0010abc'
    exit 0
    ;;
  truncated-receive-pack-advertisement)
    printf '0008NAK\n000a'
    exit 0
    ;;
esac

exit 0
SH);
        chmod($script, 0755);

        return $script;
    }

    private function createDirectory(string $prefix): string
    {
        $path = Workspace::createDirectory($prefix);
        $this->paths[] = $path;

        return $path;
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($socket === false) {
            self::fail("Failed to allocate test port: {$errstr}");
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        if ($name === false) {
            self::fail('Failed to read allocated test port');
        }

        return (int) substr((string) strrchr($name, ':'), 1);
    }
}
