<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;
use Pitmaster\Ref\Reftable;

final class ReftableTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-reftable-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function openOnNonexistentFileReturnsNull(): void
    {
        $this->assertNull(Reftable::open($this->tmpDir . '/nonexistent-reftable'));
    }

    #[Test]
    public function openOnTooSmallFileReturnsNull(): void
    {
        $path = $this->tmpDir . '/tiny-reftable';
        file_put_contents($path, 'REFT');

        $this->assertNull(Reftable::open($path));
    }

    #[Test]
    public function openOnInvalidMagicReturnsEmptyRefs(): void
    {
        $path = $this->tmpDir . '/bad-reftable';
        file_put_contents($path, str_repeat("\x00", 100));

        $result = Reftable::open($path);

        $this->assertNotNull($result);
        $this->assertEmpty($result->refs());
        $this->assertEmpty($result->symrefs());
    }

    #[Test]
    public function parsesGitGeneratedReftableRefsAndSymrefs(): void
    {
        $repoDir = $this->tmpDir . '/repo';
        $this->gitIn($this->tmpDir, 'init --initial-branch=main --ref-format=reftable ' . escapeshellarg($repoDir));
        $this->gitIn($repoDir, 'config user.email test@pitmaster.dev');
        $this->gitIn($repoDir, 'config user.name "Test User"');
        file_put_contents($repoDir . '/tracked.txt', "tracked\n");
        $this->gitIn($repoDir, 'add tracked.txt');
        $this->gitIn($repoDir, 'commit -m initial');
        $this->gitIn($repoDir, 'branch feature');
        $this->gitIn($repoDir, 'tag -a v1 -m "Release 1"');

        $refFiles = glob($repoDir . '/.git/reftable/*.ref');
        sort($refFiles);
        $table = Reftable::open((string) end($refFiles));

        $this->assertNotNull($table);
        $this->assertSame('refs/heads/main', $table->resolveSymbolic('HEAD'));
        $this->assertSame(trim($this->gitIn($repoDir, 'rev-parse refs/heads/main')), $table->resolve('refs/heads/main')?->hex);
        $this->assertSame(trim($this->gitIn($repoDir, 'rev-parse refs/heads/feature')), $table->resolve('refs/heads/feature')?->hex);
        $this->assertSame(trim($this->gitIn($repoDir, 'rev-parse refs/tags/v1')), $table->resolve('refs/tags/v1')?->hex);

        $expectedRefs = $this->showRefMap($repoDir);
        $actualRefs = array_map(static fn ($id) => $id->hex, $table->refs());
        ksort($expectedRefs);
        ksort($actualRefs);

        $this->assertSame($expectedRefs, $actualRefs);
    }

    #[Test]
    public function pitmasterOpenUsesReftableBackendForHeadBranchesAndTags(): void
    {
        $repoDir = $this->tmpDir . '/public-repo';
        $this->gitIn($this->tmpDir, 'init --initial-branch=main --ref-format=reftable ' . escapeshellarg($repoDir));
        $this->gitIn($repoDir, 'config user.email test@pitmaster.dev');
        $this->gitIn($repoDir, 'config user.name "Test User"');
        file_put_contents($repoDir . '/tracked.txt', "tracked\n");
        $this->gitIn($repoDir, 'add tracked.txt');
        $this->gitIn($repoDir, 'commit -m initial');
        $this->gitIn($repoDir, 'branch feature');
        $this->gitIn($repoDir, 'tag -a v1 -m "Release 1"');

        $repo = Pitmaster::open($repoDir);
        $showRefMap = $this->showRefMap($repoDir);

        $this->assertSame('main', $repo->branch());
        $this->assertSame(['feature', 'main'], $repo->branches());
        $this->assertSame(['v1'], $repo->tags());
        $this->assertSame(trim($this->gitIn($repoDir, 'rev-parse HEAD')), $repo->head()->id->hex);
        $this->assertSame($showRefMap['refs/heads/main'], $repo->resolve('refs/heads/main')->hex);
        $this->assertSame($showRefMap['refs/heads/feature'], $repo->resolve('refs/heads/feature')->hex);
        $this->assertSame($showRefMap['refs/tags/v1'], $repo->resolve('refs/tags/v1')->hex);
    }

    /**
     * @return array<string, string>
     */
    private function showRefMap(string $dir): array
    {
        $lines = array_values(array_filter(explode("\n", trim($this->gitIn($dir, 'show-ref')))));
        $result = [];

        foreach ($lines as $line) {
            [$hash, $name] = explode(' ', $line, 2);
            $result[$name] = $hash;
        }

        return $result;
    }

    private function gitIn(string $dir, string $command): string
    {
        exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command), $output, $exitCode);
        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->fail("git {$command} failed:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }
}
