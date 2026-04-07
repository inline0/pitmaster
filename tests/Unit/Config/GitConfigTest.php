<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Config\GitConfig;

final class GitConfigTest extends TestCase
{
    #[Test]
    public function testParseSimpleSection(): void
    {
        $content = <<<'INI'
[core]
    bare = false
    repositoryformatversion = 0
INI;

        $config = GitConfig::parse($content);

        $this->assertSame('false', $config->get('core.bare'));
        $this->assertSame('0', $config->get('core.repositoryformatversion'));
    }

    #[Test]
    public function testParseSubsectionWithQuotes(): void
    {
        $content = <<<'INI'
[remote "origin"]
    url = https://github.com/example/repo.git
    fetch = +refs/heads/*:refs/remotes/origin/*
INI;

        $config = GitConfig::parse($content);

        $this->assertSame(
            'https://github.com/example/repo.git',
            $config->get('remote.origin.url')
        );
        $this->assertSame(
            '+refs/heads/*:refs/remotes/origin/*',
            $config->get('remote.origin.fetch')
        );
    }

    #[Test]
    public function testGet(): void
    {
        $content = <<<'INI'
[user]
    name = John Doe
    email = john@example.com
INI;

        $config = GitConfig::parse($content);

        $this->assertSame('John Doe', $config->get('user.name'));
        $this->assertNull($config->get('user.nonexistent'));
        $this->assertSame('fallback', $config->get('user.nonexistent', 'fallback'));
    }

    #[Test]
    public function testGetBool(): void
    {
        $content = <<<'INI'
[core]
    bare = true
    autocrlf = false
    symlinks = yes
    filemode = on
    ignorecase = 1
    logallrefupdates = no
INI;

        $config = GitConfig::parse($content);

        $this->assertTrue($config->getBool('core.bare'));
        $this->assertFalse($config->getBool('core.autocrlf'));
        $this->assertTrue($config->getBool('core.symlinks'));
        $this->assertTrue($config->getBool('core.filemode'));
        $this->assertTrue($config->getBool('core.ignorecase'));
        $this->assertFalse($config->getBool('core.logallrefupdates'));
    }

    #[Test]
    public function testGetBoolDefault(): void
    {
        $config = GitConfig::parse('');

        $this->assertFalse($config->getBool('nonexistent.key'));
        $this->assertTrue($config->getBool('nonexistent.key', true));
    }

    #[Test]
    public function testGetInt(): void
    {
        $content = <<<'INI'
[core]
    repositoryformatversion = 0
[pack]
    windowmemory = 1024
INI;

        $config = GitConfig::parse($content);

        $this->assertSame(0, $config->getInt('core.repositoryformatversion'));
        $this->assertSame(1024, $config->getInt('pack.windowmemory'));
    }

    #[Test]
    public function testGetIntDefault(): void
    {
        $config = GitConfig::parse('');
        $this->assertSame(42, $config->getInt('nonexistent.key', 42));
    }

    #[Test]
    public function testSection(): void
    {
        $content = <<<'INI'
[remote "origin"]
    url = https://github.com/example/repo.git
    fetch = +refs/heads/*:refs/remotes/origin/*
[remote "upstream"]
    url = https://github.com/upstream/repo.git
[core]
    bare = false
INI;

        $config = GitConfig::parse($content);

        $origin = $config->section('remote.origin');
        $this->assertSame('https://github.com/example/repo.git', $origin['url']);
        $this->assertSame('+refs/heads/*:refs/remotes/origin/*', $origin['fetch']);
        $this->assertCount(2, $origin);
    }

    #[Test]
    public function testSet(): void
    {
        $config = GitConfig::parse('');

        $config->set('user.name', 'Jane Doe');
        $this->assertSame('Jane Doe', $config->get('user.name'));
    }

    #[Test]
    public function testUnset(): void
    {
        $content = <<<'INI'
[user]
    name = John Doe
INI;

        $config = GitConfig::parse($content);

        $this->assertSame('John Doe', $config->get('user.name'));
        $config->unset('user.name');
        $this->assertNull($config->get('user.name'));
    }

    #[Test]
    public function testCommentsAreIgnored(): void
    {
        $content = <<<'INI'
# This is a comment
[core]
    ; Another comment
    bare = false
INI;

        $config = GitConfig::parse($content);

        $this->assertSame('false', $config->get('core.bare'));
    }

    #[Test]
    public function testSectionKeysAreLowercased(): void
    {
        $content = <<<'INI'
[Core]
    Bare = true
INI;

        $config = GitConfig::parse($content);

        // Section name is lowercased, key name is lowercased
        $this->assertSame('true', $config->get('core.bare'));
    }

    #[Test]
    public function testAll(): void
    {
        $content = <<<'INI'
[core]
    bare = false
[user]
    name = Test
INI;

        $config = GitConfig::parse($content);
        $all = $config->all();

        $this->assertArrayHasKey('core.bare', $all);
        $this->assertArrayHasKey('user.name', $all);
        $this->assertCount(2, $all);
    }
}
