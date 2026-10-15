<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Tests\Integration\Support\GitTestRuntime;

final class IncrementalFetchParityTest extends TestCase
{
    private string $tmpDir;

    /** @var resource|null */
    private $server = null;

    private string $serverLog = '';
    private string $serverErrLog = '';
    private string $baseUrl = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-incremental-fetch-' . bin2hex(random_bytes(4));
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
    public function incrementalFetchImportsRemoteAdvanceAndSecondFetchIsNoOp(): void
    {
        [$sourceDir, $cloneDir, $remoteDir, $repo] = $this->seedClone();

        $packDir = $cloneDir . '/.git/objects/pack';
        $beforePacks = $this->packFiles($packDir);

        file_put_contents($sourceDir . '/remote.txt', "first advance\n");
        $this->git('add remote.txt', $sourceDir);
        $this->git('commit -m remote-update', $sourceDir);
        $this->git('push origin main', $sourceDir);

        $repo->fetch();

        $afterFirstFetch = $this->packFiles($packDir);
        $this->assertNotSame($beforePacks, $afterFirstFetch);
        $this->assertSame(trim($this->git('rev-parse refs/heads/main', $remoteDir)), trim($this->git('rev-parse refs/remotes/origin/main', $cloneDir)));

        $repo->fetch();

        $this->assertSame($afterFirstFetch, $this->packFiles($packDir));
        $this->assertSame(trim($this->git('rev-parse refs/heads/main', $remoteDir)), trim($this->git('rev-parse refs/remotes/origin/main', $cloneDir)));
    }

    #[Test]
    public function fetchHonorsNegativeRefspecs(): void
    {
        [$sourceDir, $cloneDir, $remoteDir, $repo] = $this->seedClone();

        $config = $repo->config();
        $config->set('remote.origin.fetch', '+refs/heads/*:refs/remotes/origin/*');
        $config->append('remote.origin.fetch', '^refs/heads/feature');
        $config->writeToFile($cloneDir . '/.git/config');
        $repo = Pitmaster::open($cloneDir);

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
        $this->assertStringContainsString('^refs/heads/feature', $this->git('config --get-all remote.origin.fetch', $cloneDir));
    }

    public static function remoteHeadPolicies(): array
    {
        return [
            'default create' => [null, null, 'absent', false, false],
            'default preserve' => [null, null, 'symbolic', false, false],
            'global always' => ['always', null, 'symbolic', false, false],
            'global always with new objects' => ['always', null, 'symbolic', true, false],
            'global never' => ['never', null, 'absent', false, false],
            'global create' => ['create', null, 'symbolic', false, false],
            'global warn' => ['warn', null, 'symbolic', false, true],
            'remote always overrides never' => ['never', 'always', 'symbolic', false, false],
            'remote never overrides always' => ['always', 'never', 'absent', false, false],
            'warn-if-not suppresses matching branch' => ['always', 'warn-if-not-next', 'symbolic', false, false],
            'warn-if-not reports other branch' => [null, 'warn-if-not-main', 'symbolic', false, true],
            'direct local head with create' => ['create', null, 'direct', false, false],
            'direct local head with always' => ['always', null, 'direct', false, false],
            'always with existing reflog' => ['always', null, 'symbolic', true, false, true],
            'create with existing reflog' => ['create', null, 'absent', false, false, true],
            'unknown global ignored' => ['invalid', null, 'absent', false, true],
            'unknown remote falls back to global' => ['always', 'invalid', 'symbolic', false, true],
        ];
    }

