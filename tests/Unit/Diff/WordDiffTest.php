<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Diff;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Diff\WordDiff;

final class WordDiffTest extends TestCase
{
    #[Test]
    public function identicalStringsReturnInput(): void
    {
        $text = 'hello world';

        self::assertSame($text, WordDiff::diff($text, $text));
    }

    #[Test]
    public function singleWordChangeShowsMarkers(): void
    {
        $result = WordDiff::diff('hello world', 'hello planet');

        self::assertStringContainsString('{+planet+}', $result);
        self::assertStringContainsString('[-world-]', $result);
    }

    #[Test]
    public function addedWords(): void
    {
        $result = WordDiff::diff('hello', 'hello world');

        self::assertStringContainsString('{+world+}', $result);
    }

    #[Test]
    public function removedWords(): void
    {
        $result = WordDiff::diff('hello world', 'hello');

        self::assertStringContainsString('[-world-]', $result);
    }
}
