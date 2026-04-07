<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Diff;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Diff\DiffResult;
use Pitmaster\Diff\Hunk;

final class DiffResultTest extends TestCase
{
    #[Test]
    public function formatProducesDiffGitHeader(): void
    {
        $hunk = new Hunk(1, 1, 1, 2, [' context', '+added']);
        $result = new DiffResult('file.txt', 'file.txt', [$hunk]);

        $output = $result->format();

        self::assertStringContainsString('diff --git a/file.txt b/file.txt', $output);
    }

    #[Test]
    public function formatWithBinaryFlagSaysBinaryFilesDiffer(): void
    {
        $result = new DiffResult('image.png', 'image.png', [], binary: true);

        $output = $result->format();

        self::assertStringContainsString('Binary files differ', $output);
        self::assertStringContainsString('diff --git a/image.png b/image.png', $output);
    }

    #[Test]
    public function hasChangesWithHunksReturnsTrue(): void
    {
        $hunk = new Hunk(1, 1, 1, 2, [' context', '+added']);
        $result = new DiffResult('file.txt', 'file.txt', [$hunk]);

        self::assertTrue($result->hasChanges());
    }

    #[Test]
    public function hasChangesWithNoHunksReturnsFalse(): void
    {
        $result = new DiffResult('file.txt', 'file.txt', []);

        self::assertFalse($result->hasChanges());
    }
}
