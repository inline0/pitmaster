<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Config\GitConfig;

final class ConfigParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-config-parity-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function pitmasterParsesGitWrittenConfigLikeGit(): void
    {
        $this->git('config core.filemode false');
        $this->git('config core.logAllRefUpdates true');
        $this->git('config alias.lg "log --oneline"');
        $this->git('config remote.origin.url https://example.com/repo.git');
        $this->git('config --add remote.origin.fetch +refs/heads/*:refs/remotes/origin/*');
        $this->git('config --add remote.origin.fetch ^refs/heads/tmp');
        $this->git('config branch.main.merge refs/heads/main');

        $config = GitConfig::fromFile($this->tmpDir . '/.git/config');

        $this->assertSame(trim($this->git('config --get core.filemode')), $config->get('core.filemode'));
        $this->assertSame(trim($this->git('config --get core.logAllRefUpdates')), $config->get('core.logallrefupdates'));
        $this->assertSame(trim($this->git('config --get alias.lg')), $config->get('alias.lg'));
        $this->assertSame(trim($this->git('config --get remote.origin.url')), $config->get('remote.origin.url'));
        $this->assertSame(
            $this->gitLines('config --get-all remote.origin.fetch'),
            $config->getAll('remote.origin.fetch'),
        );
        $this->assertSame(trim($this->git('config --get branch.main.merge')), $config->get('branch.main.merge'));
        $this->assertFalse($config->getBool('core.filemode', true));
        $this->assertTrue($config->getBool('core.logallrefupdates'));
    }

    #[Test]
    public function gitReadsConfigWrittenByPitmaster(): void
    {
        $config = GitConfig::parse('');
        $config->set('core.filemode', 'false');
        $config->set('core.logallrefupdates', 'true');
        $config->set('alias.lg', 'log --oneline');
        $config->set('remote.origin.url', 'https://example.com/repo.git');
        $config->append('remote.origin.fetch', '+refs/heads/*:refs/remotes/origin/*');
        $config->append('remote.origin.fetch', '^refs/heads/tmp');
        $config->set('branch.main.merge', 'refs/heads/main');

        $path = $this->tmpDir . '/written.config';
        $config->writeToFile($path);

        $this->assertSame('false', trim($this->gitFileConfig($path, 'core.filemode')));
        $this->assertSame('true', trim($this->gitFileConfig($path, 'core.logAllRefUpdates')));
        $this->assertSame('log --oneline', trim($this->gitFileConfig($path, 'alias.lg')));
        $this->assertSame('https://example.com/repo.git', trim($this->gitFileConfig($path, 'remote.origin.url')));
        $this->assertSame(
            ['+refs/heads/*:refs/remotes/origin/*', '^refs/heads/tmp'],
            $this->gitFileConfigLines($path, 'remote.origin.fetch'),
        );
        $this->assertSame('refs/heads/main', trim($this->gitFileConfig($path, 'branch.main.merge')));
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
            $this->fail("git {$command} failed:\n{$result}");
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function gitLines(string $command): array
    {
        return array_values(array_filter(explode("\n", trim($this->git($command))), static fn (string $line): bool => $line !== ''));
    }

    private function gitFileConfig(string $path, string $key): string
    {
        return $this->git(sprintf('config --file %s --get %s', escapeshellarg($path), escapeshellarg($key)));
    }

    /**
     * @return list<string>
     */
    private function gitFileConfigLines(string $path, string $key): array
    {
        return $this->gitLines(sprintf('config --file %s --get-all %s', escapeshellarg($path), escapeshellarg($key)));
    }
}
