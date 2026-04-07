<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Status;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Status\GitIgnore;

final class GitIgnoreTest extends TestCase
{
    private function createIgnore(string ...$patterns): GitIgnore
    {
        $ignore = new GitIgnore();

        foreach ($patterns as $pattern) {
            $ignore->addPattern($pattern);
        }

        return $ignore;
    }

    #[Test]
    public function testMatchSimplePattern(): void
    {
        $ignore = $this->createIgnore('*.log');

        $this->assertTrue($ignore->isIgnored('debug.log'));
        $this->assertTrue($ignore->isIgnored('error.log'));
        $this->assertFalse($ignore->isIgnored('readme.txt'));
    }

    #[Test]
    public function testNegationPattern(): void
    {
        $ignore = $this->createIgnore('*.log', '!important.log');

        $this->assertTrue($ignore->isIgnored('debug.log'));
        $this->assertFalse($ignore->isIgnored('important.log'));
    }

    #[Test]
    public function testDirectoryOnlyPattern(): void
    {
        $ignore = $this->createIgnore('build/');

        // Directory should match
        $this->assertTrue($ignore->isIgnored('build', true));

        // File should not match a directory-only pattern
        $this->assertFalse($ignore->isIgnored('build', false));
    }

    #[Test]
    public function testGlobWithStar(): void
    {
        $ignore = $this->createIgnore('*.txt');

        $this->assertTrue($ignore->isIgnored('readme.txt'));
        $this->assertTrue($ignore->isIgnored('notes.txt'));
        $this->assertFalse($ignore->isIgnored('readme.md'));
    }

    #[Test]
    public function testAnchoredPatternWithSlash(): void
    {
        $ignore = $this->createIgnore('/vendor');

        // Anchored to root: should match top-level "vendor"
        $this->assertTrue($ignore->isIgnored('vendor'));

        // Should not match nested "vendor" since it's anchored
        // (fnmatch with FNM_PATHNAME won't match sub/vendor against "vendor")
        $this->assertFalse($ignore->isIgnored('sub/vendor'));
    }

    #[Test]
    public function testDoubleStarPattern(): void
    {
        $ignore = $this->createIgnore('**/logs');

        $this->assertTrue($ignore->isIgnored('logs'));
        $this->assertTrue($ignore->isIgnored('src/logs'));
        $this->assertTrue($ignore->isIgnored('a/b/logs'));
    }

    #[Test]
    public function testDoubleStarWithSuffix(): void
    {
        $ignore = $this->createIgnore('**/*.log');

        $this->assertTrue($ignore->isIgnored('error.log'));
        $this->assertTrue($ignore->isIgnored('src/error.log'));
        $this->assertTrue($ignore->isIgnored('a/b/c/error.log'));
    }

    #[Test]
    public function testCommentLinesAreIgnored(): void
    {
        $ignore = $this->createIgnore('# this is a comment', '*.log');

        $this->assertTrue($ignore->isIgnored('test.log'));
        // The comment line should not create a rule
        $this->assertFalse($ignore->isIgnored('# this is a comment'));
    }

    #[Test]
    public function testEmptyLinesAreIgnored(): void
    {
        $ignore = $this->createIgnore('', '*.log', '');

        $this->assertTrue($ignore->isIgnored('test.log'));
        $this->assertFalse($ignore->isIgnored('test.txt'));
    }

    #[Test]
    public function testPatternMatchesInSubdirectory(): void
    {
        $ignore = $this->createIgnore('*.o');

        // Unanchored patterns match any component
        $this->assertTrue($ignore->isIgnored('build/main.o'));
        $this->assertTrue($ignore->isIgnored('main.o'));
    }

    #[Test]
    public function testSpecificFilename(): void
    {
        $ignore = $this->createIgnore('.env');

        $this->assertTrue($ignore->isIgnored('.env'));
        $this->assertFalse($ignore->isIgnored('.env.example'));
    }

    #[Test]
    public function testNegationOverridesPrevious(): void
    {
        $ignore = $this->createIgnore('*.txt', '!keep.txt', '*.txt');

        // The last *.txt rule re-ignores keep.txt
        $this->assertTrue($ignore->isIgnored('keep.txt'));
        $this->assertTrue($ignore->isIgnored('other.txt'));
    }
}
