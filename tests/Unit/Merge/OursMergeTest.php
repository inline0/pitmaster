<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Merge;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Merge\OursMerge;

final class OursMergeTest extends TestCase
{
    #[Test]
    public function alwaysReturnsOursContent(): void
    {
        $result = OursMerge::merge('base content', 'our content', 'their content');

        self::assertSame('our content', $result['content']);
    }

    #[Test]
    public function alwaysClean(): void
    {
        $result = OursMerge::merge('base', 'ours', 'theirs');

        self::assertTrue($result['clean']);
        self::assertSame(0, $result['conflicts']);
    }
}
