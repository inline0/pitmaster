<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Unit\Lfs;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Lfs\LfsClient;

final class LfsClientTest extends TestCase
{
    #[Test]
    public function testConstructor(): void
    {
        $client = new LfsClient('https://example.com/repo.git/info/lfs');
        $this->assertInstanceOf(LfsClient::class, $client);
    }

    #[Test]
    public function testFromRemoteUrlWithDotGit(): void
    {
        $client = LfsClient::fromRemoteUrl('https://github.com/user/repo.git');
        $this->assertInstanceOf(LfsClient::class, $client);
    }

    #[Test]
    public function testFromRemoteUrlWithoutDotGit(): void
    {
        $client = LfsClient::fromRemoteUrl('https://github.com/user/repo');
        $this->assertInstanceOf(LfsClient::class, $client);
    }

    #[Test]
    public function testCustomTimeout(): void
    {
        $client = new LfsClient('https://example.com/lfs', 120);
        $this->assertInstanceOf(LfsClient::class, $client);
    }
}
