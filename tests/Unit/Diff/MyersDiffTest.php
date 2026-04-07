<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Diff;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Diff\MyersDiff;

final class MyersDiffTest extends TestCase
{
    #[Test]
    public function testIdenticalStringsProduceNoHunks(): void
    {
        $hunks = MyersDiff::diff("line1\nline2\nline3", "line1\nline2\nline3");
        $this->assertSame([], $hunks);
    }

    #[Test]
    public function testAddLines(): void
    {
        $old = "line1\nline2";
        $new = "line1\nline2\nline3\nline4";

        $hunks = MyersDiff::diff($old, $new);
        $this->assertNotEmpty($hunks);

        // The hunks should contain insert operations for line3 and line4
        $allLines = [];
        foreach ($hunks as $hunk) {
            foreach ($hunk->lines as $line) {
                $allLines[] = $line;
            }
        }

        $added = array_filter($allLines, fn(string $l) => str_starts_with($l, '+'));
        $this->assertNotEmpty($added);
    }

    #[Test]
    public function testDeleteLines(): void
    {
        $old = "line1\nline2\nline3\nline4";
        $new = "line1\nline2";

        $hunks = MyersDiff::diff($old, $new);
        $this->assertNotEmpty($hunks);

        $allLines = [];
        foreach ($hunks as $hunk) {
            foreach ($hunk->lines as $line) {
                $allLines[] = $line;
            }
        }

        $deleted = array_filter($allLines, fn(string $l) => str_starts_with($l, '-'));
        $this->assertNotEmpty($deleted);
    }

    #[Test]
    public function testModifyLines(): void
    {
        $old = "line1\nline2\nline3";
        $new = "line1\nmodified\nline3";

        $hunks = MyersDiff::diff($old, $new);
        $this->assertNotEmpty($hunks);

        $allLines = [];
        foreach ($hunks as $hunk) {
            foreach ($hunk->lines as $line) {
                $allLines[] = $line;
            }
        }

        $deleted = array_filter($allLines, fn(string $l) => str_starts_with($l, '-'));
        $added = array_filter($allLines, fn(string $l) => str_starts_with($l, '+'));

        // A modification shows as delete + insert
        $this->assertNotEmpty($deleted);
        $this->assertNotEmpty($added);
    }

    #[Test]
    public function testEmptyOldAllAdded(): void
    {
        $hunks = MyersDiff::diff('', "line1\nline2");
        $this->assertNotEmpty($hunks);

        $allLines = [];
        foreach ($hunks as $hunk) {
            foreach ($hunk->lines as $line) {
                $allLines[] = $line;
            }
        }

        // All lines should be additions
        foreach ($allLines as $line) {
            $this->assertStringStartsWith('+', $line);
        }
    }

    #[Test]
    public function testEmptyNewAllDeleted(): void
    {
        $hunks = MyersDiff::diff("line1\nline2", '');
        $this->assertNotEmpty($hunks);

        $allLines = [];
        foreach ($hunks as $hunk) {
            foreach ($hunk->lines as $line) {
                $allLines[] = $line;
            }
        }

        // All lines should be deletions
        foreach ($allLines as $line) {
            $this->assertStringStartsWith('-', $line);
        }
    }

    #[Test]
    public function testBothEmptyNoHunks(): void
    {
        $hunks = MyersDiff::diff('', '');
        $this->assertSame([], $hunks);
    }

    #[Test]
    public function testIsBinaryWithNulByte(): void
    {
        $this->assertTrue(MyersDiff::isBinary("some\x00binary content"));
        $this->assertTrue(MyersDiff::isBinary("\x00"));
    }

    #[Test]
    public function testIsBinaryWithPlainText(): void
    {
        $this->assertFalse(MyersDiff::isBinary('plain text content'));
        $this->assertFalse(MyersDiff::isBinary(''));
    }

    #[Test]
    public function testHunkHasCorrectHeader(): void
    {
        $old = "aaa\nbbb\nccc";
        $new = "aaa\nxxx\nccc";

        $hunks = MyersDiff::diff($old, $new);
        $this->assertNotEmpty($hunks);

        $header = $hunks[0]->header();
        $this->assertStringStartsWith('@@', $header);
        $this->assertStringEndsWith('@@', $header);
    }
}
