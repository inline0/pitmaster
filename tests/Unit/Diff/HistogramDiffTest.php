<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Diff;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Diff\HistogramDiff;

final class HistogramDiffTest extends TestCase
{
    #[Test]
    public function testIdenticalStringsProduceNoHunks(): void
    {
        $text = "line1\nline2\nline3\n";

        $hunks = HistogramDiff::diff($text, $text);

        $this->assertSame([], $hunks);
    }

    #[Test]
    public function testSimpleModification(): void
    {
        $old = "line1\nline2\nline3\n";
        $new = "line1\nchanged\nline3\n";

        $hunks = HistogramDiff::diff($old, $new);

        $this->assertNotEmpty($hunks);
        $hunk = $hunks[0];

        $hasDelete = false;
        $hasInsert = false;

        foreach ($hunk->lines as $line) {
            if (str_starts_with($line, '-')) {
                $hasDelete = true;
            }

            if (str_starts_with($line, '+')) {
                $hasInsert = true;
            }
        }

        $this->assertTrue($hasDelete, 'Expected at least one deleted line');
        $this->assertTrue($hasInsert, 'Expected at least one inserted line');
    }

    #[Test]
    public function testAddLines(): void
    {
        $old = "line1\nline2\n";
        $new = "line1\nline2\nline3\nline4\n";

        $hunks = HistogramDiff::diff($old, $new);

        $this->assertNotEmpty($hunks);

        $insertedLines = [];

        foreach ($hunks[0]->lines as $line) {
            if (str_starts_with($line, '+')) {
                $insertedLines[] = $line;
            }
        }

        $this->assertNotEmpty($insertedLines);
    }

    #[Test]
    public function testDeleteLines(): void
    {
        $old = "line1\nline2\nline3\nline4\n";
        $new = "line1\nline2\n";

        $hunks = HistogramDiff::diff($old, $new);

        $this->assertNotEmpty($hunks);

        $deletedLines = [];

        foreach ($hunks[0]->lines as $line) {
            if (str_starts_with($line, '-')) {
                $deletedLines[] = $line;
            }
        }

        $this->assertNotEmpty($deletedLines);
    }
}
