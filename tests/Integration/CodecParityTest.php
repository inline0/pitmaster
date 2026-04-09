<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Encoding\BinaryReader;
use Pitmaster\Encoding\Leb128;
use Pitmaster\Encoding\VarInt;
use Pitmaster\Protocol\PktLine;

final class CodecParityTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-codec-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init --initial-branch=main');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function pktLineDecodesGitUploadPackAdvertisement(): void
    {
        file_put_contents($this->tmpDir . '/tracked.txt', "tracked\n");
        $this->git('add tracked.txt');
        $this->git('commit -m initial');

        $advertisement = $this->gitBinary('upload-pack --stateless-rpc --advertise-refs .');
        $reader = new BinaryReader($advertisement);
        $firstPacketLength = hexdec($reader->read(4));
        $firstPayload = $reader->read($firstPacketLength - 4);
        $decoded = PktLine::decode($advertisement);
        $lines = array_values(array_filter($decoded, static fn ($line): bool => is_string($line)));

        $this->assertSame($firstPayload, $lines[0] . "\n");
        $this->assertSame(PktLine::encode($lines[0] . "\n"), sprintf('%04x', $firstPacketLength) . $firstPayload);
        $this->assertStringStartsWith(trim($this->git('rev-parse HEAD')) . ' HEAD', $lines[0]);

        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $advertisement);
        rewind($stream);
        $streamLines = PktLine::readFromStream($stream);
        fclose($stream);

        $this->assertSame($lines, $streamLines);
    }

    #[Test]
    public function packHeaderAndDeltaEncodingsMatchGit(): void
    {
        $this->createDeltaFriendlyHistory();
        $this->git('pack-objects --delta-base-offset --window=50 --depth=50 .git/objects/pack/codec-pack --all >/dev/null');

        $packPath = $this->singlePath('.git/objects/pack/*.pack');
        $indexPath = $this->singlePath('.git/objects/pack/*.idx');
        $verifyPack = $this->parseVerifyPack($indexPath);
        $delta = $this->firstDeltaEntry($verifyPack);

        $packData = (string) file_get_contents($packPath);
        $reader = new BinaryReader($packData);

        $this->assertSame('PACK', $reader->read(4));
        $this->assertSame(2, $reader->readUint32());
        $this->assertSame(count($verifyPack), $reader->readUint32());

        $reader->seek($delta['offset']);
        $firstByte = $reader->readByte();
        $type = ($firstByte >> 4) & 0x07;
        $initialSizeBits = $firstByte & 0x0F;
        $size = ($firstByte & 0x80) !== 0
            ? VarInt::decodePackSize($reader, $initialSizeBits)
            : $initialSizeBits;

        $this->assertSame(6, $type);
        $this->assertSame($delta['size'], $size);

        $distance = VarInt::decodeOfsOffset($reader);
        $baseOffset = $delta['offset'] - $distance;

        $this->assertSame($verifyPack[$delta['baseHash']]['offset'], $baseOffset);

        $deltaPayload = $this->inflateRaw(substr($packData, $reader->position()));
        $deltaReader = new BinaryReader($deltaPayload);
        $baseSize = Leb128::decodeUnsigned($deltaReader);
        $resultSize = Leb128::decodeUnsigned($deltaReader);

        $this->assertSame((int) trim($this->git('cat-file -s ' . escapeshellarg($delta['baseHash']))), $baseSize);
        $this->assertSame((int) trim($this->git('cat-file -s ' . escapeshellarg($delta['hash']))), $resultSize);
    }

    private function createDeltaFriendlyHistory(): void
    {
        $base = "header\n" . implode("\n", array_map(
            static fn (int $i): string => "line {$i} base",
            range(1, 300),
        )) . "\n";

        for ($i = 1; $i <= 6; $i++) {
            file_put_contents(
                $this->tmpDir . '/file.txt',
                $base . "change {$i}\n",
            );
            $this->git('add file.txt');
            $this->git('commit -m ' . escapeshellarg("commit {$i}"));
        }
    }

    /**
     * @return array<string, array{hash: string, size: int, offset: int, depth: int|null, baseHash: string|null}>
     */
    private function parseVerifyPack(string $indexPath): array
    {
        exec(
            sprintf(
                'cd %s && git verify-pack -v %s 2>&1',
                escapeshellarg($this->tmpDir),
                escapeshellarg($indexPath),
            ),
            $output,
            $exitCode,
        );

        if ($exitCode !== 0) {
            $this->fail('git verify-pack failed: ' . implode("\n", $output));
        }

        $entries = [];

        foreach ($output as $line) {
            if (
                !preg_match(
                    '/^([0-9a-f]+)\s+\w+\s+(\d+)\s+\d+\s+(\d+)(?:\s+(\d+)\s+([0-9a-f]+))?$/',
                    trim($line),
                    $matches,
                )
            ) {
                continue;
            }

            $entries[$matches[1]] = [
                'hash' => $matches[1],
                'size' => (int) $matches[2],
                'offset' => (int) $matches[3],
                'depth' => isset($matches[4]) && $matches[4] !== '' ? (int) $matches[4] : null,
                'baseHash' => isset($matches[5]) && $matches[5] !== '' ? $matches[5] : null,
            ];
        }

        return $entries;
    }

    /**
     * @param array<string, array{hash: string, size: int, offset: int, depth: int|null, baseHash: string|null}> $entries
     * @return array{hash: string, size: int, offset: int, depth: int|null, baseHash: string}
     */
    private function firstDeltaEntry(array $entries): array
    {
        foreach ($entries as $entry) {
            if ($entry['baseHash'] !== null) {
                return $entry + ['baseHash' => $entry['baseHash']];
            }
        }

        $this->fail('Expected git verify-pack to contain at least one deltified object');
    }

    private function inflateRaw(string $data): string
    {
        $context = inflate_init(ZLIB_ENCODING_RAW);
        $decoded = @inflate_add($context, $data, ZLIB_FINISH);

        if ($decoded !== false) {
            return $decoded;
        }

        $decoded = zlib_decode($data);

        if ($decoded !== false) {
            return $decoded;
        }

        $this->fail('Failed to inflate git-generated delta payload');
    }

    private function singlePath(string $pattern): string
    {
        $matches = glob($this->tmpDir . '/' . $pattern);

        if ($matches === false || count($matches) !== 1) {
            $this->fail("Expected exactly one match for {$pattern}");
        }

        return $matches[0];
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

        return $result . ($result === '' ? '' : "\n");
    }

    private function gitBinary(string $command): string
    {
        $process = proc_open(
            sprintf('cd %s && git %s', escapeshellarg($this->tmpDir), $command),
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (!is_resource($process)) {
            $this->fail("Failed to start git {$command}");
        }

        $stdout = stream_get_contents($pipes[1]) ?: '';
        $stderr = stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $this->fail("git {$command} failed:\n{$stderr}");
        }

        return $stdout;
    }
}
