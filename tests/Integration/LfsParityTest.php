<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Lfs\LfsClient;
use Pitmaster\Lfs\LfsPointer;
use Pitmaster\Tests\Support\Workspace;

final class LfsParityTest extends TestCase
{
    /** @var array<int, string> */
    private array $paths = [];

    /** @var array<int, resource> */
    private array $serverProcesses = [];

    protected function tearDown(): void
    {
        foreach ($this->serverProcesses as $process) {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }

        foreach ($this->paths as $path) {
            Workspace::remove($path);
        }
    }

    #[Test]
    public function pointerSerializationMatchesGitLfsCanonicalPointer(): void
    {
        $dir = $this->createDirectory('lfs-pointer-');
        $payload = "payload\n";
        file_put_contents($dir . '/payload.bin', $payload);

        $pointer = LfsPointer::forContent($payload)->serialize();
        file_put_contents($dir . '/pointer.txt', $pointer);

        $output = $this->gitLfs($dir, 'pointer --file=payload.bin');
        $gitPointer = $this->stripGitLfsPointerBanner($output);

        $this->assertSame($gitPointer, $pointer);
        $this->gitLfs($dir, 'pointer --check --strict --file=pointer.txt');
    }

    #[Test]
    public function pitmasterClientDownloadsObjectUploadedByGitLfs(): void
    {
        $payload = "payload from git-lfs\n";
        ['baseUrl' => $baseUrl, 'storage' => $storage] = $this->startLfsServer();
        $remoteDir = $this->createDirectory('lfs-remote-');
        $workDir = $this->createDirectory('lfs-work-');

        $this->git($remoteDir, 'init --bare --initial-branch=main');
        $this->initGitLfsRepo($workDir, $baseUrl);
        file_put_contents($workDir . '/payload.bin', $payload);
        $this->git($workDir, 'add .gitattributes payload.bin');
        $this->git($workDir, 'commit -m "Add payload"');
        $this->git($workDir, 'remote add origin ' . escapeshellarg($remoteDir));
        $this->git($workDir, 'push origin main');

        $pointer = LfsPointer::parse($this->git($workDir, 'show HEAD:payload.bin'));
        $this->assertNotNull($pointer);
        $this->assertFileExists($storage . '/' . $pointer->oid);

        $client = new LfsClient($baseUrl . '/repo.git/info/lfs');
        $downloaded = $client->download($pointer->oid, $pointer->size);

        $this->assertSame($payload, $downloaded);
    }

    #[Test]
    public function gitLfsPullDownloadsObjectUploadedByPitmasterClient(): void
    {
        $payload = 'payload from pitmaster';
        $pointer = LfsPointer::forContent($payload);
        ['baseUrl' => $baseUrl] = $this->startLfsServer();
        $client = new LfsClient($baseUrl . '/repo.git/info/lfs');
        $client->upload($pointer->oid, $pointer->size, $payload);

        $remoteDir = $this->createDirectory('lfs-remote-');
        $workDir = $this->createDirectory('lfs-work-');
        $cloneDir = $this->createDirectory('lfs-clone-');

        $this->git($remoteDir, 'init --bare --initial-branch=main');
        $this->initGitLfsRepo($workDir, $baseUrl);
        file_put_contents($workDir . '/payload.bin', $pointer->serialize());
        $this->git($workDir, 'add .gitattributes payload.bin');
        $this->git($workDir, 'commit -m "Pointer commit"');
        $this->git($workDir, 'config lfs.allowincompletepush true');
        $this->git($workDir, 'remote add origin ' . escapeshellarg($remoteDir));
        $this->git($workDir, 'push origin main');

        $this->command(
            'git clone ' . escapeshellarg($remoteDir) . ' ' . escapeshellarg($cloneDir),
            dirname($cloneDir),
            ['GIT_LFS_SKIP_SMUDGE' => '1'],
        );
        $this->gitLfs($cloneDir, 'install --local');
        $this->git($cloneDir, 'config lfs.url ' . escapeshellarg($baseUrl . '/repo.git/info/lfs'));
        $this->gitLfs($cloneDir, 'pull');

        $this->assertSame($payload, file_get_contents($cloneDir . '/payload.bin'));
    }

    /**
     * @return array{baseUrl: string, storage: string}
     */
    private function startLfsServer(): array
    {
        $root = $this->createDirectory('lfs-server-');
        $storage = $root . '/storage';
        mkdir($storage, 0777, true);

        $port = $this->freePort();
        $router = getcwd() . '/tests/Fixtures/lfs_router.php';
        $process = proc_open(
            sprintf('%s -S 127.0.0.1:%d %s', escapeshellarg(PHP_BINARY), $port, escapeshellarg($router)),
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', $root . '/server.out.log', 'a'],
                2 => ['file', $root . '/server.err.log', 'a'],
            ],
            $pipes,
            null,
            ['LFS_STORAGE_DIR' => $storage],
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start LFS test server');
        }

        $this->serverProcesses[] = $process;
        $baseUrl = "http://127.0.0.1:{$port}";

        for ($i = 0; $i < 40; $i++) {
            $health = @file_get_contents($baseUrl . '/__health');

            if ($health === "ok\n") {
                return ['baseUrl' => $baseUrl, 'storage' => $storage];
            }

            usleep(100000);
        }

        throw new \RuntimeException('Timed out waiting for LFS test server to start');
    }

    private function initGitLfsRepo(string $dir, string $baseUrl): void
    {
        $this->git($dir, 'init --initial-branch=main');
        $this->git($dir, 'config user.email test@pitmaster.dev');
        $this->git($dir, 'config user.name "Test User"');
        $this->gitLfs($dir, 'install --local');
        $this->gitLfs($dir, 'track "*.bin"');
        $this->git($dir, 'config lfs.url ' . escapeshellarg($baseUrl . '/repo.git/info/lfs'));
    }

    private function git(string $dir, string $command): string
    {
        return $this->command('git ' . $command, $dir);
    }

    private function gitLfs(string $dir, string $command): string
    {
        return $this->command('git lfs ' . $command, $dir);
    }

    /**
     * @param array<string, string> $env
     */
    private function command(string $command, string $dir, array $env = []): string
    {
        $prefix = '';

        foreach ($env as $name => $value) {
            $prefix .= $name . '=' . escapeshellarg($value) . ' ';
        }

        exec(
            sprintf('cd %s && %s%s 2>&1', escapeshellarg($dir), $prefix, $command),
            $output,
            $exitCode,
        );

        if ($exitCode !== 0) {
            throw new \RuntimeException("Command failed: {$command}\n" . implode("\n", $output));
        }

        return implode("\n", $output) . ($output !== [] ? "\n" : '');
    }

    private function stripGitLfsPointerBanner(string $output): string
    {
        $parts = preg_split("/\n\n/", trim($output), 2);

        if ($parts === false || $parts === []) {
            return trim($output) . "\n";
        }

        return trim(count($parts) === 2 ? $parts[1] : $parts[0]) . "\n";
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($socket === false) {
            throw new \RuntimeException("Unable to allocate test port: {$errstr}");
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        if ($name === false) {
            throw new \RuntimeException('Unable to read allocated test port');
        }

        return (int) substr((string) strrchr($name, ':'), 1);
    }

    private function createDirectory(string $prefix): string
    {
        $path = Workspace::createDirectory($prefix);
        $this->paths[] = $path;

        return $path;
    }
}
