<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Protocol;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\ObjectId;
use Pitmaster\Protocol\Bundle;

final class BundleTest extends TestCase
{
    #[Test]
    public function testParseV2Bundle(): void
    {
        $refHash = str_repeat('a', 40);
        $prereqHash = str_repeat('b', 40);

        $bundleData = "# v2 git bundle\n"
            . "-{$prereqHash}\n"
            . "{$refHash} refs/heads/main\n"
            . "\n"
            . "PACK_DATA_HERE";

        $bundle = Bundle::parse($bundleData);

        $refs = $bundle->refs();
        $this->assertArrayHasKey('refs/heads/main', $refs);
        $this->assertSame($refHash, $refs['refs/heads/main']->hex);

        $prereqs = $bundle->prerequisites();
        $this->assertCount(1, $prereqs);
        $this->assertSame($prereqHash, $prereqs[0]->hex);

        $this->assertSame('PACK_DATA_HERE', $bundle->packData());
    }

    #[Test]
    public function testParseV2BundleNoPrerequisites(): void
    {
        $refHash = str_repeat('c', 40);

        $bundleData = "# v2 git bundle\n"
            . "{$refHash} refs/heads/main\n"
            . "\n";

        $bundle = Bundle::parse($bundleData);

        $this->assertCount(0, $bundle->prerequisites());
        $this->assertCount(1, $bundle->refs());
        $this->assertSame('', $bundle->packData());
    }

    #[Test]
    public function testSerializeRoundtrip(): void
    {
        $refId = ObjectId::fromHex(str_repeat('a', 40));
        $prereqId = ObjectId::fromHex(str_repeat('b', 40));

        $original = Bundle::create(
            refs: ['refs/heads/main' => $refId],
            packData: 'PACKBYTES',
            prerequisites: [$prereqId],
        );

        $serialized = $original->serialize();
        $parsed = Bundle::parse($serialized);

        $this->assertSame($refId->hex, $parsed->refs()['refs/heads/main']->hex);
        $this->assertCount(1, $parsed->prerequisites());
        $this->assertSame($prereqId->hex, $parsed->prerequisites()[0]->hex);
        $this->assertSame('PACKBYTES', $parsed->packData());
    }

    #[Test]
    public function testSerializeFormat(): void
    {
        $refId = ObjectId::fromHex(str_repeat('d', 40));

        $bundle = Bundle::create(
            refs: ['refs/heads/main' => $refId],
            packData: '',
        );

        $serialized = $bundle->serialize();

        $this->assertStringStartsWith('# v2 git bundle', $serialized);
        $this->assertStringContainsString(str_repeat('d', 40) . ' refs/heads/main', $serialized);
    }

    #[Test]
    public function testMultipleRefs(): void
    {
        $hash1 = str_repeat('1', 40);
        $hash2 = str_repeat('2', 40);

        $bundleData = "# v2 git bundle\n"
            . "{$hash1} refs/heads/main\n"
            . "{$hash2} refs/tags/v1.0\n"
            . "\n";

        $bundle = Bundle::parse($bundleData);
        $refs = $bundle->refs();

        $this->assertCount(2, $refs);
        $this->assertSame($hash1, $refs['refs/heads/main']->hex);
        $this->assertSame($hash2, $refs['refs/tags/v1.0']->hex);
    }

    #[Test]
    public function testInvalidHeaderThrows(): void
    {
        $this->expectException(\RuntimeException::class);

        Bundle::parse("# v99 invalid format\nhash ref\n\n");
    }
}
