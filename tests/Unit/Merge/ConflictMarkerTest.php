<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Merge;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Merge\ConflictMarker;

final class ConflictMarkerTest extends TestCase
{
    #[Test]
    public function producesStandardConflictMarkers(): void
    {
        $result = ConflictMarker::mark("our content\n", "their content\n");

        self::assertStringContainsString('<<<<<<< HEAD', $result);
        self::assertStringContainsString('=======', $result);
        self::assertStringContainsString('>>>>>>> incoming', $result);
        self::assertStringContainsString('our content', $result);
        self::assertStringContainsString('their content', $result);
    }

    #[Test]
    public function usesCustomLabels(): void
    {
        $result = ConflictMarker::mark(
            "our content\n",
            "their content\n",
            oursLabel: 'feature-branch',
            theirsLabel: 'main',
        );

        self::assertStringContainsString('<<<<<<< feature-branch', $result);
        self::assertStringContainsString('>>>>>>> main', $result);
    }

    #[Test]
    public function producesDiff3MarkersWhenBaseProvided(): void
    {
        $result = ConflictMarker::mark(
            "our content\n",
            "their content\n",
            base: "base content\n",
        );

        self::assertStringContainsString('<<<<<<< HEAD', $result);
        self::assertStringContainsString('||||||| base', $result);
        self::assertStringContainsString('base content', $result);
        self::assertStringContainsString('>>>>>>> incoming', $result);
    }
}
