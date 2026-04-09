<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pitmaster;

final class BisectParityTest extends TestCase
{
    /** @var list<string> */
    private array $tmpDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpDirs as $dir) {
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }

    #[Test]
    public function linearBisectLifecycleMatchesGit(): void
    {
        ['git' => $gitDir, 'pit' => $pitDir, 'bad' => $bad, 'good' => $good] = $this->createParityPair();
        $pit = Pitmaster::open($pitDir);

        $this->git($gitDir, 'bisect start ' . escapeshellarg($bad) . ' ' . escapeshellarg($good));
        $pit->bisectStart($bad, $good);

        $this->assertBisectStateMatches($gitDir, $pitDir);

        $gitCandidate = trim($this->git($gitDir, 'rev-parse HEAD'));
        $pitCandidate = $pit->head()->id->hex;
        $this->assertSame($gitCandidate, $pitCandidate);

        $this->git($gitDir, 'bisect good ' . escapeshellarg($gitCandidate));
        $pit->bisectGood($pitCandidate);

        $this->assertSame($this->git($gitDir, 'rev-parse HEAD'), $this->git($pitDir, 'rev-parse HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'BISECT_EXPECTED_REV'), $this->readGitFile($pitDir, 'BISECT_EXPECTED_REV'));
        $this->assertSame(
            $this->git($gitDir, 'for-each-ref refs/bisect --format="%(refname) %(objectname)"'),
            $this->git($pitDir, 'for-each-ref refs/bisect --format="%(refname) %(objectname)"'),
        );
        $this->assertSame($this->readGitFile($gitDir, 'BISECT_LOG'), $this->readGitFile($pitDir, 'BISECT_LOG'));

        $gitCandidate = trim($this->git($gitDir, 'rev-parse HEAD'));
        $pitCandidate = $pit->head()->id->hex;
        $this->assertSame($gitCandidate, $pitCandidate);

        $this->git($gitDir, 'bisect bad ' . escapeshellarg($gitCandidate));
        $pit->bisectBad($pitCandidate);

        $this->assertSame($this->git($gitDir, 'rev-parse HEAD'), $this->git($pitDir, 'rev-parse HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'BISECT_EXPECTED_REV'), $this->readGitFile($pitDir, 'BISECT_EXPECTED_REV'));
        $this->assertSame(
            $this->git($gitDir, 'for-each-ref refs/bisect --format="%(refname) %(objectname)"'),
            $this->git($pitDir, 'for-each-ref refs/bisect --format="%(refname) %(objectname)"'),
        );
        $this->assertSame($this->readGitFile($gitDir, 'BISECT_LOG'), $this->readGitFile($pitDir, 'BISECT_LOG'));

        $this->git($gitDir, 'bisect reset');
        $pit->bisectReset();

        $this->assertSame($this->git($gitDir, 'rev-parse HEAD'), $this->git($pitDir, 'rev-parse HEAD'));
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertBisectFilesAbsent($pitDir);
    }

    /**
     * @return array{git: string, pit: string, bad: string, good: string}
     */
    private function createParityPair(): array
    {
        $baseDir = sys_get_temp_dir() . '/pitmaster-bisect-parity-' . bin2hex(random_bytes(4));
        $gitDir = $baseDir . '-git';
        $pitDir = $baseDir . '-pit';
        $this->tmpDirs[] = $gitDir;
        $this->tmpDirs[] = $pitDir;
        mkdir($gitDir, 0777, true);
        $this->git($gitDir, 'init --initial-branch=main');
        $this->git($gitDir, 'config user.email test@pitmaster.dev');
        $this->git($gitDir, 'config user.name "Test User"');

        $good = '';

        for ($i = 1; $i <= 6; $i++) {
            file_put_contents($gitDir . '/file.txt', "version {$i}\n");
            $this->git($gitDir, 'add file.txt');
            $this->git($gitDir, 'commit -m "c' . $i . '"');

            if ($i === 1) {
                $good = trim($this->git($gitDir, 'rev-parse HEAD'));
            }
        }

        $bad = trim($this->git($gitDir, 'rev-parse HEAD'));
        exec(sprintf('cp -R %s %s', escapeshellarg($gitDir), escapeshellarg($pitDir)), $output, $exitCode);

        if ($exitCode !== 0) {
            self::fail('Failed to create parity copy');
        }

        return [
            'git' => $gitDir,
            'pit' => $pitDir,
            'bad' => $bad,
            'good' => $good,
        ];
    }

    private function assertBisectStateMatches(string $gitDir, string $pitDir): void
    {
        $this->assertSame($this->git($gitDir, 'rev-parse HEAD'), $this->git($pitDir, 'rev-parse HEAD'));
        $this->assertSame($this->git($gitDir, 'status --porcelain=v2'), $this->git($pitDir, 'status --porcelain=v2'));
        $this->assertSame($this->readGitFile($gitDir, 'HEAD'), $this->readGitFile($pitDir, 'HEAD'));
        $this->assertSame($this->readGitFile($gitDir, 'BISECT_EXPECTED_REV'), $this->readGitFile($pitDir, 'BISECT_EXPECTED_REV'));
        $this->assertSame($this->readGitFile($gitDir, 'BISECT_LOG'), $this->readGitFile($pitDir, 'BISECT_LOG'));
        $this->assertSame($this->readGitFile($gitDir, 'BISECT_START'), $this->readGitFile($pitDir, 'BISECT_START'));
        $this->assertSame($this->readGitFile($gitDir, 'BISECT_TERMS'), $this->readGitFile($pitDir, 'BISECT_TERMS'));
        $this->assertSame($this->readGitFile($gitDir, 'BISECT_ANCESTORS_OK'), $this->readGitFile($pitDir, 'BISECT_ANCESTORS_OK'));
        $this->assertSame($this->readGitFile($gitDir, 'BISECT_NAMES'), $this->readGitFile($pitDir, 'BISECT_NAMES'));
        $this->assertSame($this->git($gitDir, 'for-each-ref refs/bisect --format="%(refname) %(objectname)"'), $this->git($pitDir, 'for-each-ref refs/bisect --format="%(refname) %(objectname)"'));
    }

    private function assertBisectFilesAbsent(string $repoDir): void
    {
        foreach (
            [
                'BISECT_EXPECTED_REV',
                'BISECT_LOG',
                'BISECT_START',
                'BISECT_TERMS',
                'BISECT_ANCESTORS_OK',
                'BISECT_NAMES',
            ] as $file
        ) {
            $this->assertFileDoesNotExist($repoDir . '/.git/' . $file);
        }

        $this->assertDirectoryDoesNotExist($repoDir . '/.git/refs/bisect');
    }

    private function readGitFile(string $repoDir, string $path): ?string
    {
        $fullPath = $repoDir . '/.git/' . $path;

        if (!is_file($fullPath)) {
            return null;
        }

        return file_get_contents($fullPath);
    }

    private function git(string $repoDir, string $command): string
    {
        exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($repoDir), $command), $output, $exitCode);
        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            self::fail("git {$command} failed:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }
}
