<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Merge;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Merge\OctopusMerge;

final class OctopusMergeTest extends TestCase
{
    #[Test]
    public function cleanMergeOfNonConflictingContents(): void
    {
        // Base has line A, branch1 keeps it, branch2 keeps it.
        // No conflicts because theirs matches base (trivial merge).
        $base = "line A\n";
        $result = OctopusMerge::merge($base, [$base, $base]);

        self::assertTrue($result['clean']);
        self::assertSame($base, $result['content']);
    }

    #[Test]
    public function cleanMergeWithDifferentBranches(): void
    {
        // OctopusMerge uses accumulated result as both base and ours,
        // so each branch is taken sequentially when it differs.
        $base = "line A";
        $branch1 = "line B";
        $branch2 = "line C";

        $result = OctopusMerge::merge($base, [$branch1, $branch2]);

        // Each merge has base===ours, so theirs is always taken cleanly.
        // The last branch content wins.
        self::assertTrue($result['clean']);
        self::assertSame($branch2, $result['content']);
    }
}
