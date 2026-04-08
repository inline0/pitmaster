<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Merge;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Merge\ThreeWayMerge;

final class ThreeWayMergeTest extends TestCase
{
    #[Test]
    public function testIdenticalContent(): void
    {
        $result = ThreeWayMerge::merge('same', 'same', 'same');

        $this->assertSame('same', $result['content']);
        $this->assertTrue($result['clean']);
        $this->assertSame(0, $result['conflicts']);
    }

    #[Test]
    public function testOnlyOursChanged(): void
    {
        $base = "line1\nline2\nline3";
        $ours = "line1\nmodified\nline3";
        $theirs = "line1\nline2\nline3";

        $result = ThreeWayMerge::merge($base, $ours, $theirs);

        $this->assertSame($ours, $result['content']);
        $this->assertTrue($result['clean']);
        $this->assertSame(0, $result['conflicts']);
    }

    #[Test]
    public function testOnlyTheirsChanged(): void
    {
        $base = "line1\nline2\nline3";
        $ours = "line1\nline2\nline3";
        $theirs = "line1\nmodified\nline3";

        $result = ThreeWayMerge::merge($base, $ours, $theirs);

        $this->assertSame($theirs, $result['content']);
        $this->assertTrue($result['clean']);
        $this->assertSame(0, $result['conflicts']);
    }

    #[Test]
    public function testBothChangedSameWay(): void
    {
        $base = "line1\nline2\nline3";
        $ours = "line1\nchanged\nline3";
        $theirs = "line1\nchanged\nline3";

        $result = ThreeWayMerge::merge($base, $ours, $theirs);

        $this->assertSame($ours, $result['content']);
        $this->assertTrue($result['clean']);
        $this->assertSame(0, $result['conflicts']);
    }

    #[Test]
    public function testBothChangedDifferentlyProducesConflictMarkers(): void
    {
        $base = "line1\nline2\nline3";
        $ours = "line1\nours-change\nline3";
        $theirs = "line1\ntheirs-change\nline3";

        $result = ThreeWayMerge::merge($base, $ours, $theirs, 'HEAD', 'incoming');

        $this->assertFalse($result['clean']);
        $this->assertSame(1, $result['conflicts']);
        $this->assertStringContainsString('<<<<<<< HEAD', $result['content']);
        $this->assertStringContainsString('ours-change', $result['content']);
        $this->assertStringContainsString('theirs-change', $result['content']);
        $this->assertStringContainsString('>>>>>>> incoming', $result['content']);
    }

    #[Test]
    public function testBaseEqualsOursTakesTheirs(): void
    {
        $base = 'original';
        $ours = 'original';
        $theirs = 'changed';

        $result = ThreeWayMerge::merge($base, $ours, $theirs);

        $this->assertSame('changed', $result['content']);
        $this->assertTrue($result['clean']);
    }

    #[Test]
    public function testBaseEqualsTheirsTakesOurs(): void
    {
        $base = 'original';
        $ours = 'changed';
        $theirs = 'original';

        $result = ThreeWayMerge::merge($base, $ours, $theirs);

        $this->assertSame('changed', $result['content']);
        $this->assertTrue($result['clean']);
    }
}
