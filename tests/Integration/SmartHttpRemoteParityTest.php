<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Exceptions\ProtocolException;
use Pitmaster\Pitmaster;

final class SmartHttpRemoteParityTest extends TestCase
{
    private string $tmpDir;

    /** @var resource|null */
    private $server = null;

    private string $serverLog = '';
    private string $serverErrLog = '';
    private string $baseUrl = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-smart-http-' . bin2hex(random_bytes(4));
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
    public function cloneFetchAndPushRoundTripAgainstGitSmartHttp(): void
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
        $this->git('config http.receivepack true', $remoteDir);

        file_put_contents($sourceDir . '/README.md', "hello smart http\n");
        $this->git('add README.md', $sourceDir);
        $this->git('commit -m initial', $sourceDir);
        $this->git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
        $this->git('push origin main', $sourceDir);

        $this->startGitHttpBackendServer($projectRoot);
        $remoteUrl = $this->baseUrl . '/remote.git';

        $repo = Pitmaster::clone($remoteUrl, $cloneDir);
        $initialHead = trim($this->git('rev-parse refs/heads/main', $remoteDir));

        $this->assertSame("hello smart http\n", file_get_contents($cloneDir . '/README.md'));
        $this->assertSame($initialHead, trim($this->git('rev-parse HEAD', $cloneDir)));
        $this->assertSame($initialHead, trim($this->git('rev-parse refs/remotes/origin/main', $cloneDir)));

        file_put_contents($sourceDir . '/remote.txt', "from remote\n");
        $this->git('add remote.txt', $sourceDir);
        $this->git('commit -m remote-update', $sourceDir);
        $this->git('push origin main', $sourceDir);

        $repo->fetch();
        $remoteHead = trim($this->git('rev-parse refs/heads/main', $remoteDir));

        $this->assertSame($remoteHead, trim($this->git('rev-parse refs/remotes/origin/main', $cloneDir)));

        file_put_contents($cloneDir . '/pitmaster.txt', "from pitmaster push\n");
        $repo->add('pitmaster.txt');
        $localCommit = $repo->commit("Pitmaster push\n");
        $repo->push();
        $verifyDir = $this->tmpDir . '/verify';

        $this->assertSame($localCommit->hex, trim($this->git('rev-parse refs/heads/main', $remoteDir)));
        $this->assertSame("from pitmaster push\n", $this->git('show refs/heads/main:pitmaster.txt', $remoteDir));
        $this->git('fsck --full', $remoteDir);
        $this->git('clone ' . escapeshellarg($remoteUrl) . ' ' . escapeshellarg($verifyDir), $this->tmpDir);
        $this->assertSame("from pitmaster push\n", file_get_contents($verifyDir . '/pitmaster.txt'));
    }

    #[Test]
    public function cloneImportsRemoteTagsAndNoOpFetchDoesNotWriteExtraPacks(): void
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

        file_put_contents($sourceDir . '/README.md', "hello smart http\n");
        $this->git('add README.md', $sourceDir);
        $this->git('commit -m initial', $sourceDir);
        $this->git('tag v1.0', $sourceDir);
        $this->git('tag -a v1.1 -m "Release 1.1"', $sourceDir);
        $this->git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
        $this->git('push origin main --tags', $sourceDir);

        $this->startGitHttpBackendServer($projectRoot);
        $remoteUrl = $this->baseUrl . '/remote.git';

        $repo = Pitmaster::clone($remoteUrl, $cloneDir);

        $this->assertSame(['v1.0', 'v1.1'], $repo->tags());
        $this->assertSame("v1.0\nv1.1\n", $this->git('tag --list --sort=refname', $cloneDir));

        $packDir = $cloneDir . '/.git/objects/pack';
        $beforePacks = $this->packFiles($packDir);

        $repo->fetch();

        $this->assertSame($beforePacks, $this->packFiles($packDir));
    }

    #[Test]
    public function pushRejectsNonFastForwardAgainstGitSmartHttp(): void
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
        $this->git('config http.receivepack true', $remoteDir);
        $this->git('config receive.denyNonFastForwards true', $remoteDir);

        file_put_contents($sourceDir . '/README.md', "hello smart http\n");
        $this->git('add README.md', $sourceDir);
        $this->git('commit -m initial', $sourceDir);
        $this->git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
        $this->git('push origin main', $sourceDir);

        $this->startGitHttpBackendServer($projectRoot);
        $remoteUrl = $this->baseUrl . '/remote.git';
        $repo = Pitmaster::clone($remoteUrl, $cloneDir);

        file_put_contents($sourceDir . '/remote.txt', "from remote\n");
        $this->git('add remote.txt', $sourceDir);
        $this->git('commit -m remote-update', $sourceDir);
        $this->git('push origin main', $sourceDir);
        $advancedHead = trim($this->git('rev-parse refs/heads/main', $remoteDir));

        file_put_contents($cloneDir . '/pitmaster.txt', "stale push\n");
        $repo->add('pitmaster.txt');
        $repo->commit("Stale push\n");

        $this->expectException(ProtocolException::class);
        $this->expectExceptionMessage('non-fast-forward');

        try {
            $repo->push();
        } finally {
            $this->assertSame($advancedHead, trim($this->git('rev-parse refs/heads/main', $remoteDir)));
            $this->assertStringNotContainsString('pitmaster.txt', $this->git('ls-tree --name-only refs/heads/main', $remoteDir));
        }
    }

    private function startGitHttpBackendServer(string $projectRoot): void
    {
        $port = $this->findFreePort();
        $router = dirname(__DIR__) . '/Fixtures/git_http_backend_router.php';
        $this->baseUrl = "http://127.0.0.1:{$port}";
        $this->serverLog = sys_get_temp_dir() . '/pitmaster-smart-http-' . bin2hex(random_bytes(4)) . '.log';
        $this->serverErrLog = sys_get_temp_dir() . '/pitmaster-smart-http-' . bin2hex(random_bytes(4)) . '.err.log';

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

        $stderr = is_file($this->serverErrLog) ? file_get_contents($this->serverErrLog) : '';
        $this->fail('git-http-backend test server did not become ready: ' . trim((string) $stderr));
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

    /**
     * @return array<int, string>
     */
    private function packFiles(string $packDir): array
    {
        if (!is_dir($packDir)) {
            return [];
        }

        $packs = array_values(array_filter(
            scandir($packDir) ?: [],
            static fn (string $file): bool => str_ends_with($file, '.pack'),
        ));
        sort($packs);

        return $packs;
    }
}
