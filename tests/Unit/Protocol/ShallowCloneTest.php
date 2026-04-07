<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Protocol;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\ObjectId;
use Pitmaster\Protocol\ShallowClone;

final class ShallowCloneTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-shallow-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    #[Test]
    public function testWriteAndReadShallowRoundtrip(): void
    {
        $commits = [
            ObjectId::fromHex(str_repeat('a', 40)),
            ObjectId::fromHex(str_repeat('b', 40)),
        ];

        ShallowClone::writeShallow($this->tmpDir, $commits);
        $result = ShallowClone::readShallow($this->tmpDir);

        $this->assertCount(2, $result);
        $this->assertSame(str_repeat('a', 40), $result[0]->hex);
        $this->assertSame(str_repeat('b', 40), $result[1]->hex);
    }

    #[Test]
    public function testIsShallowReturnsTrueWhenFileExists(): void
    {
        $commits = [ObjectId::fromHex(str_repeat('c', 40))];
        ShallowClone::writeShallow($this->tmpDir, $commits);

        $this->assertTrue(ShallowClone::isShallow($this->tmpDir));
    }

    #[Test]
    public function testIsShallowReturnsFalseWhenNoFile(): void
    {
        $this->assertFalse(ShallowClone::isShallow($this->tmpDir));
    }

    #[Test]
    public function testReadShallowReturnsEmptyWhenNoFile(): void
    {
        $result = ShallowClone::readShallow($this->tmpDir);

        $this->assertSame([], $result);
    }

    #[Test]
    public function testWriteEmptyCommitsRemovesFile(): void
    {
        $commits = [ObjectId::fromHex(str_repeat('d', 40))];
        ShallowClone::writeShallow($this->tmpDir, $commits);
        $this->assertTrue(ShallowClone::isShallow($this->tmpDir));

        ShallowClone::writeShallow($this->tmpDir, []);
        $this->assertFalse(ShallowClone::isShallow($this->tmpDir));
    }

    #[Test]
    public function testBuildDeepenRequestFormat(): void
    {
        $request = ShallowClone::buildDeepenRequest(5);

        // pkt-line format: 4 hex digits for length + payload
        // "deepen 5\n" = 9 bytes payload + 4 bytes length header = 13 = 000d
        $this->assertStringContainsString('deepen 5', $request);
        $this->assertSame('000d', substr($request, 0, 4));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
