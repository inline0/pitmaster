<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Ref;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Ref\SymbolicRef;

final class SymbolicRefTest extends TestCase
{
    #[Test]
    public function parseSymbolicRefReturnsTarget(): void
    {
        $ref = SymbolicRef::parse('HEAD', "ref: refs/heads/main\n");

        self::assertNotNull($ref);
        self::assertSame('HEAD', $ref->name);
        self::assertSame('refs/heads/main', $ref->target);
    }

    #[Test]
    public function parseDirectHashReturnsNull(): void
    {
        $hash = str_repeat('a', 40);
        $ref = SymbolicRef::parse('HEAD', "{$hash}\n");

        self::assertNull($ref);
    }

    #[Test]
    public function encodeProducesRefTarget(): void
    {
        $ref = new SymbolicRef('HEAD', 'refs/heads/main');

        self::assertSame("ref: refs/heads/main\n", $ref->encode());
    }
}
