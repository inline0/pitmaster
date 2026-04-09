<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Diff;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Diff\ColorDiff;

final class ColorDiffTest extends TestCase
{
    #[Test]
    public function addedLinesGetGreenAnsi(): void
    {
        $result = ColorDiff::colorize("+added line");

        self::assertStringContainsString("\033[32m", $result);
        self::assertStringContainsString("+", $result);
        self::assertStringContainsString("added line", $result);
        self::assertStringContainsString("\033[m", $result);
    }

    #[Test]
    public function removedLinesGetRedAnsi(): void
    {
        $result = ColorDiff::colorize("-removed line");

        self::assertStringContainsString("\033[31m", $result);
        self::assertStringContainsString("-removed line", $result);
        self::assertStringContainsString("\033[m", $result);
    }

    #[Test]
    public function hunkHeadersGetCyanAnsi(): void
    {
        $result = ColorDiff::colorize("@@ -1,3 +1,4 @@");

        self::assertStringContainsString("\033[36m", $result);
        self::assertStringContainsString("@@ -1,3 +1,4 @@", $result);
        self::assertStringContainsString("\033[m", $result);
    }

    #[Test]
    public function plainLinesUnchanged(): void
    {
        $result = ColorDiff::colorize(" context line");

        self::assertSame(" context line", $result);
    }
}
