<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Diff;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Diff\MinimalDiff;

final class MinimalDiffTest extends TestCase
{
    #[Test]
    public function testIdenticalStringsProduceNoHunks(): void
    {
        $text = "line1\nline2\nline3\n";

        $hunks = MinimalDiff::diff($text, $text);

        $this->assertSame([], $hunks);
    }

    #[Test]
    public function testSimpleChange(): void
    {
        $old = "aaa\nbbb\nccc\n";
        $new = "aaa\nxxx\nccc\n";

        $hunks = MinimalDiff::diff($old, $new);

        $this->assertCount(1, $hunks);

        $lines = $hunks[0]->lines;
        $this->assertContains('-bbb', $lines);
        $this->assertContains('+xxx', $lines);
    }

    #[Test]
    public function testEmptyOldProducesAllInserts(): void
    {
        $hunks = MinimalDiff::diff('', "new1\nnew2\n");

        $this->assertNotEmpty($hunks);

        foreach ($hunks[0]->lines as $line) {
            $this->assertStringStartsWith('+', $line);
        }
    }

    #[Test]
    public function testEmptyNewProducesAllDeletes(): void
    {
        $hunks = MinimalDiff::diff("old1\nold2\n", '');

        $this->assertNotEmpty($hunks);

        foreach ($hunks[0]->lines as $line) {
            $this->assertStringStartsWith('-', $line);
        }
    }
}
