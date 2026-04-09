<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Exceptions\ProtocolException;
use Pitmaster\Protocol\GitProtocolClient;
use Pitmaster\Protocol\ProtocolV1;
use Pitmaster\Tests\Integration\Support\GitTestRuntime;

final class GitProtocolClientTest extends TestCase
{
    private string $tmpDir;

    /** @var resource|null */
    private $daemon = null;

    private string $daemonLog = '';
    private string $daemonErrLog = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-git-protocol-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->daemon)) {
            proc_terminate($this->daemon);
            proc_close($this->daemon);
        }

        if ($this->daemonLog !== '') {
            @unlink($this->daemonLog);
        }

        if ($this->daemonErrLog !== '') {
            @unlink($this->daemonErrLog);
        }

        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function parseUrlExtractsHostPortPath(): void
    {
        $result = GitProtocolClient::parseUrl('git://github.com/user/repo.git');

        $this->assertSame('github.com', $result['host']);
        $this->assertSame(9418, $result['port']);
        $this->assertSame('/user/repo.git', $result['path']);
    }

    #[Test]
    public function parseUrlWithCustomPort(): void
    {
        $result = GitProtocolClient::parseUrl('git://example.com:1234/path/to/repo.git');

        $this->assertSame('example.com', $result['host']);
        $this->assertSame(1234, $result['port']);
        $this->assertSame('/path/to/repo.git', $result['path']);
    }

    #[Test]
    public function parseUrlThrowsForNonGitProtocol(): void
    {
        $this->expectException(ProtocolException::class);

        GitProtocolClient::parseUrl('https://github.com/user/repo.git');
    }

    #[Test]
    public function constructorAcceptsTimeout(): void
    {
        $client = new GitProtocolClient(60);

        $this->assertInstanceOf(GitProtocolClient::class, $client);
    }

    #[Test]
    public function discoversRefsAndFetchesPackFromActualGitDaemon(): void
    {
        $exportRoot = $this->tmpDir . '/export';
        $sourceDir = $this->tmpDir . '/source';
        $remoteDir = $exportRoot . '/remote.git';

        mkdir($exportRoot, 0777, true);
        $this->git('init --initial-branch=main ' . escapeshellarg($sourceDir), $this->tmpDir);
        $this->git('init --bare --initial-branch=main ' . escapeshellarg($remoteDir), $this->tmpDir);
        $this->git('config user.email test@example.com', $sourceDir);
        $this->git('config user.name Test', $sourceDir);

        file_put_contents($sourceDir . '/README.md', "hello git daemon\n");
        $this->git('add README.md', $sourceDir);
        $this->git('commit -m initial', $sourceDir);
        $this->git('tag v1.0', $sourceDir);
        $this->git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
        $this->git('push origin main --tags', $sourceDir);

        $port = $this->startGitDaemon($exportRoot);
        $url = "git://127.0.0.1:{$port}/remote.git";
        $client = new GitProtocolClient(10);

        $discovery = $client->discoverRefs($url);
        $mainHead = trim($this->git('rev-parse refs/heads/main', $remoteDir));
        $tagId = trim($this->git('rev-parse refs/tags/v1.0', $remoteDir));
        $mainRef = $discovery->ref('refs/heads/main');

        $this->assertNotNull($mainRef);
        $this->assertSame($mainHead, $mainRef->hex);
        $this->assertSame($tagId, $discovery->ref('refs/tags/v1.0')?->hex);

        $request = ProtocolV1::buildFetchRequest([$mainRef]);
        $response = $client->uploadPack($url, $request);

        $this->assertStringContainsString('PACK', $response);
    }

    private function startGitDaemon(string $exportRoot): int
    {
        $port = $this->findFreePort();
        $this->daemonLog = sys_get_temp_dir() . '/pitmaster-git-daemon-' . bin2hex(random_bytes(4)) . '.log';
        $this->daemonErrLog = sys_get_temp_dir() . '/pitmaster-git-daemon-' . bin2hex(random_bytes(4)) . '.err.log';

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
}
