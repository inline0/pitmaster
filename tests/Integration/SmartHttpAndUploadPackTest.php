<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Protocol\SmartHttpClient;
use Pitmaster\Protocol\UploadPackClient;

final class SmartHttpAndUploadPackTest extends TestCase
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
    public function smartHttpClientCanBeConstructed(): void
    {
        $client = new SmartHttpClient();

        $this->assertInstanceOf(SmartHttpClient::class, $client);
    }

    #[Test]
    public function smartHttpClientAcceptsCustomTimeout(): void
    {
        $client = new SmartHttpClient(120);

        $this->assertInstanceOf(SmartHttpClient::class, $client);
    }

    #[Test]
    public function uploadPackClientCanBeConstructed(): void
    {
        $http = new SmartHttpClient();
        $client = new UploadPackClient($http);

        $this->assertInstanceOf(UploadPackClient::class, $client);
    }
}
