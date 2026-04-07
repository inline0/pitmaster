<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Exceptions\ProtocolException;
use Pitmaster\Protocol\GitProtocolClient;

final class GitProtocolClientTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        exec(sprintf('cd %s && git init && git config user.email t@t.com && git config user.name T 2>&1', escapeshellarg($this->tmpDir)));
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function parseUrlExtractsHostPortPath(): void
    {
        $result = GitProtocolClient::parseUrl('git://github.com/user/repo.git');

        $this->assertSame('github.com', $result['host']);
        $this->assertSame(9418, $result['port']);
        $this->assertSame('/user/repo.git', $result['path']);
    }

    #[Test]
    public function parseUrlWithCustomPort(): void
    {
        $result = GitProtocolClient::parseUrl('git://example.com:1234/path/to/repo.git');

        $this->assertSame('example.com', $result['host']);
        $this->assertSame(1234, $result['port']);
        $this->assertSame('/path/to/repo.git', $result['path']);
    }

    #[Test]
    public function parseUrlThrowsForNonGitProtocol(): void
    {
        $this->expectException(ProtocolException::class);

        GitProtocolClient::parseUrl('https://github.com/user/repo.git');
    }

    #[Test]
    public function constructorAcceptsTimeout(): void
    {
        $client = new GitProtocolClient(60);

        // If it constructed without error, the timeout was accepted
        $this->assertInstanceOf(GitProtocolClient::class, $client);
    }
}
