<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Protocol;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Object\ObjectId;
use Pitmaster\Protocol\PktLine;
use Pitmaster\Protocol\ProtocolV1;

final class ProtocolV1Test extends TestCase
{
    #[Test]
    public function testBuildFetchRequestSingleWant(): void
    {
        $want = ObjectId::fromHex(str_repeat('a', 40));

        $request = ProtocolV1::buildFetchRequest([$want]);

        // First want line includes capabilities
        $decoded = PktLine::decode($request);

        // First decoded line should contain "want" + hash + capabilities
        $firstLine = $decoded[0];
        $this->assertStringContainsString('want ' . $want->hex, $firstLine);
        $this->assertStringContainsString('multi_ack_detailed', $firstLine);
        $this->assertStringContainsString('side-band-64k', $firstLine);
        $this->assertStringContainsString('ofs-delta', $firstLine);

        // Should contain a flush (null) and "done"
        $this->assertContains(null, $decoded);

        $doneFound = false;

        foreach ($decoded as $line) {
            if ($line === 'done') {
                $doneFound = true;
            }
        }

        $this->assertTrue($doneFound, 'Expected "done" in fetch request');
    }

    #[Test]
    public function testBuildFetchRequestMultipleWants(): void
    {
        $want1 = ObjectId::fromHex(str_repeat('a', 40));
        $want2 = ObjectId::fromHex(str_repeat('b', 40));

        $request = ProtocolV1::buildFetchRequest([$want1, $want2]);
        $decoded = PktLine::decode($request);

        // First line has capabilities, second does not
        $this->assertStringContainsString('want ' . $want1->hex, $decoded[0]);
        $this->assertStringContainsString('multi_ack_detailed', $decoded[0]);

        // Second want should not include capabilities
        $this->assertStringContainsString('want ' . $want2->hex, $decoded[1]);
        $this->assertStringNotContainsString('multi_ack_detailed', $decoded[1]);
    }

    #[Test]
    public function testBuildFetchRequestWithHaves(): void
    {
        $want = ObjectId::fromHex(str_repeat('a', 40));
        $have = ObjectId::fromHex(str_repeat('c', 40));

        $request = ProtocolV1::buildFetchRequest([$want], [$have]);
        $decoded = PktLine::decode($request);

        $haveFound = false;

        foreach ($decoded as $line) {
            if (is_string($line) && str_contains($line, 'have ' . $have->hex)) {
                $haveFound = true;
            }
        }

        $this->assertTrue($haveFound, 'Expected "have" line in fetch request');
    }

    #[Test]
    public function testBuildPushRequestSingleUpdate(): void
    {
        $oldId = ObjectId::zero();
        $newId = ObjectId::fromHex(str_repeat('f', 40));

        $updates = [
            ['old' => $oldId, 'new' => $newId, 'ref' => 'refs/heads/main'],
        ];

        $request = ProtocolV1::buildPushRequest($updates);
        $decoded = PktLine::decode($request);

        // First line contains the update and capabilities (after NUL byte)
        $firstLine = $decoded[0];
        $this->assertStringContainsString($oldId->hex, $firstLine);
        $this->assertStringContainsString($newId->hex, $firstLine);
        $this->assertStringContainsString('refs/heads/main', $firstLine);
    }

    #[Test]
    public function testBuildPushRequestMultipleUpdates(): void
    {
        $zero = ObjectId::zero();
        $hash1 = ObjectId::fromHex(str_repeat('1', 40));
        $hash2 = ObjectId::fromHex(str_repeat('2', 40));

        $updates = [
            ['old' => $zero, 'new' => $hash1, 'ref' => 'refs/heads/main'],
            ['old' => $zero, 'new' => $hash2, 'ref' => 'refs/heads/feature'],
        ];

        $request = ProtocolV1::buildPushRequest($updates);
        $decoded = PktLine::decode($request);

        // First line includes capabilities (NUL-separated in raw form)
        $this->assertStringContainsString('refs/heads/main', $decoded[0]);

        // Second line should contain the feature ref
        $this->assertStringContainsString('refs/heads/feature', $decoded[1]);
    }
}
