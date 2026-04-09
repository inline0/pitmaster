<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Hooks\HookRunner;
use Pitmaster\Object\ObjectId;
use Pitmaster\Pitmaster;
use Pitmaster\Tests\Integration\Support\GitTestRuntime;

final class PushParityTest extends TestCase
{
    private string $tmpDir;

    /** @var resource|null */
    private $server = null;

    private string $serverLog = '';
    private string $serverErrLog = '';
    private string $baseUrl = '';

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/pitmaster-push-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }

        if ($this->serverLog !== '') {
            @unlink($this->serverLog);
        }

        if ($this->serverErrLog !== '') {
            @unlink($this->serverErrLog);
        }

        exec('rm -rf ' . escapeshellarg($this->tmpDir));
    }

    #[Test]
    public function forceWithLeaseMatchesGitForExplicitCasPush(): void
    {
        [$sourceDir, $pitRemoteDir, $gitRemoteDir] = $this->initDualRemoteProject();
        $pitCloneDir = $this->tmpDir . '/pit-clone';
        $gitCloneDir = $this->tmpDir . '/git-clone';
        $this->startGitHttpBackendServer($this->tmpDir . '/projects');
        $pitRemoteUrl = $this->baseUrl . '/pit-remote.git';
        $gitRemoteUrl = $this->baseUrl . '/git-remote.git';

        $pitRepo = Pitmaster::clone($pitRemoteUrl, $pitCloneDir);
        $this->git('clone ' . escapeshellarg($gitRemoteUrl) . ' ' . escapeshellarg($gitCloneDir), $this->tmpDir);
        $this->configureUser($gitCloneDir);

        file_put_contents($sourceDir . '/remote.txt', "remote advance\n");
        $this->git('add remote.txt', $sourceDir);
        $this->git('commit -m remote-advance', $sourceDir);
        $this->git('push origin-pit main', $sourceDir);
        $this->git('push origin-git main', $sourceDir);
        $advancedPitHead = trim($this->git('rev-parse refs/heads/main', $pitRemoteDir));
        $advancedGitHead = trim($this->git('rev-parse refs/heads/main', $gitRemoteDir));

        file_put_contents($pitCloneDir . '/lease.txt', "pit lease push\n");
        $pitRepo->add('lease.txt');
        $pitRepo->commit("Pit lease\n");
        $pitRepo->pushForceWithLease('origin', 'main', ObjectId::fromHex($advancedPitHead));

        file_put_contents($gitCloneDir . '/lease.txt', "pit lease push\n");
        $this->git('add lease.txt', $gitCloneDir);
        $this->git('commit -m "Pit lease"', $gitCloneDir);
        $this->git(
            'push --force-with-lease=refs/heads/main:' . $advancedGitHead . ' origin main',
            $gitCloneDir,
        );

        $this->assertSame(
            $this->git('ls-tree -r --full-tree refs/heads/main', $gitRemoteDir),
            $this->git('ls-tree -r --full-tree refs/heads/main', $pitRemoteDir),
        );
        $this->assertSame("pit lease push\n", $this->git('show refs/heads/main:lease.txt', $pitRemoteDir));
        $this->git('fsck --full', $pitRemoteDir);
    }

    #[Test]
    public function successfulPushAdvancesRemoteTrackingRefLikeGit(): void
    {
        [, $pitRemoteDir, $gitRemoteDir] = $this->initDualRemoteProject();
        $pitCloneDir = $this->tmpDir . '/pit-clone-tracking';
        $gitCloneDir = $this->tmpDir . '/git-clone-tracking';
        $this->startGitHttpBackendServer($this->tmpDir . '/projects');
        $pitRemoteUrl = $this->baseUrl . '/pit-remote.git';
        $gitRemoteUrl = $this->baseUrl . '/git-remote.git';

        $pitRepo = Pitmaster::clone($pitRemoteUrl, $pitCloneDir);
        $this->git('clone ' . escapeshellarg($gitRemoteUrl) . ' ' . escapeshellarg($gitCloneDir), $this->tmpDir);
        $this->configureUser($gitCloneDir);

        file_put_contents($pitCloneDir . '/tracking.txt', "pit tracking\n");
        $pitRepo->add('tracking.txt');
        $pitRepo->commit("Tracking push\n");
        $pitRepo->push();

        file_put_contents($gitCloneDir . '/tracking.txt', "pit tracking\n");
        $this->git('add tracking.txt', $gitCloneDir);
        $this->git('commit -m "Tracking push"', $gitCloneDir);
        $this->git('push origin main', $gitCloneDir);

        $this->assertSame(
            trim($this->git('rev-parse HEAD', $pitCloneDir)),
            trim($this->git('rev-parse refs/remotes/origin/main', $pitCloneDir)),
        );
        $this->assertSame(
            trim($this->git('rev-parse HEAD', $gitCloneDir)),
            trim($this->git('rev-parse refs/remotes/origin/main', $gitCloneDir)),
        );
        $this->assertSame(
            trim($this->git('rev-parse refs/heads/main', $pitRemoteDir)),
            trim($this->git('rev-parse refs/remotes/origin/main', $pitCloneDir)),
        );
        $this->assertSame(
            trim($this->git('rev-parse refs/heads/main', $gitRemoteDir)),
            trim($this->git('rev-parse refs/remotes/origin/main', $gitCloneDir)),
        );
    }

    #[Test]
    public function forceWithLeaseRejectsWhenTrackedLeaseIsStale(): void
    {
        [$sourceDir] = $this->initDualRemoteProject();
        $pitCloneDir = $this->tmpDir . '/pit-clone-stale';
        $this->startGitHttpBackendServer($this->tmpDir . '/projects');
        $pitRemoteUrl = $this->baseUrl . '/pit-remote.git';
        $pitRepo = Pitmaster::clone($pitRemoteUrl, $pitCloneDir);

        file_put_contents($sourceDir . '/remote.txt', "remote advance\n");
        $this->git('add remote.txt', $sourceDir);
        $this->git('commit -m remote-advance', $sourceDir);
        $this->git('push origin-pit main', $sourceDir);

        file_put_contents($pitCloneDir . '/lease.txt', "stale lease\n");
        $pitRepo->add('lease.txt');
        $pitRepo->commit("Stale lease\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('force-with-lease rejected');
        $pitRepo->pushForceWithLease();
    }

    #[Test]
    public function nonFastForwardPushRejectsLikeGit(): void
    {
        [$sourceDir, $pitRemoteDir, $gitRemoteDir] = $this->initDualRemoteProject();
        $pitCloneDir = $this->tmpDir . '/pit-clone-nff';
        $gitCloneDir = $this->tmpDir . '/git-clone-nff';
        $this->startGitHttpBackendServer($this->tmpDir . '/projects');
        $pitRemoteUrl = $this->baseUrl . '/pit-remote.git';
        $gitRemoteUrl = $this->baseUrl . '/git-remote.git';

        $pitRepo = Pitmaster::clone($pitRemoteUrl, $pitCloneDir);
        $this->git('clone ' . escapeshellarg($gitRemoteUrl) . ' ' . escapeshellarg($gitCloneDir), $this->tmpDir);
        $this->configureUser($gitCloneDir);

        file_put_contents($sourceDir . '/remote.txt', "remote advance\n");
        $this->git('add remote.txt', $sourceDir);
        $this->git('commit -m remote-advance', $sourceDir);
        $this->git('push origin-pit main', $sourceDir);
        $this->git('push origin-git main', $sourceDir);

        file_put_contents($pitCloneDir . '/local.txt', "local only\n");
        $pitRepo->add('local.txt');
        $pitRepo->commit("Pit local\n");
        file_put_contents($gitCloneDir . '/local.txt', "local only\n");
        $this->git('add local.txt', $gitCloneDir);
        $this->git('commit -m "Pit local"', $gitCloneDir);

        $pitRemoteBefore = trim($this->git('rev-parse refs/heads/main', $pitRemoteDir));
        $gitRemoteBefore = trim($this->git('rev-parse refs/heads/main', $gitRemoteDir));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('non-fast-forward');

        try {
            $pitRepo->push();
        } finally {
            exec(
                sprintf(
                    'cd %s && git push origin main >/dev/null 2>&1',
                    escapeshellarg($gitCloneDir),
                ),
                $output,
                $exitCode,
            );
            $this->assertNotSame(0, $exitCode);
            $this->assertSame($pitRemoteBefore, trim($this->git('rev-parse refs/heads/main', $pitRemoteDir)));
            $this->assertSame($gitRemoteBefore, trim($this->git('rev-parse refs/heads/main', $gitRemoteDir)));
        }
    }

    #[Test]
    public function atomicPushMatchesGitAllOrNothingBehavior(): void
    {
        [$sourceDir, $pitRemoteDir, $gitRemoteDir] = $this->initDualRemoteProject(withTopic: true);
        $pitCloneDir = $this->tmpDir . '/pit-clone-atomic';
        $gitCloneDir = $this->tmpDir . '/git-clone-atomic';
        $this->startGitHttpBackendServer($this->tmpDir . '/projects');
        $pitRemoteUrl = $this->baseUrl . '/pit-remote.git';
        $gitRemoteUrl = $this->baseUrl . '/git-remote.git';

        $pitRepo = Pitmaster::clone($pitRemoteUrl, $pitCloneDir);
        $pitRepo->createBranch('topic', $pitRepo->resolve('refs/remotes/origin/topic'));
        $this->git('clone ' . escapeshellarg($gitRemoteUrl) . ' ' . escapeshellarg($gitCloneDir), $this->tmpDir);
        $this->configureUser($gitCloneDir);
        $this->git('checkout -b topic origin/topic', $gitCloneDir);
        $this->git('checkout main', $gitCloneDir);

        $this->git('checkout topic', $sourceDir);
        file_put_contents($sourceDir . '/topic.txt', "topic advance\n");
        $this->git('add topic.txt', $sourceDir);
        $this->git('commit -m topic-advance', $sourceDir);
        $this->git('push origin-pit topic', $sourceDir);
        $this->git('push origin-git topic', $sourceDir);
        $this->git('checkout main', $sourceDir);

        file_put_contents($pitCloneDir . '/main.txt', "atomic main\n");
        $pitRepo->add('main.txt');
        $pitRepo->commit("Atomic main\n");
        $pitRepo->checkout('topic');
        file_put_contents($pitCloneDir . '/topic-local.txt', "atomic topic\n");
        $pitRepo->add('topic-local.txt');
        $pitRepo->commit("Atomic topic\n");
        $pitRepo->checkout('main');

        $this->git('checkout main', $gitCloneDir);
        file_put_contents($gitCloneDir . '/main.txt', "atomic main\n");
        $this->git('add main.txt', $gitCloneDir);
        $this->git('commit -m "Atomic main"', $gitCloneDir);
        $this->git('checkout topic', $gitCloneDir);
        file_put_contents($gitCloneDir . '/topic-local.txt', "atomic topic\n");
        $this->git('add topic-local.txt', $gitCloneDir);
        $this->git('commit -m "Atomic topic"', $gitCloneDir);
        $this->git('checkout main', $gitCloneDir);

        $pitMainBefore = trim($this->git('rev-parse refs/heads/main', $pitRemoteDir));
        $pitTopicBefore = trim($this->git('rev-parse refs/heads/topic', $pitRemoteDir));
        $gitMainBefore = trim($this->git('rev-parse refs/heads/main', $gitRemoteDir));
        $gitTopicBefore = trim($this->git('rev-parse refs/heads/topic', $gitRemoteDir));

        try {
            $pitRepo->pushAtomic('origin', ['main', 'topic']);
            self::fail('Expected atomic push to fail');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('non-fast-forward', $e->getMessage());
        }

        exec(
            sprintf(
                'cd %s && git push --atomic origin main topic >/dev/null 2>&1',
                escapeshellarg($gitCloneDir),
            ),
            $output,
            $exitCode,
        );
        $this->assertNotSame(0, $exitCode);

        $this->assertSame($pitMainBefore, trim($this->git('rev-parse refs/heads/main', $pitRemoteDir)));
        $this->assertSame($pitTopicBefore, trim($this->git('rev-parse refs/heads/topic', $pitRemoteDir)));
        $this->assertSame($gitMainBefore, trim($this->git('rev-parse refs/heads/main', $gitRemoteDir)));
        $this->assertSame($gitTopicBefore, trim($this->git('rev-parse refs/heads/topic', $gitRemoteDir)));
    }

    #[Test]
    public function mirrorPushMatchesGitForBranchAndTagDeletions(): void
    {
        [$sourceDir, $pitRemoteDir, $gitRemoteDir] = $this->initDualRemoteProject(withTopic: true, withOldTag: true);
        $pitCloneDir = $this->tmpDir . '/pit-clone-mirror';
        $gitCloneDir = $this->tmpDir . '/git-clone-mirror';
        $this->startGitHttpBackendServer($this->tmpDir . '/projects');
        $pitRemoteUrl = $this->baseUrl . '/pit-remote.git';
        $gitRemoteUrl = $this->baseUrl . '/git-remote.git';

        $pitRepo = Pitmaster::clone($pitRemoteUrl, $pitCloneDir);
        $pitRepo->createBranch('topic', $pitRepo->resolve('refs/remotes/origin/topic'));
        $this->git('clone ' . escapeshellarg($gitRemoteUrl) . ' ' . escapeshellarg($gitCloneDir), $this->tmpDir);
        $this->configureUser($gitCloneDir);
        $this->git('checkout -b topic origin/topic', $gitCloneDir);
        $this->git('checkout main', $gitCloneDir);

        $pitRepo->deleteBranch('topic');
        $pitRepo->createBranch('feature');
        $pitRepo->createLightweightTag('newtag');
        $pitRepo->deleteTag('oldtag');
        $pitRepo->pushMirror();

        $this->git('branch -D topic', $gitCloneDir);
        $this->git('checkout -b feature', $gitCloneDir);
        $this->git('checkout main', $gitCloneDir);
        $this->git('tag newtag', $gitCloneDir);
        $this->git('tag -d oldtag', $gitCloneDir);
        $this->git('push --mirror origin', $gitCloneDir);

        $this->assertSame($this->refSnapshot($gitRemoteDir), $this->refSnapshot($pitRemoteDir));
        $this->git('fsck --full', $pitRemoteDir);
    }

    #[Test]
    public function prePushHookMatchesGit(): void
    {
        [$sourceDir, $remoteDir] = $this->initSingleRemoteProject();
        $pitCloneDir = $this->tmpDir . '/pit-clone-hook';
        $gitCloneDir = $this->tmpDir . '/git-clone-hook';
        $this->startGitHttpBackendServer($this->tmpDir . '/projects');
        $remoteUrl = $this->baseUrl . '/hook-remote.git';

        $pitRepo = Pitmaster::clone($remoteUrl, $pitCloneDir);
        $this->git('clone ' . escapeshellarg($remoteUrl) . ' ' . escapeshellarg($gitCloneDir), $this->tmpDir);
        $this->configureUser($gitCloneDir);

        $hook = "#!/bin/sh\n"
            . "echo \"$1|$2\" >> .hook-log\n"
            . "cat >> .hook-log\n"
            . "exit 1\n";
        (new HookRunner($pitCloneDir . '/.git'))->install('pre-push', $hook);
        (new HookRunner($gitCloneDir . '/.git'))->install('pre-push', $hook);

        file_put_contents($pitCloneDir . '/hook.txt', "pit hook push\n");
        $pitRepo->add('hook.txt');
        putenv('GIT_AUTHOR_DATE=@1700000100 +0000');
        putenv('GIT_COMMITTER_DATE=@1700000100 +0000');
        $pitRepo->commit("Hook push\n");
        file_put_contents($gitCloneDir . '/hook.txt', "pit hook push\n");
        $this->git('add hook.txt', $gitCloneDir);
        $this->gitWithEnv($gitCloneDir, [
            'GIT_AUTHOR_DATE' => '@1700000100 +0000',
            'GIT_COMMITTER_DATE' => '@1700000100 +0000',
        ], 'commit -m "Hook push"');
        putenv('GIT_AUTHOR_DATE');
        putenv('GIT_COMMITTER_DATE');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('pre-push hook failed');

        try {
            $pitRepo->push();
        } finally {
            exec(
                sprintf('cd %s && git push origin main >/dev/null 2>&1', escapeshellarg($gitCloneDir)),
                $output,
                $exitCode,
            );
            $this->assertNotSame(0, $exitCode);
            [$gitRemoteLine, $gitUpdateLine] = explode("\n", trim((string) file_get_contents($gitCloneDir . '/.hook-log')));
            [$pitRemoteLine, $pitUpdateLine] = explode("\n", trim((string) file_get_contents($pitCloneDir . '/.hook-log')));
            $this->assertSame($gitRemoteLine, $pitRemoteLine);

            $gitUpdate = preg_split('/\s+/', trim($gitUpdateLine)) ?: [];
            $pitUpdate = preg_split('/\s+/', trim($pitUpdateLine)) ?: [];

            $this->assertSame('refs/heads/main', $gitUpdate[0] ?? null);
            $this->assertSame('refs/heads/main', $pitUpdate[0] ?? null);
            $this->assertSame(trim($this->git('rev-parse HEAD', $gitCloneDir)), $gitUpdate[1] ?? null);
            $this->assertSame(trim($this->git('rev-parse HEAD', $pitCloneDir)), $pitUpdate[1] ?? null);
            $this->assertSame('refs/heads/main', $gitUpdate[2] ?? null);
            $this->assertSame('refs/heads/main', $pitUpdate[2] ?? null);
            $this->assertSame($gitUpdate[3] ?? null, $pitUpdate[3] ?? null);
            $this->assertSame(
                trim($this->git('rev-parse refs/heads/main', $remoteDir)),
                trim($this->git('rev-parse refs/remotes/origin/main', $gitCloneDir)),
            );
        }
    }

    /**
     * @return array{string, string}
     */
    private function initSingleRemoteProject(): array
    {
        $projectRoot = $this->tmpDir . '/projects';
        $sourceDir = $this->tmpDir . '/single-source';
        $remoteDir = $projectRoot . '/hook-remote.git';

        mkdir($projectRoot, 0777, true);
        $this->git('init --initial-branch=main ' . escapeshellarg($sourceDir), $this->tmpDir);
        $this->git('init --bare --initial-branch=main ' . escapeshellarg($remoteDir), $this->tmpDir);
        $this->git('config user.email test@example.com', $sourceDir);
        $this->git('config user.name Test', $sourceDir);
        $this->git('config http.receivepack true', $remoteDir);

        file_put_contents($sourceDir . '/README.md', "hello hook parity\n");
        $this->git('add README.md', $sourceDir);
        $this->git('commit -m initial', $sourceDir);
        $this->git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
        $this->git('push origin main', $sourceDir);

        return [$sourceDir, $remoteDir];
    }

    /**
     * @return array{string, string, string}
     */
    private function initDualRemoteProject(bool $withTopic = false, bool $withOldTag = false): array
    {
        $projectRoot = $this->tmpDir . '/projects';
        $sourceDir = $this->tmpDir . '/source';
        $pitRemoteDir = $projectRoot . '/pit-remote.git';
        $gitRemoteDir = $projectRoot . '/git-remote.git';

        mkdir($projectRoot, 0777, true);
        $this->git('init --initial-branch=main ' . escapeshellarg($sourceDir), $this->tmpDir);
        $this->git('init --bare --initial-branch=main ' . escapeshellarg($pitRemoteDir), $this->tmpDir);
        $this->git('init --bare --initial-branch=main ' . escapeshellarg($gitRemoteDir), $this->tmpDir);
        $this->git('config user.email test@example.com', $sourceDir);
        $this->git('config user.name Test', $sourceDir);
        $this->git('config http.receivepack true', $pitRemoteDir);
        $this->git('config http.receivepack true', $gitRemoteDir);

        file_put_contents($sourceDir . '/README.md', "hello push parity\n");
        $this->git('add README.md', $sourceDir);
        $this->git('commit -m initial', $sourceDir);

        if ($withTopic) {
            $this->git('checkout -b topic', $sourceDir);
            file_put_contents($sourceDir . '/topic.txt', "topic base\n");
            $this->git('add topic.txt', $sourceDir);
            $this->git('commit -m topic-base', $sourceDir);
            $this->git('checkout main', $sourceDir);
        }

        if ($withOldTag) {
            $this->git('tag oldtag', $sourceDir);
        }

        $this->git('remote add origin-pit ' . escapeshellarg($pitRemoteDir), $sourceDir);
        $this->git('remote add origin-git ' . escapeshellarg($gitRemoteDir), $sourceDir);
        $this->git('push origin-pit --all', $sourceDir);
        $this->git('push origin-pit --tags', $sourceDir);
        $this->git('push origin-git --all', $sourceDir);
        $this->git('push origin-git --tags', $sourceDir);

        return [$sourceDir, $pitRemoteDir, $gitRemoteDir];
    }

    private function startGitHttpBackendServer(string $projectRoot): void
    {
        $port = $this->findFreePort();
        $router = dirname(__DIR__) . '/Fixtures/git_http_backend_router.php';
        $this->baseUrl = "http://127.0.0.1:{$port}";
        $this->serverLog = sys_get_temp_dir() . '/pitmaster-push-' . bin2hex(random_bytes(4)) . '.log';
        $this->serverErrLog = sys_get_temp_dir() . '/pitmaster-push-' . bin2hex(random_bytes(4)) . '.err.log';

        $command = sprintf(
            '%s -S 127.0.0.1:%d %s',
            escapeshellarg(PHP_BINARY),
            $port,
            escapeshellarg($router),
        );

        $this->server = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['file', $this->serverLog, 'a'],
                2 => ['file', $this->serverErrLog, 'a'],
            ],
            $pipes,
            dirname(__DIR__, 2),
            [
                'PITMASTER_GIT_HTTP_PROJECT_ROOT' => $projectRoot,
                'PITMASTER_GIT_HTTP_BACKEND' => GitTestRuntime::gitHttpBackend(),
            ],
        );

        if (!is_resource($this->server)) {
            $this->fail('Failed to start git-http-backend test server');
        }

        fclose($pipes[0]);
        $this->waitUntilServerReady();
    }

    private function findFreePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($socket === false) {
            $this->fail("Failed to allocate test port: {$errstr}");
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        if ($name === false) {
            $this->fail('Failed to read allocated test port');
        }

        return (int) substr((string) strrchr($name, ':'), 1);
    }

    private function waitUntilServerReady(): void
    {
        $context = stream_context_create([
            'http' => [
                'ignore_errors' => true,
                'timeout' => 1,
            ],
        ]);

        for ($i = 0; $i < 50; $i++) {
            $response = @file_get_contents($this->baseUrl . '/health', false, $context);

            if ($response !== false) {
                return;
            }

            usleep(100000);
        }

        $stderr = is_file($this->serverErrLog) ? file_get_contents($this->serverErrLog) : '';
        $this->fail('git-http-backend test server did not become ready: ' . trim((string) $stderr));
    }

    private function refSnapshot(string $repoDir): string
    {
        return $this->git("for-each-ref --format='%(refname) %(objectname)' refs/heads refs/tags | sort", $repoDir);
    }

    private function configureUser(string $dir): void
    {
        $this->git('config user.email test@example.com', $dir);
        $this->git('config user.name Test', $dir);
    }

    private function git(string $command, string $dir): string
    {
        exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command), $output, $exitCode);
        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->fail("git {$command} failed in {$dir}:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }

    /**
     * @param array<string, string> $env
     */
    private function gitWithEnv(string $dir, array $env, string $command): string
    {
        $prefix = [];

        foreach ($env as $name => $value) {
            $prefix[] = sprintf('%s=%s', $name, escapeshellarg($value));
        }

        exec(
            sprintf('cd %s && %s git %s 2>&1', escapeshellarg($dir), implode(' ', $prefix), $command),
            $output,
            $exitCode,
        );
        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            $this->fail("git {$command} failed in {$dir}:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }
}
