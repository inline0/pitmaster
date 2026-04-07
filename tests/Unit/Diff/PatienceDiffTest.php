<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Diff;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Diff\PatienceDiff;

final class PatienceDiffTest extends TestCase
{
    #[Test]
    public function testIdenticalStringsProduceNoHunks(): void
    {
        $text = "line1\nline2\nline3\n";

        $hunks = PatienceDiff::diff($text, $text);

        $this->assertSame([], $hunks);
    }

    #[Test]
    public function testSimpleChange(): void
    {
        $old = "alpha\nbeta\ngamma\n";
        $new = "alpha\ndelta\ngamma\n";

        $hunks = PatienceDiff::diff($old, $new);

        $this->assertCount(1, $hunks);

        $lines = $hunks[0]->lines;
        $this->assertContains('-beta', $lines);
        $this->assertContains('+delta', $lines);
    }

    #[Test]
    public function testAddLines(): void
    {
        $old = "first\nlast\n";
        $new = "first\nmiddle\nlast\n";

        $hunks = PatienceDiff::diff($old, $new);

        $this->assertNotEmpty($hunks);

        $insertedLines = [];

        foreach ($hunks[0]->lines as $line) {
            if (str_starts_with($line, '+')) {
                $insertedLines[] = $line;
            }
        }

        $this->assertContains('+middle', $insertedLines);
    }

    #[Test]
    public function testEmptyToContent(): void
    {
        $hunks = PatienceDiff::diff('', "added\n");

        $this->assertNotEmpty($hunks);
        $this->assertContains('+added', $hunks[0]->lines);
    }

    #[Test]
    public function testContentToEmpty(): void
    {
        $hunks = PatienceDiff::diff("removed\n", '');

        $this->assertNotEmpty($hunks);
        $this->assertContains('-removed', $hunks[0]->lines);
    }
}