    #[DataProvider('remoteHeadPolicies')]
    public function testFetchRemoteHeadPoliciesMatchGit256(?string $global, ?string $remote, string $existing, bool $advance, bool $warn, bool $existingLog = false): void
    {
        preg_match('/(\d+\.\d+\.\d+)/', $this->git('--version', $this->tmpDir), $version);
        if (version_compare($version[1] ?? '0', '2.56.0', '<')) {
            self::markTestSkipped('Global followRemoteHEAD requires Git 2.56.');
        }
        [$sourceDir, $actual, $remoteDir] = $this->seedClone();
        $native = $this->tmpDir . '/native';
        $this->git('clone ' . escapeshellarg($this->baseUrl . '/remote.git') . ' ' . escapeshellarg($native), $this->tmpDir);
        foreach ([$actual, $native] as $path) {
            $this->git('config user.name Test', $path);
            $this->git('config user.email test@example.com', $path);
            if ($global !== null) {
                $this->git('config fetch.followRemoteHEAD ' . escapeshellarg($global), $path);
            }
            if ($remote !== null) {
                $this->git('config remote.origin.followRemoteHEAD ' . escapeshellarg($remote), $path);
            }
            $head = $path . '/.git/refs/remotes/origin/HEAD';
            if (is_file($head)) {
                unlink($head);
            }
            if ($existing === 'symbolic') {
                $this->git('symbolic-ref refs/remotes/origin/HEAD refs/remotes/origin/main', $path);
            } elseif ($existing === 'direct') {
                $id = trim($this->git('rev-parse HEAD', $path));
                $this->git('update-ref --no-deref refs/remotes/origin/HEAD ' . $id, $path);
            }
            $log = $path . '/.git/logs/refs/remotes/origin/HEAD';
            if (is_file($log)) {
                unlink($log);
            }
            if ($existingLog) {
                if (!is_dir(dirname($log))) {
                    mkdir(dirname($log), 0777, true);
                }
                file_put_contents($log, '');
            }
        }
        $this->git('checkout -b next', $sourceDir);
        if ($advance) {
            file_put_contents($sourceDir . '/next.txt', "new branch\n");
            $this->git('add next.txt', $sourceDir);
            $this->git('commit -m next', $sourceDir);
        }
        $this->git('push origin next', $sourceDir);
        $this->git('symbolic-ref HEAD refs/heads/next', $remoteDir);
        $this->git('fetch origin', $native);
        $warnings = [];
        Pitmaster::open($actual)->fetch(onWarning: static function (string $message) use (&$warnings): void {
            $warnings[] = $message;
        });
        $relative = '/.git/refs/remotes/origin/HEAD';
        $expected = is_file($native . $relative) ? file_get_contents($native . $relative) : null;
        $result = is_file($actual . $relative) ? file_get_contents($actual . $relative) : null;
        self::assertSame($expected, $result);
        self::assertSame($warn, $warnings !== []);
        $relative = '/.git/logs/refs/remotes/origin/HEAD';
        $normalize = static function (string $path): array {
            if (!is_file($path)) {
                return [];
            }
            return array_map(static function (string $line): string {
                return substr($line, 0, 81) . substr($line, strpos($line, "\t"));
            }, file($path, FILE_IGNORE_NEW_LINES) ?: []);
        };
        self::assertSame($normalize($native . $relative), $normalize($actual . $relative));
    }

    public static function mirrorHeadPolicies(): array
    {
        return [['create'], ['always'], ['never'], ['warn']];
    }

    #[DataProvider('mirrorHeadPolicies')]
    public function testBareMirrorHeadMatchesGit256(string $policy): void
    {
        preg_match('/(\d+\.\d+\.\d+)/', $this->git('--version', $this->tmpDir), $version);
        if (version_compare($version[1] ?? '0', '2.56.0', '<')) {
            self::markTestSkipped('Global followRemoteHEAD requires Git 2.56.');
        }
        [$sourceDir, , $remoteDir] = $this->seedClone();
        $actual = $this->tmpDir . '/actual-mirror.git';
        $native = $this->tmpDir . '/native-mirror.git';
        foreach ([$actual, $native] as $path) {
            $this->git('clone --mirror ' . escapeshellarg($this->baseUrl . '/remote.git') . ' ' . escapeshellarg($path), $this->tmpDir);
            $this->git('config fetch.followRemoteHEAD ' . $policy, $path);
        }
        $this->git('branch next', $sourceDir);
        $this->git('push origin next', $sourceDir);
        $this->git('symbolic-ref HEAD refs/heads/next', $remoteDir);
        $this->git('fetch origin', $native);
        Pitmaster::open($actual)->fetch();
        self::assertSame(file_get_contents($native . '/HEAD'), file_get_contents($actual . '/HEAD'));
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: \Pitmaster\Repository}
     */
    private function seedClone(): array
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
        file_put_contents($sourceDir . '/README.md', "incremental fetch\n");
        $this->git('add README.md', $sourceDir);
        $this->git('commit -m initial', $sourceDir);
        $this->git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
        $this->git('push origin main', $sourceDir);

        $this->startGitHttpBackendServer($projectRoot);
        $repo = Pitmaster::clone($this->baseUrl . '/remote.git', $cloneDir);

        return [$sourceDir, $cloneDir, $remoteDir, $repo];
    }

    private function startGitHttpBackendServer(string $projectRoot): void
    {
        $port = $this->findFreePort();
        $router = dirname(__DIR__) . '/Fixtures/git_http_backend_router.php';
        $this->baseUrl = "http://127.0.0.1:{$port}";
        $this->serverLog = sys_get_temp_dir() . '/pitmaster-incremental-fetch-' . bin2hex(random_bytes(4)) . '.log';
        $this->serverErrLog = sys_get_temp_dir() . '/pitmaster-incremental-fetch-' . bin2hex(random_bytes(4)) . '.err.log';

        $command = [PHP_BINARY, '-S', "127.0.0.1:{$port}", $router];

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

    /**
     * @return list<string>
     */
    private function packFiles(string $packDir): array
    {
        $files = glob($packDir . '/*') ?: [];
        sort($files);

        return array_map('basename', $files);
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
