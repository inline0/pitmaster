<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Status\Fsmonitor;

final class ProcessFreeOptionsTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-process-free-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removePath($this->tmpDir);
    }

    #[Test]
    public function processFreeOptionsDisableHooksAndExposeTheRepositoryPolicy(): void
    {
        $repo = Pitmaster::init($this->tmpDir . '/repo', options: Pitmaster::processFreeOptions());

        $this->assertFalse($repo->processesEnabled());
        $this->assertFalse($repo->hooksEnabled());
    }

    #[Test]
    public function processFreeRepositoriesBlockNetworkFetchAndPushBeforeOpeningTransports(): void
    {
        $repo = Pitmaster::init($this->tmpDir . '/repo', options: ['processes' => false]);
        $config = $repo->config();
        $config->set('remote.origin.url', 'ssh://example.invalid/repo.git');
        $config->writeToFile($repo->commonGitDir() . '/config');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot fetch: process-free repositories disable network operations.');
        $repo->fetch();
    }

    #[Test]
    public function processFreeRepositoriesBlockPushOperations(): void
    {
        $repo = Pitmaster::init($this->tmpDir . '/repo', options: ['processes' => false]);
        $config = $repo->config();
        $config->set('remote.origin.url', 'ssh://example.invalid/repo.git');
        $config->writeToFile($repo->commonGitDir() . '/config');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot push: process-free repositories disable network operations.');
        $repo->pushMirror();
    }

    #[Test]
    public function processFreeCloneIsRejectedWithoutCreatingTargetState(): void
    {
        $target = $this->tmpDir . '/clone';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot clone: process-free repositories disable network operations.');

        try {
            Pitmaster::clone('https://example.invalid/repo.git', $target, options: ['processes' => false]);
        } finally {
            $this->assertFileDoesNotExist($target);
        }
    }

    #[Test]
    public function processFreeFsmonitorFallsBackWithoutRunningConfiguredHooks(): void
    {
        $repo = Pitmaster::init($this->tmpDir . '/repo', options: ['processes' => false]);
        $config = $repo->config();
        $config->set('core.fsmonitor', '.git/hooks/query-fsmonitor');
        $config->writeToFile($repo->commonGitDir() . '/config');

        $fsmonitor = new Fsmonitor($repo->commonGitDir(), $repo->workDir(), processesEnabled: false);

        $this->assertFalse($fsmonitor->isEnabled());
        $this->assertSame([], $fsmonitor->query('9999999999')['files']);
    }

    private function removePath(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);

        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->removePath($path . '/' . $entry);
        }

        @rmdir($path);
    }
}
