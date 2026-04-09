<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pack\PackEnumerator;
use Pitmaster\Pack\PackFile;
use Pitmaster\Pack\PackIndex;

final class PackEnumerationParityTest extends TestCase
{
    #[Test]
    public function dulwichV1PackIndexMatchesGitShowIndex(): void
    {
        $idxPath = dirname(__DIR__, 2) . '/fixtures/upstream/dulwich/testdata/packs/pack-bc63ddad95e7321ee734ea11a7a62d314e0d7481.idx';
        $expected = $this->gitShowIndexHashes($idxPath);
        $index = PackIndex::open($idxPath);

        $this->assertNotSame([], $expected);
        $this->assertSame($expected, $index->allHashes());
        $this->assertSame(count($expected), $index->objectCount());
    }

    #[Test]
    public function dulwichV1PackEnumerationMatchesGitShowIndex(): void
    {
        $packDir = dirname(__DIR__, 2) . '/fixtures/upstream/dulwich/testdata/packs';
        $packPath = $packDir . '/pack-bc63ddad95e7321ee734ea11a7a62d314e0d7481.pack';
        $idxPath = $packDir . '/pack-bc63ddad95e7321ee734ea11a7a62d314e0d7481.idx';
        $expected = $this->gitShowIndexHashes($idxPath);
        $enumerator = new PackEnumerator(PackFile::open($packPath, $idxPath));

        $actual = [];

        foreach ($enumerator->enumerate() as $object) {
            $actual[] = $object->id->hex;
        }

        sort($actual);
        sort($expected);

        $this->assertSame($expected, $actual);
    }

    /**
     * @return list<string>
     */
    private function gitShowIndexHashes(string $idxPath): array
    {
        exec(
            sprintf('git show-index < %s 2>&1', escapeshellarg($idxPath)),
            $output,
            $exitCode,
        );

        if ($exitCode !== 0) {
            self::fail("git show-index failed for {$idxPath}:\n" . implode("\n", $output));
        }

        $hashes = [];

        foreach ($output as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];

            if (isset($parts[1]) && strlen($parts[1]) === 40 && ctype_xdigit($parts[1])) {
                $hashes[] = strtolower($parts[1]);
            }
        }

        return $hashes;
    }
}
