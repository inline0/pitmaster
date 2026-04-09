<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Exceptions\IndexParseException;
use Pitmaster\Exceptions\ObjectNotFoundException;
use Pitmaster\Pitmaster;

final class RepositoryErrorParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-error-parity-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init --initial-branch=main');
        $this->git('config user.email test@example.com');
        $this->git('config user.name Test');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function missingObjectsRaiseObjectNotFoundWhileGitRejectsThem(): void
    {
        file_put_contents($this->tmpDir . '/tracked.txt', "missing object\n");
        $this->git('add tracked.txt');
        $this->git('commit -m initial');

        $head = trim($this->git('rev-parse HEAD'));
        $objectPath = $this->tmpDir . '/.git/objects/' . substr($head, 0, 2) . '/' . substr($head, 2);
        unlink($objectPath);

        $repo = Pitmaster::open($this->tmpDir);
        $gitResult = $this->gitAllowFailure('cat-file -p ' . escapeshellarg($head));

        $this->assertNotSame(0, $gitResult['exitCode']);
        $this->expectException(ObjectNotFoundException::class);
        $repo->readObject($head);
    }

    #[Test]
    public function corruptIndexRaisesIndexParseExceptionWhileGitRejectsIt(): void
    {
        file_put_contents($this->tmpDir . '/tracked.txt', "corrupt index\n");
        $this->git('add tracked.txt');
        file_put_contents($this->tmpDir . '/.git/index', "not-an-index\n");

        $repo = Pitmaster::open($this->tmpDir);
        $gitResult = $this->gitAllowFailure('status --short');

        $this->assertNotSame(0, $gitResult['exitCode']);
        $this->expectException(IndexParseException::class);
        $repo->index();
    }

    private function git(string $command): string
    {
        exec(
            sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $command),
            $output,
            $exitCode,
        );

        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            self::fail("git {$command} failed in {$this->tmpDir}:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }

    /**
     * @return array{exitCode: int, output: string}
     */
    private function gitAllowFailure(string $command): array
    {
        exec(
            sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $command),
            $output,
            $exitCode,
        );

        return [
            'exitCode' => $exitCode,
            'output' => implode("\n", $output),
        ];
    }
}
