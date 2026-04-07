<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Merge;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Merge\MergeResult;
use Pitmaster\Object\ObjectId;

final class MergeResultTest extends TestCase
{
    #[Test]
    public function testCleanMerge(): void
    {
        $commitId = ObjectId::fromHex(str_repeat('a', 40));
        $result = new MergeResult(clean: true, commitId: $commitId);

        $this->assertTrue($result->clean);
        $this->assertSame($commitId, $result->commitId);
        $this->assertSame([], $result->conflictPaths);
        $this->assertSame([], $result->mergedContents);
    }

    #[Test]
    public function testMergeWithConflicts(): void
    {
        $conflicts = ['file1.txt', 'file2.txt'];
        $merged = ['file1.txt' => "<<<<<<< HEAD\nours\n=======\ntheirs\n>>>>>>> branch\n"];

        $result = new MergeResult(
            clean: false,
            commitId: null,
            conflictPaths: $conflicts,
            mergedContents: $merged,
        );

        $this->assertFalse($result->clean);
        $this->assertNull($result->commitId);
        $this->assertSame($conflicts, $result->conflictPaths);
        $this->assertSame($merged, $result->mergedContents);
    }

    #[Test]
    public function testDefaultsForOptionalParameters(): void
    {
        $result = new MergeResult(clean: true);

        $this->assertTrue($result->clean);
        $this->assertNull($result->commitId);
        $this->assertSame([], $result->conflictPaths);
        $this->assertSame([], $result->mergedContents);
    }
}
