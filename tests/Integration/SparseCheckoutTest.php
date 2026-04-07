<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Checkout\SparseCheckout;

/**
 * Test Checkout\SparseCheckout file management against a real git repo.
 */
final class SparseCheckoutTest extends TestCase
{
    private string $tmpDir;
    private string $gitDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');
        $this->gitDir = $this->tmpDir . '/.git';
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function initCreatesSparseCheckoutFile(): void
    {
        $sparse = new SparseCheckout($this->gitDir);
        $this->assertFalse($sparse->isEnabled());

        $sparse->init();
        $this->assertTrue($sparse->isEnabled());
        $this->assertFileExists($this->gitDir . '/info/sparse-checkout');
    }

    #[Test]
    public function setWritesCorrectPatterns(): void
    {
        $sparse = new SparseCheckout($this->gitDir);
        $sparse->init();
        $sparse->set(['src', 'docs']);

        $content = file_get_contents($this->gitDir . '/info/sparse-checkout');
        $this->assertStringContainsString('/src/', $content);
        $this->assertStringContainsString('/docs/', $content);
        $this->assertStringContainsString('/*', $content);
        $this->assertStringContainsString('!/*/', $content);
    }

    #[Test]
    public function includesMatchesExpectedPaths(): void
    {
        $sparse = new SparseCheckout($this->gitDir);
        $sparse->init();
        $sparse->set(['src', 'docs']);

        $this->assertTrue($sparse->includes('src/main.php'));
        $this->assertTrue($sparse->includes('docs/readme.txt'));
        $this->assertFalse($sparse->includes('vendor/lib.php'));
        $this->assertFalse($sparse->includes('tests/unit.php'));
    }

    #[Test]
    public function rootFilesAreIncluded(): void
    {
        $sparse = new SparseCheckout($this->gitDir);
        $sparse->init();
        $sparse->set(['src']);

        // Root-level files should be included (/* pattern)
        $this->assertTrue($sparse->includes('README.md'));
        $this->assertTrue($sparse->includes('composer.json'));
    }

    #[Test]
    public function addAppendsDirectories(): void
    {
        $sparse = new SparseCheckout($this->gitDir);
        $sparse->init();
        $sparse->set(['src']);

        $this->assertTrue($sparse->includes('src/main.php'));
        $this->assertFalse($sparse->includes('tests/unit.php'));

        $sparse->add(['tests']);

        $this->assertTrue($sparse->includes('src/main.php'));
        $this->assertTrue($sparse->includes('tests/unit.php'));
    }

    #[Test]
    public function disableRemovesSparseCheckoutFile(): void
    {
        $sparse = new SparseCheckout($this->gitDir);
        $sparse->init();
        $this->assertTrue($sparse->isEnabled());

        $sparse->disable();
        $this->assertFalse($sparse->isEnabled());
        $this->assertFileDoesNotExist($this->gitDir . '/info/sparse-checkout');
    }

    #[Test]
    public function includedDirectoriesReturnsSetDirs(): void
    {
        $sparse = new SparseCheckout($this->gitDir);
        $sparse->init();
        $sparse->set(['src', 'docs', 'lib']);

        $dirs = $sparse->includedDirectories();
        $this->assertContains('src', $dirs);
        $this->assertContains('docs', $dirs);
        $this->assertContains('lib', $dirs);
        $this->assertCount(3, $dirs);
    }

    private function git(string $cmd): string
    {
        return shell_exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $cmd)) ?? '';
    }
}
