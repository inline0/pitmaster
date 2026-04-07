<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Pack;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Encoding\Leb128;
use Pitmaster\Exceptions\PackParseException;
use Pitmaster\Pack\DeltaApplier;

final class DeltaApplierTest extends TestCase
{
    #[Test]
    public function testApplyWithCopyAndInsert(): void
    {
        // Base object: "Hello, World!"
        $base = 'Hello, World!';

        // Build delta to produce: "Hello, PHP World!"
        // Strategy: copy "Hello, " from base (offset 0, size 7), insert "PHP ",
        // copy "World!" from base (offset 7, size 6)
        $target = 'Hello, PHP World!';

        $delta = '';

        // Source size header (LEB128)
        $delta .= Leb128::encodeUnsigned(strlen($base));
        // Target size header (LEB128)
        $delta .= Leb128::encodeUnsigned(strlen($target));

        // Copy instruction: offset=0, size=7
        // Opcode: 1 0 0 1 0 0 0 1 = 0x91 (bit 7: copy, bit 4: size byte present, bit 0: offset byte present)
        $delta .= chr(0x91);
        $delta .= chr(0);   // offset byte 0
        $delta .= chr(7);   // size byte 0

        // Insert instruction: "PHP " (4 bytes)
        $delta .= chr(4);
        $delta .= 'PHP ';

        // Copy instruction: offset=7, size=6
        $delta .= chr(0x91);
        $delta .= chr(7);   // offset byte 0
        $delta .= chr(6);   // size byte 0

        $result = DeltaApplier::apply($base, $delta);
        $this->assertSame($target, $result);
    }

    #[Test]
    public function testApplyPureCopy(): void
    {
        $base = 'ABCDEFGHIJ';
        $target = 'ABCDEFGHIJ';

        $delta = '';
        $delta .= Leb128::encodeUnsigned(strlen($base));
        $delta .= Leb128::encodeUnsigned(strlen($target));

        // Copy all 10 bytes from offset 0
        $delta .= chr(0x91); // copy, offset byte 0, size byte 0
        $delta .= chr(0);    // offset = 0
        $delta .= chr(10);   // size = 10

        $result = DeltaApplier::apply($base, $delta);
        $this->assertSame($target, $result);
    }

    #[Test]
    public function testApplyPureInsert(): void
    {
        $base = '';
        $target = 'New content';

        $delta = '';
        $delta .= Leb128::encodeUnsigned(0);  // source size = 0
        $delta .= Leb128::encodeUnsigned(strlen($target));

        // Insert entire target
        $delta .= chr(strlen($target));
        $delta .= $target;

        $result = DeltaApplier::apply($base, $delta);
        $this->assertSame($target, $result);
    }

    #[Test]
    public function testApplySourceSizeMismatchThrows(): void
    {
        $base = 'Hello';

        $delta = '';
        $delta .= Leb128::encodeUnsigned(999); // wrong source size
        $delta .= Leb128::encodeUnsigned(5);

        $this->expectException(PackParseException::class);
        DeltaApplier::apply($base, $delta);
    }

    #[Test]
    public function testApplyTargetSizeMismatchThrows(): void
    {
        $base = 'Hello';

        $delta = '';
        $delta .= Leb128::encodeUnsigned(5);  // correct source size
        $delta .= Leb128::encodeUnsigned(999); // wrong target size

        // Insert 5 bytes (will not match expected target size of 999)
        $delta .= chr(5);
        $delta .= 'World';

        $this->expectException(PackParseException::class);
        DeltaApplier::apply($base, $delta);
    }

    #[Test]
    public function testApplyZeroOpcodeThrows(): void
    {
        $base = 'Hello';

        $delta = '';
        $delta .= Leb128::encodeUnsigned(5);
        $delta .= Leb128::encodeUnsigned(5);
        $delta .= chr(0); // reserved zero opcode

        $this->expectException(PackParseException::class);
        DeltaApplier::apply($base, $delta);
    }
}
