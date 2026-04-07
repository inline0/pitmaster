<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Pack;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Pack\DeltaResolver;

final class DeltaResolverTest extends TestCase
{
    #[Test]
    public function testMaxChainDepthReturnsDefault(): void
    {
        $this->assertSame(50, DeltaResolver::maxChainDepth());
    }
}
