<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Protocol;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Exceptions\ProtocolException;
use Pitmaster\Protocol\SshClient;

final class SshClientTest extends TestCase
{
    #[Test]
    public function testParseScpStyleUrl(): void
    {
        $result = SshClient::parseUrl('git@github.com:user/repo.git');

        $this->assertSame('git', $result['user']);
        $this->assertSame('github.com', $result['host']);
        $this->assertSame(22, $result['port']);
        $this->assertSame('user/repo.git', $result['path']);
    }

    #[Test]
    public function testParseSshUrlWithoutPort(): void
    {
        $result = SshClient::parseUrl('ssh://user@host/path/to/repo.git');

        $this->assertSame('user', $result['user']);
        $this->assertSame('host', $result['host']);
        $this->assertSame(22, $result['port']);
        $this->assertSame('/path/to/repo.git', $result['path']);
    }

    #[Test]
    public function testParseSshUrlAnotherUser(): void
    {
        $result = SshClient::parseUrl('ssh://deploy@example.com/var/git/project.git');

        $this->assertSame('deploy', $result['user']);
        $this->assertSame('example.com', $result['host']);
        $this->assertSame(22, $result['port']);
        $this->assertSame('/var/git/project.git', $result['path']);
    }

    #[Test]
    public function testParseSshUrlWithPort(): void
    {
        $result = SshClient::parseUrl('ssh://admin@server.io:2222/repos/project.git');

        $this->assertSame('admin', $result['user']);
        $this->assertSame('server.io', $result['host']);
        $this->assertSame(2222, $result['port']);
        $this->assertSame('/repos/project.git', $result['path']);
    }

    #[Test]
    public function testInvalidUrlThrowsProtocolException(): void
    {
        $this->expectException(ProtocolException::class);

        SshClient::parseUrl('https://github.com/user/repo.git');
    }

    #[Test]
    public function testParseScpStyleWithDifferentUser(): void
    {
        $result = SshClient::parseUrl('deploy@gitlab.example.com:group/project.git');

        $this->assertSame('deploy', $result['user']);
        $this->assertSame('gitlab.example.com', $result['host']);
        $this->assertSame(22, $result['port']);
        $this->assertSame('group/project.git', $result['path']);
    }
}
