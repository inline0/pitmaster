<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Protocol\DumbHttpClient;

final class DumbHttpClientTest extends TestCase
{
    private string $tmpDir;

    /** @var resource|null */
    private $server = null;

    private string $serverLog = '';
    private string $serverErrLog = '';
    private string $baseUrl = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-dumb-http-' . bin2hex(random_bytes(4));
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
    public function canBeConstructedWithDefaultTimeout(): void
    {
        $client = new DumbHttpClient();

        $this->assertInstanceOf(DumbHttpClient::class, $client);
    }

    #[Test]
    public function canBeConstructedWithCustomTimeout(): void
    {
        $client = new DumbHttpClient(60);

        $this->assertInstanceOf(DumbHttpClient::class, $client);
    }

    #[Test]
    public function fetchesRefsLooseObjectsAndPacksFromActualDumbHttpExport(): void
    {
        [$sourceDir, $remoteDir] = $this->seedDumbHttpRemote('hello dumb http');

        $this->startStaticServer(dirname($remoteDir));

        $client = new DumbHttpClient();
        $remoteUrl = $this->baseUrl . '/remote.git';

        $refs = $client->fetchRefs($remoteUrl);
        $head = trim($this->git('rev-parse refs/heads/main', $remoteDir));

        $this->assertSame($head, $refs['refs/heads/main']->hex ?? null);

        $loosePath = $remoteDir . '/objects/' . substr($head, 0, 2) . '/' . substr($head, 2);
        $this->assertFileExists($loosePath);
        $this->assertSame(file_get_contents($loosePath), $client->fetchObject($remoteUrl, $head));

        $this->git('repack -ad', $remoteDir);
        $this->git('update-server-info', $remoteDir);

        $packs = $client->fetchPackList($remoteUrl);
        $this->assertNotSame([], $packs);

        $packName = $packs[0];
        $this->assertSame(
            file_get_contents($remoteDir . '/objects/pack/' . $packName),
            $client->fetchPack($remoteUrl, $packName),
        );
    }

    #[Test]
    public function cloneOverDumbHttpMatchesGitForPackedExport(): void
    {
        [$sourceDir, $remoteDir] = $this->seedDumbHttpRemote('dumb clone parity');
        $pitClone = $this->tmpDir . '/pit-clone';
        $gitClone = $this->tmpDir . '/git-clone';

        $this->git('tag -a v1.0 -m v1.0', $sourceDir);
        $this->git('push origin --tags', $sourceDir);
        $this->git('repack -ad', $remoteDir);
        $this->git('update-server-info', $remoteDir);
        $this->startStaticServer(dirname($remoteDir));
        $remoteUrl = $this->baseUrl . '/remote.git';

        Pitmaster::clone($remoteUrl, $pitClone);
        $this->git('clone ' . escapeshellarg($remoteUrl) . ' ' . escapeshellarg($gitClone), $this->tmpDir);

        $this->assertSame(trim($this->git('rev-parse HEAD', $gitClone)), trim($this->git('rev-parse HEAD', $pitClone)));
        $this->assertSame(trim($this->git('rev-parse refs/remotes/origin/main', $gitClone)), trim($this->git('rev-parse refs/remotes/origin/main', $pitClone)));
        $this->assertSame(trim($this->git('rev-parse refs/tags/v1.0', $gitClone)), trim($this->git('rev-parse refs/tags/v1.0', $pitClone)));
        $this->assertSame(file_get_contents($gitClone . '/README.md'), file_get_contents($pitClone . '/README.md'));
        $this->assertSame('', trim($this->git('fsck --no-progress', $pitClone)));
    }

    #[Test]
    public function fetchOverDumbHttpMatchesGitAfterRemoteAdvance(): void
    {
        [, $remoteDir] = $this->seedDumbHttpRemote('dumb fetch parity');
        $pitClone = $this->tmpDir . '/pit-clone';
        $gitClone = $this->tmpDir . '/git-clone';

        $this->git('repack -ad', $remoteDir);
        $this->git('update-server-info', $remoteDir);
        $this->startStaticServer(dirname($remoteDir));
        $remoteUrl = $this->baseUrl . '/remote.git';

        $repo = Pitmaster::clone($remoteUrl, $pitClone);
        $this->git('clone ' . escapeshellarg($remoteUrl) . ' ' . escapeshellarg($gitClone), $this->tmpDir);

        $sourceDir = $this->tmpDir . '/source';
        file_put_contents($sourceDir . '/README.md', "dumb fetch parity\nremote advance\n");
        file_put_contents($sourceDir . '/notes.txt', "fetched over dumb http\n");
        $this->git('add README.md notes.txt', $sourceDir);
        $this->git('commit -m advance', $sourceDir);
        $this->git('push origin main', $sourceDir);
        $this->git('repack -ad', $remoteDir);
        $this->git('update-server-info', $remoteDir);

        $repo->fetch();
        $this->git('fetch origin', $gitClone);

        $this->assertSame(trim($this->git('rev-parse refs/remotes/origin/main', $gitClone)), trim($this->git('rev-parse refs/remotes/origin/main', $pitClone)));
        $this->assertSame(trim($this->git('rev-parse refs/heads/main', $remoteDir)), trim($this->git('rev-parse refs/remotes/origin/main', $pitClone)));
        $this->assertSame('', trim($this->git('fsck --no-progress', $pitClone)));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function seedDumbHttpRemote(string $content): array
    {
        $docRoot = $this->tmpDir . '/docroot';
        $sourceDir = $this->tmpDir . '/source';
        $remoteDir = $docRoot . '/remote.git';

        mkdir($docRoot, 0777, true);
        file_put_contents($docRoot . '/health.txt', "ok\n");

        $this->git('init --initial-branch=main ' . escapeshellarg($sourceDir), $this->tmpDir);
        $this->git('init --bare --initial-branch=main ' . escapeshellarg($remoteDir), $this->tmpDir);
        $this->git('config user.email test@example.com', $sourceDir);
        $this->git('config user.name Test', $sourceDir);

        file_put_contents($sourceDir . '/README.md', $content . "\n");
        $this->git('add README.md', $sourceDir);
        $this->git('commit -m initial', $sourceDir);
        $this->git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
        $this->git('push origin main', $sourceDir);
        $this->git('update-server-info', $remoteDir);

        return [$sourceDir, $remoteDir];
    }

    private function startStaticServer(string $docRoot): void
    {
        $port = $this->findFreePort();
        $this->baseUrl = "http://127.0.0.1:{$port}";
        $this->serverLog = sys_get_temp_dir() . '/pitmaster-dumb-http-' . bin2hex(random_bytes(4)) . '.log';
        $this->serverErrLog = sys_get_temp_dir() . '/pitmaster-dumb-http-' . bin2hex(random_bytes(4)) . '.err.log';

        $command = sprintf(
            '%s -S 127.0.0.1:%d -t %s',
            escapeshellarg(PHP_BINARY),
            $port,
            escapeshellarg($docRoot),
        );

        $this->server = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['file', $this->serverLog, 'a'],
                2 => ['file', $this->serverErrLog, 'a'],
            ],
            $pipes,
            $docRoot,
        );

        if (!is_resource($this->server)) {
            $this->fail('Failed to start dumb HTTP test server');
        }

        fclose($pipes[0]);
        $this->waitUntilServerReady($this->baseUrl . '/health.txt');
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
        $this->fail('Dumb HTTP test server did not become ready: ' . trim((string) $stderr));
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
