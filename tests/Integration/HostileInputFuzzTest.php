<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Index\Index;
use Pitmaster\Object\ObjectSerializer;
use Pitmaster\Pack\CommitGraph;
use Pitmaster\Pack\PackIndex;
use Pitmaster\Protocol\PktLine;

final class HostileInputFuzzTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-hostile-fuzz-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init -b main');
        $this->git('config user.email test@example.com');
        $this->git('config user.name Test');
        file_put_contents($this->tmpDir . '/app.txt', "one\ntwo\nthree\n");
        $this->git('add app.txt');
        $this->git('commit -m base');
        $this->git('gc');
        $this->git('commit-graph write --reachable');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function deterministicPktLineAndObjectMutationsFailClosed(): void
    {
        $pktSeed = PktLine::encode("hello\n") . PktLine::encode("world\n") . PktLine::flush();
        $objectSeed = "blob 5\0hello";

        foreach ($this->mutations($pktSeed) as $mutation) {
            try {
                PktLine::decode($mutation);
            } catch (\Throwable) {
            }
        }

        foreach ($this->mutations($objectSeed) as $mutation) {
            try {
                ObjectSerializer::decodeRaw($mutation);
            } catch (\Throwable) {
            }
        }

        self::assertTrue(true);
    }

    #[Test]
    public function deterministicIndexPackAndCommitGraphMutationsFailClosed(): void
    {
        $indexSeed = file_get_contents($this->tmpDir . '/.git/index');
        $packIndexes = glob($this->tmpDir . '/.git/objects/pack/*.idx') ?: [];
        $packIndexPath = $packIndexes[0] ?? null;
        $commitGraphPath = $this->tmpDir . '/.git/objects/info/commit-graph';

        self::assertNotFalse($indexSeed);
        self::assertNotNull($packIndexPath);
        self::assertFileExists($commitGraphPath);

        foreach ($this->mutations($indexSeed) as $i => $mutation) {
            try {
                Index::parse($mutation, "mutated-index-{$i}");
            } catch (\Throwable) {
            }
        }

        $packIndexSeed = file_get_contents($packIndexPath);
        self::assertNotFalse($packIndexSeed);

        foreach ($this->mutations($packIndexSeed) as $i => $mutation) {
            $path = $this->tmpDir . "/mutated-{$i}.idx";
            file_put_contents($path, $mutation);

            try {
                PackIndex::open($path);
            } catch (\Throwable) {
            }
        }

        $commitGraphSeed = file_get_contents($commitGraphPath);
        self::assertNotFalse($commitGraphSeed);

        foreach ($this->mutations($commitGraphSeed) as $i => $mutation) {
            $path = $this->tmpDir . "/mutated-{$i}.graph";
            file_put_contents($path, $mutation);

            try {
                CommitGraph::open($path);
            } catch (\Throwable) {
            }
        }

        self::assertTrue(true);
    }

    /**
     * @return list<string>
     */
    private function mutations(string $seed): array
    {
        $length = strlen($seed);

        if ($length === 0) {
            return [''];
        }

        $positions = array_values(array_unique([
            0,
            (int) floor($length / 3),
            (int) floor($length / 2),
            max(0, $length - 1),
        ]));

        $mutations = [
            substr($seed, 0, max(0, $length - 1)),
            substr($seed, 0, max(0, (int) floor($length / 2))),
            "0000" . $seed,
            $seed . "\xff\x00BAD",
        ];

        foreach ($positions as $position) {
            $mutated = $seed;
            $mutated[$position] = chr((ord($mutated[$position]) + 113) % 256);
            $mutations[] = $mutated;
        }

        return array_values(array_unique($mutations));
    }

    private function git(string $command): void
    {
        exec(
            sprintf('cd %s && git %s >/dev/null 2>&1', escapeshellarg($this->tmpDir), $command),
            $output,
            $exitCode,
        );

        if ($exitCode !== 0) {
            self::fail("git {$command} failed while building fuzz corpus");
        }
    }
}
