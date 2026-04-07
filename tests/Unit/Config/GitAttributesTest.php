<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Config\GitAttributes;

final class GitAttributesTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-gitattrs-' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    #[Test]
    public function testGetAttributesForMatchingPattern(): void
    {
        $this->writeAttrs("*.png binary\n*.jpg binary\n");

        $attrs = new GitAttributes();
        $attrs->addFile($this->tmpDir . '/.gitattributes');

        $result = $attrs->getAttributes('image.png');
        $this->assertTrue($result['binary']);
    }

    #[Test]
    public function testGetAttributesForNonMatchingPattern(): void
    {
        $this->writeAttrs("*.png binary\n");

        $attrs = new GitAttributes();
        $attrs->addFile($this->tmpDir . '/.gitattributes');

        $result = $attrs->getAttributes('readme.txt');
        $this->assertEmpty($result);
    }

    #[Test]
    public function testIsBinaryWithBinaryAttribute(): void
    {
        $this->writeAttrs("*.png binary\n");

        $attrs = new GitAttributes();
        $attrs->addFile($this->tmpDir . '/.gitattributes');

        $this->assertTrue($attrs->isBinary('photo.png'));
        $this->assertFalse($attrs->isBinary('readme.txt'));
    }

    #[Test]
    public function testIsBinaryWithNegatedDiff(): void
    {
        $this->writeAttrs("*.bin -diff\n");

        $attrs = new GitAttributes();
        $attrs->addFile($this->tmpDir . '/.gitattributes');

        $this->assertTrue($attrs->isBinary('data.bin'));
    }

    #[Test]
    public function testHasAttribute(): void
    {
        $this->writeAttrs("*.php text\n*.js text eol=lf\n");

        $attrs = new GitAttributes();
        $attrs->addFile($this->tmpDir . '/.gitattributes');

        $this->assertTrue($attrs->hasAttribute('app.php', 'text'));
        $this->assertFalse($attrs->hasAttribute('app.php', 'binary'));
        $this->assertTrue($attrs->hasAttribute('app.js', 'eol'));
    }

    #[Test]
    public function testValueAttribute(): void
    {
        $this->writeAttrs("*.php diff=php\n");

        $attrs = new GitAttributes();
        $attrs->addFile($this->tmpDir . '/.gitattributes');

        $result = $attrs->getAttributes('index.php');
        $this->assertSame('php', $result['diff']);
    }

    #[Test]
    public function testUnspecifiedAttributeIsSkipped(): void
    {
        $this->writeAttrs("*.txt !merge text\n");

        $attrs = new GitAttributes();
        $attrs->addFile($this->tmpDir . '/.gitattributes');

        $result = $attrs->getAttributes('readme.txt');
        $this->assertArrayNotHasKey('merge', $result);
        $this->assertTrue($result['text']);
    }

    #[Test]
    public function testCommentsAreIgnored(): void
    {
        $this->writeAttrs("# comment\n*.txt text\n");

        $attrs = new GitAttributes();
        $attrs->addFile($this->tmpDir . '/.gitattributes');

        $result = $attrs->getAttributes('readme.txt');
        $this->assertTrue($result['text']);
    }

    #[Test]
    public function testForRepoLoadsGitattributes(): void
    {
        $this->writeAttrs("*.bin binary\n");

        $attrs = GitAttributes::forRepo($this->tmpDir);

        $this->assertTrue($attrs->isBinary('file.bin'));
    }

    #[Test]
    public function testForRepoWithNoFile(): void
    {
        $emptyDir = $this->tmpDir . '/empty';
        mkdir($emptyDir, 0777, true);

        $attrs = GitAttributes::forRepo($emptyDir);

        $this->assertEmpty($attrs->getAttributes('anything'));
    }

    private function writeAttrs(string $content): void
    {
        file_put_contents($this->tmpDir . '/.gitattributes', $content);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
