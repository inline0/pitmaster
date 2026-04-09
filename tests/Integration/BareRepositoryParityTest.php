<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;

final class BareRepositoryParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-bare-parity-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function bareRepositoriesCanBeOpenedAndReadLikeGit(): void
    {
        [$sourceDir, $bareDir] = $this->seedBareRepository();
        $repo = Pitmaster::open($bareDir);

        $this->assertTrue($repo->isBare());
        $this->assertSame('main', $repo->branch());
        $this->assertSame(trim($this->gitBare('rev-parse HEAD', $bareDir)), $repo->head()->id->hex);
        $this->assertSame(
            $this->gitLinesBare('for-each-ref --format="%(refname:short)" refs/heads', $bareDir),
            $repo->branches(),
        );
        $this->assertSame(
            $this->gitLinesBare('log --oneline --abbrev=7 -n 20', $bareDir),
            $repo->logOneline(20),
        );

        $headTreeEntry = trim($this->gitBare('ls-tree --name-only HEAD', $bareDir));
        $this->assertStringContainsString('README.md', $headTreeEntry);
        $this->assertSame(
            rtrim($this->gitBare('show HEAD:README.md', $bareDir), "\n"),
            rtrim($repo->catFile(trim($this->gitBare('rev-parse HEAD:README.md', $bareDir))), "\n"),
        );

        $this->assertSame('', trim($this->gitBare('fsck --no-progress', $bareDir)));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function seedBareRepository(): array
    {
        $sourceDir = $this->tmpDir . '/source';
        $bareDir = $this->tmpDir . '/repo.git';

        $this->gitInDir($this->tmpDir, 'init --initial-branch=main ' . escapeshellarg($sourceDir));
        $this->gitInDir($sourceDir, 'config user.email test@example.com');
        $this->gitInDir($sourceDir, 'config user.name Test');
        file_put_contents($sourceDir . '/README.md', "bare parity\n");
        $this->gitInDir($sourceDir, 'add README.md');
        $this->gitInDir($sourceDir, 'commit -m initial');
        $this->gitInDir($sourceDir, 'checkout -b feature');
        file_put_contents($sourceDir . '/feature.txt', "feature\n");
        $this->gitInDir($sourceDir, 'add feature.txt');
        $this->gitInDir($sourceDir, 'commit -m feature');
        $this->gitInDir($sourceDir, 'checkout main');
        $this->gitInDir($this->tmpDir, 'clone --bare ' . escapeshellarg($sourceDir) . ' ' . escapeshellarg($bareDir));

        return [$sourceDir, $bareDir];
    }

    private function gitBare(string $command, string $gitDir): string
    {
        return $this->gitInDir($this->tmpDir, sprintf('--git-dir=%s %s', escapeshellarg($gitDir), $command));
    }

    /**
     * @return list<string>
     */
    private function gitLinesBare(string $command, string $gitDir): array
    {
        return array_values(array_filter(explode("\n", trim($this->gitBare($command, $gitDir))), static fn (string $line): bool => $line !== ''));
    }

    private function gitInDir(string $dir, string $command): string
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
