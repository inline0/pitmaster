<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Protocol\PktLine;
use Pitmaster\Tests\Support\Workspace;

final class OpenIssueCloneTest extends TestCase
{
    public function testIncompleteTransferFailsInsteadOfReturningAPartialCheckout(): void
    {
        $root = Workspace::createDirectory('incomplete-clone-');
        $server = null;
        try {
            $this->captureProcess('git init --initial-branch=main', $root);
            $this->captureProcess('git config user.name Test', $root);
            $this->captureProcess('git config user.email test@example.com', $root);
            file_put_contents($root . '/a.txt', "a\n");
            $this->captureProcess('git add a.txt', $root);
            $this->captureProcess('git commit -m initial', $root);
            $head = trim($this->captureProcess('git rev-parse HEAD', $root)['stdout']);
            $tree = trim($this->captureProcess('git rev-parse HEAD^{tree}', $root)['stdout']);
            $pack = $this->captureProcess('git pack-objects --stdout', $root, $head . "\n" . $tree . "\n")['stdout'];
            file_put_contents($root . '/incomplete.pack', $pack);
            file_put_contents($root . '/advertisement', PktLine::encode($head . " HEAD\0side-band-64k ofs-delta symref=HEAD:refs/heads/main\n") . PktLine::encode($head . " refs/heads/main\n") . PktLine::flush());

            $socket = stream_socket_server('tcp://127.0.0.1:0');
            self::assertIsResource($socket);
            $address = stream_socket_get_name($socket, false);
            fclose($socket);
            self::assertIsString($address);
            $router = dirname(__DIR__) . '/Fixtures/incomplete_clone_router.php';
            $server = proc_open([PHP_BINARY, '-S', $address, $router], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $root . '/server.log', 'a'], 2 => ['file', $root . '/server.err', 'a']], $pipes, $root, ['PITMASTER_TEST_INCOMPLETE_REFS' => $root . '/advertisement', 'PITMASTER_TEST_INCOMPLETE_PACK' => $root . '/incomplete.pack']);
            self::assertIsResource($server);
            $url = 'http://' . $address;
            $ready = false;
            for ($i = 0; $i < 50; $i++) {
                if (@file_get_contents($url . '/health') !== false) {
                    $ready = true;
                    break;
                }
                usleep(20_000);
            }
            self::assertTrue($ready);
            $git = $this->captureProcess('git clone ' . escapeshellarg($url . '/repo.git') . ' git-clone', $root, '', false);
            self::assertNotSame(0, $git['exit']);
            $failure = null;
            try {
                Pitmaster::clone($url . '/repo.git', $root . '/pit-clone');
            } catch (\Throwable $error) {
                $failure = $error;
            }
            self::assertInstanceOf(\Throwable::class, $failure, 'Incomplete checkout was returned as a successful clone.');
            self::assertFileDoesNotExist($root . '/pit-clone');
        } finally {
            if (is_resource($server)) {
                proc_terminate($server);
                proc_close($server);
            }
            Workspace::remove($root);
        }
    }

    /** @return array{stdout: string, stderr: string, exit: int} */
    private function captureProcess(string $command, string $dir, string $input = '', bool $successful = true): array
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $dir);
        self::assertIsResource($process);
        fwrite($pipes[0], $input);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);
        if ($successful) {
            self::assertSame(0, $exit, $stderr);
        }
        return ['stdout' => $stdout, 'stderr' => $stderr, 'exit' => $exit];
    }
}
