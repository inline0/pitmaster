<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Submodule\Submodule;
use Pitmaster\Submodule\SubmoduleManager;

/**
 * Test Submodule\SubmoduleManager parsing against a real git repo.
 */
final class SubmoduleTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-test-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
        $this->git('init');
        $this->git('config user.email test@pitmaster.dev');
        $this->git('config user.name "Test User"');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function parseGitmodulesWithSingleSubmodule(): void
    {
        $content = <<<'GITMODULES'
[submodule "vendor/lib"]
    path = vendor/lib
    url = https://github.com/example/lib.git
GITMODULES;

        $result = SubmoduleManager::parseGitmodules($content);

        $this->assertCount(1, $result);
        $this->assertSame('vendor/lib', $result[0]->name);
        $this->assertSame('vendor/lib', $result[0]->path);
        $this->assertSame('https://github.com/example/lib.git', $result[0]->url);
        $this->assertNull($result[0]->branch);
    }

    #[Test]
    public function parseGitmodulesWithMultipleSubmodules(): void
    {
        $content = <<<'GITMODULES'
[submodule "libs/core"]
    path = libs/core
    url = https://github.com/example/core.git
    branch = main
[submodule "libs/utils"]
    path = libs/utils
    url = https://github.com/example/utils.git
    branch = develop
GITMODULES;

        $result = SubmoduleManager::parseGitmodules($content);

        $this->assertCount(2, $result);

        $this->assertSame('libs/core', $result[0]->name);
        $this->assertSame('https://github.com/example/core.git', $result[0]->url);
        $this->assertSame('main', $result[0]->branch);

        $this->assertSame('libs/utils', $result[1]->name);
        $this->assertSame('https://github.com/example/utils.git', $result[1]->url);
        $this->assertSame('develop', $result[1]->branch);
    }

    #[Test]
    public function parseGitmodulesHandlesComments(): void
    {
        $content = <<<'GITMODULES'
# This is a comment
[submodule "vendor/dep"]
    # Another comment
    path = vendor/dep
    url = https://github.com/example/dep.git
GITMODULES;

        $result = SubmoduleManager::parseGitmodules($content);

        $this->assertCount(1, $result);
        $this->assertSame('vendor/dep', $result[0]->name);
    }

    #[Test]
    public function listReturnsSubmodulesFromFile(): void
    {
        $gitmodulesContent = <<<'GITMODULES'
[submodule "ext/parser"]
    path = ext/parser
    url = https://github.com/example/parser.git
[submodule "ext/lexer"]
    path = ext/lexer
    url = https://github.com/example/lexer.git
GITMODULES;

        file_put_contents($this->tmpDir . '/.gitmodules', $gitmodulesContent);

        // SubmoduleManager needs ObjectDatabase, but for list() it only reads the file
        $manager = new SubmoduleManager(
            $this->createStubObjectDatabase(),
            $this->tmpDir,
            $this->tmpDir . '/.git',
        );

        $submodules = $manager->list();

        $this->assertCount(2, $submodules);
        $this->assertSame('ext/parser', $submodules[0]->name);
        $this->assertSame('ext/lexer', $submodules[1]->name);
    }

    #[Test]
    public function listReturnsEmptyWhenNoGitmodules(): void
    {
        $manager = new SubmoduleManager(
            $this->createStubObjectDatabase(),
            $this->tmpDir,
            $this->tmpDir . '/.git',
        );

        $this->assertSame([], $manager->list());
    }

    #[Test]
    public function parseGitmodulesEmptyContentReturnsEmpty(): void
    {
        $result = SubmoduleManager::parseGitmodules('');
        $this->assertSame([], $result);
    }

    #[Test]
    public function parseGitmodulesIncompleteSection(): void
    {
        // A submodule section missing 'url' should be skipped
        $content = <<<'GITMODULES'
[submodule "incomplete"]
    path = incomplete
[submodule "complete"]
    path = complete
    url = https://github.com/example/complete.git
GITMODULES;

        $result = SubmoduleManager::parseGitmodules($content);

        $this->assertCount(1, $result);
        $this->assertSame('complete', $result[0]->name);
    }

    /**
     * Create a minimal stub ObjectDatabase for tests that only need list().
     */
    private function createStubObjectDatabase(): \Pitmaster\Storage\ObjectDatabase
    {
        return new \Pitmaster\Storage\ObjectDatabase($this->tmpDir . '/.git/objects');
    }

    private function git(string $cmd): string
    {
        return shell_exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($this->tmpDir), $cmd)) ?? '';
    }
}
