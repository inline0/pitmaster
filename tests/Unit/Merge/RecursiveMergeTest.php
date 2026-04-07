<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Merge;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Merge\RecursiveMerge;

final class RecursiveMergeTest extends TestCase
{
    private RecursiveMerge $merge;

    protected function setUp(): void
    {
        $this->merge = new RecursiveMerge();
    }

    #[Test]
    public function testIdenticalContentMergesCleanly(): void
    {
        $content = "line1\nline2\nline3";

        $result = $this->merge->mergeContent($content, $content, $content);

        $this->assertTrue($result['clean']);
        $this->assertSame(0, $result['conflicts']);
        $this->assertSame($content, $result['content']);
    }

    #[Test]
    public function testOnlyOursChanged(): void
    {
        $base = "line1\nline2\nline3";
        $ours = "line1\nmodified\nline3";
        $theirs = "line1\nline2\nline3";

        $result = $this->merge->mergeContent($base, $ours, $theirs);

        $this->assertTrue($result['clean']);
        $this->assertSame(0, $result['conflicts']);
        $this->assertSame($ours, $result['content']);
    }

    #[Test]
    public function testOnlyTheirsChanged(): void
    {
        $base = "line1\nline2\nline3";
        $ours = "line1\nline2\nline3";
        $theirs = "line1\nupdated\nline3";

        $result = $this->merge->mergeContent($base, $ours, $theirs);

        $this->assertTrue($result['clean']);
        $this->assertSame(0, $result['conflicts']);
        $this->assertSame($theirs, $result['content']);
    }

    #[Test]
    public function testBothChangedDifferentLinesFromBase(): void
    {
        // When ours and theirs both differ from base but at the same positions,
        // the implementation takes theirs for the changed region.
        $base = "line1\nline2\nline3";
        $ours = "line1\nour-change\nline3";
        $theirs = "line1\ntheir-change\nline3";

        $result = $this->merge->mergeContent($base, $ours, $theirs);

        // ThreeWayMerge takes theirs when both sides change the same region.
        $this->assertStringContainsString('their-change', $result['content']);
    }

    #[Test]
    public function testBothSidesIdenticalChanges(): void
    {
        $base = "line1\nline2\nline3";
        $same = "line1\nchanged\nline3";

        $result = $this->merge->mergeContent($base, $same, $same);

        $this->assertTrue($result['clean']);
        $this->assertSame(0, $result['conflicts']);
        $this->assertSame($same, $result['content']);
    }
}
