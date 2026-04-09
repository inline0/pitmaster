<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Pitmaster\Exceptions\MergeConflictException;
use Pitmaster\Pack\PackIndex;
use Pitmaster\Pack\PackIndexer;
use Pitmaster\Pitmaster;
use Pitmaster\Ref\Notes;
use Pitmaster\Ref\Reflog;
use Pitmaster\Stash\Stash;
use Pitmaster\Submodule\SubmoduleManager;
use Pitmaster\Tests\Integration\Support\GitTestRuntime;
use Pitmaster\Tests\Support\Workspace;

final class ScaleAndLongevityTest extends TestCase
{
    /** @var list<string> */
    private array $paths = [];

    /** @var array<int, resource> */
    private array $processes = [];

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }

        foreach ($this->paths as $path) {
            Workspace::remove($path);
        }
    }

    #[Test]
    public function repeatedSmartHttpCloneFetchAndPushLoopsStayConsistent(): void
    {
        $root = $this->createDirectory('scale-http-root-');
        $source = $root . '/source';
        $clone = $root . '/clone';
        $remote = $root . '/remote.git';

        mkdir($source, 0777, true);
        $this->git($root, 'init --initial-branch=main ' . escapeshellarg($source));
        $this->git($root, 'init --bare --initial-branch=main ' . escapeshellarg($remote));
        $this->git($source, 'config user.email test@pitmaster.dev');
        $this->git($source, 'config user.name "Test User"');
        $this->git($remote, 'config http.receivepack true');
        $this->git($remote, 'config pack.window 0');
        $this->git($remote, 'config pack.depth 0');
        file_put_contents($source . '/README.md', "base\n");
        $this->git($source, 'add README.md');
        $this->git($source, 'commit -m initial');
        $this->git($source, 'remote add origin ' . escapeshellarg($remote));
        $this->git($source, 'push origin main');

        $baseUrl = $this->startGitHttpBackendServer($root);
        $repo = Pitmaster::clone($baseUrl . '/remote.git', $clone);
        $config = $repo->config();
        $config->set('user.name', 'Test User');
        $config->set('user.email', 'test@pitmaster.dev');
        $config->writeToFile($clone . '/.git/config');

        for ($i = 1; $i <= 3; $i++) {
            file_put_contents($source . "/remote-{$i}.txt", "remote {$i}\n");
            $this->git($source, 'add ' . escapeshellarg("remote-{$i}.txt"));
            $this->git($source, 'commit -m ' . escapeshellarg("remote {$i}"));
            $this->git($source, 'push origin main');

            $repo->fetch();
            $repo->reset('refs/remotes/origin/main', 'hard');

            file_put_contents($clone . "/pit-{$i}.txt", "pit {$i}\n");
            $repo->add("pit-{$i}.txt");
            $repo->commit("pit {$i}\n");
            $repo->push();

            $this->git($source, 'fetch origin');
            $this->git($source, 'reset --hard origin/main');
            self::assertSame(
                trim($this->git($remote, 'rev-parse refs/heads/main')),
                trim($this->git($clone, 'rev-parse HEAD')),
            );
        }

        $this->git($remote, 'fsck --full');
    }

    #[Test]
    public function largeHistoryAndRefFanoutRemainQueryable(): void
    {
        $repoDir = $this->createDirectory('scale-history-');
        $this->git($repoDir, 'init --initial-branch=main');
        $this->git($repoDir, 'config user.email test@pitmaster.dev');
        $this->git($repoDir, 'config user.name "Test User"');

        for ($i = 1; $i <= 80; $i++) {
            file_put_contents($repoDir . '/history.txt', "commit {$i}\n", FILE_APPEND);
            $this->git($repoDir, 'add history.txt');
            $this->git($repoDir, 'commit -m ' . escapeshellarg("commit {$i}"));

            if ($i % 10 === 0) {
                $this->git($repoDir, 'branch feature-' . $i);
                $this->git($repoDir, 'tag v' . $i);
            }
        }

        $repo = Pitmaster::open($repoDir);
        $gitCommitCount = count($this->gitLines($repoDir, 'rev-list --all'));
        $gitRefCount = count($this->gitLines($repoDir, 'show-ref'));

        self::assertCount($gitCommitCount, $repo->log(100));
        self::assertCount($gitRefCount, $repo->allRefs());
        self::assertGreaterThanOrEqual(8, count($repo->branches()));
        self::assertGreaterThanOrEqual(8, count($repo->tags()));
    }

    #[Test]
    public function largeTreeResetRestoresWideDirectoryFanout(): void
    {
        $repoDir = $this->createDirectory('scale-tree-');
        $this->git($repoDir, 'init --initial-branch=main');
        $this->git($repoDir, 'config user.email test@pitmaster.dev');
        $this->git($repoDir, 'config user.name "Test User"');

        for ($dir = 1; $dir <= 18; $dir++) {
            for ($file = 1; $file <= 8; $file++) {
                $path = sprintf('%s/dir-%02d/file-%02d.txt', $repoDir, $dir, $file);
                if (!is_dir(dirname($path))) {
                    mkdir(dirname($path), 0777, true);
                }

                file_put_contents($path, "base {$dir} {$file}\n");
            }
        }

        $this->git($repoDir, 'add .');
        $this->git($repoDir, 'commit -m base');
        $repo = Pitmaster::open($repoDir);

        for ($dir = 1; $dir <= 18; $dir += 2) {
            $repo->remove(sprintf('dir-%02d/file-01.txt', $dir));
        }

        $repo->commit("remove some\n");
        $repo->reset('HEAD~1', 'hard');

        self::assertSame(
            trim($this->git($repoDir, 'ls-files | wc -l')),
            (string) count($repo->index()->paths()),
        );
        self::assertSame('', trim($this->git($repoDir, 'status --short')));
    }

    #[Test]
    public function largePackIndexingMatchesGitObjectCount(): void
    {
        $repoDir = $this->createDirectory('scale-pack-');
        $this->git($repoDir, 'init --initial-branch=main');
        $this->git($repoDir, 'config user.email test@pitmaster.dev');
        $this->git($repoDir, 'config user.name "Test User"');

        for ($i = 1; $i <= 40; $i++) {
            file_put_contents($repoDir . "/blob-{$i}.txt", str_repeat("line {$i}\n", 12));
            $this->git($repoDir, 'add ' . escapeshellarg("blob-{$i}.txt"));

            if ($i % 5 === 0) {
                $this->git($repoDir, 'commit -m ' . escapeshellarg("batch {$i}"));
            }
        }

        $this->git($repoDir, 'rev-list --all | git pack-objects .git/objects/pack/stress-pack --revs >/dev/null');
        $packPath = $this->singlePath($repoDir, '.git/objects/pack/stress-pack-*.pack');
        $copyPath = $repoDir . '/stress-copy.pack';
        copy($packPath, $copyPath);
        $idxPath = PackIndexer::writeIndex($copyPath);
        $index = PackIndex::open($idxPath);

        self::assertSame(
            count($this->gitLines($repoDir, 'rev-list --objects --all')),
            $index->objectCount(),
        );
    }

    #[Test]
    public function mergeAndRebaseConflictStormsCanAbortAndContinueCleanly(): void
    {
        $repoDir = $this->createDirectory('scale-conflicts-');
        $this->git($repoDir, 'init --initial-branch=main');
        $this->git($repoDir, 'config user.email test@pitmaster.dev');
        $this->git($repoDir, 'config user.name "Test User"');
        $this->git($repoDir, 'config rerere.enabled true');

        for ($i = 1; $i <= 5; $i++) {
            file_put_contents($repoDir . "/conflict-{$i}.txt", "start {$i}\nbase\nend {$i}\n");
        }

        $this->git($repoDir, 'add .');
        $this->git($repoDir, 'commit -m base');
        $this->git($repoDir, 'branch feature');
        $this->git($repoDir, 'branch topic');

        $this->git($repoDir, 'checkout feature');
        for ($i = 1; $i <= 5; $i++) {
            file_put_contents($repoDir . "/conflict-{$i}.txt", "start {$i}\nfeature {$i}\nend {$i}\n");
        }
        $this->git($repoDir, 'add .');
        $this->git($repoDir, 'commit -m feature');

        $this->git($repoDir, 'checkout main');
        for ($i = 1; $i <= 5; $i++) {
            file_put_contents($repoDir . "/conflict-{$i}.txt", "start {$i}\nmain {$i}\nend {$i}\n");
        }
        $this->git($repoDir, 'add .');
        $this->git($repoDir, 'commit -m main');

        $repo = Pitmaster::open($repoDir);

        $merge = $repo->merge('feature');
        self::assertFalse($merge->clean);
        self::assertCount(5, $merge->conflictPaths);

        $repo->mergeAbort();
        self::assertSame('', trim($this->git($repoDir, 'status --short')));

        self::assertFalse($repo->merge('feature')->clean);

        for ($i = 1; $i <= 5; $i++) {
            file_put_contents($repoDir . "/conflict-{$i}.txt", "resolved {$i}\n");
            $repo->add("conflict-{$i}.txt");
        }

        $repo->mergeContinue();
        self::assertSame('', trim($this->git($repoDir, 'status --short')));

        $this->git($repoDir, 'checkout topic');
        for ($i = 1; $i <= 3; $i++) {
            file_put_contents($repoDir . "/conflict-{$i}.txt", "start {$i}\ntopic {$i}\nend {$i}\n");
        }
        $this->git($repoDir, 'add conflict-1.txt conflict-2.txt conflict-3.txt');
        $this->git($repoDir, 'commit -m topic');

        $this->git($repoDir, 'checkout main');
        for ($i = 1; $i <= 3; $i++) {
            file_put_contents($repoDir . "/conflict-{$i}.txt", "start {$i}\nmain rerere {$i}\nend {$i}\n");
        }
        $this->git($repoDir, 'add conflict-1.txt conflict-2.txt conflict-3.txt');
        $this->git($repoDir, 'commit -m rerere-main');
        $this->git($repoDir, 'checkout topic');

        $topicRepo = Pitmaster::open($repoDir);
        $result = $topicRepo->rebase('main');
        self::assertFalse($result['success']);
        self::assertCount(3, $result['conflicts']);
        $topicRepo->rebaseAbort();

        $result = $topicRepo->rebase('main');
        self::assertFalse($result['success']);

        for ($i = 1; $i <= 3; $i++) {
            file_put_contents($repoDir . "/conflict-{$i}.txt", "rebased {$i}\n");
            $topicRepo->add("conflict-{$i}.txt");
        }

        $continued = $topicRepo->rebaseContinue();
        self::assertTrue($continued['success']);
        self::assertSame('', trim($this->git($repoDir, 'status --short')));
    }

    #[Test]
    public function manyLinkedWorktreesCanBeAddedOpenedAndRemovedRepeatedly(): void
    {
        $repoDir = $this->createDirectory('scale-worktrees-');
        $this->git($repoDir, 'init --initial-branch=main');
        $this->git($repoDir, 'config user.email test@pitmaster.dev');
        $this->git($repoDir, 'config user.name "Test User"');
        file_put_contents($repoDir . '/README.md', "base\n");
        $this->git($repoDir, 'add README.md');
        $this->git($repoDir, 'commit -m base');

        $repo = Pitmaster::open($repoDir);
        $paths = [];

        for ($i = 1; $i <= 6; $i++) {
            $branch = "feature-{$i}";
            $this->git($repoDir, 'branch ' . escapeshellarg($branch));
            $path = dirname($repoDir) . "/linked-{$i}/wp-content/plugins/same-name";
            $repo->addWorktree($path, $branch, name: "worktree-{$i}");
            $paths[$i] = $path;

            $linked = Pitmaster::open($path);
            self::assertTrue($linked->isLinkedWorktree());
            self::assertSame($repo->commonGitDir(), $linked->commonGitDir());
        }

        self::assertCount(7, $repo->worktrees());

        foreach ($paths as $i => $path) {
            if ($i % 2 === 0) {
                $repo->removeWorktree("worktree-{$i}");
            } else {
                $repo->removeWorktree($path);
            }
        }

        self::assertCount(1, Pitmaster::open($repoDir)->worktrees());
    }

    #[Test]
    public function nestedSubmodulesCanBeUpdatedRecursivelyThroughPitmasterManagers(): void
    {
        $dep2 = $this->createDirectory('scale-submodule-dep2-');
        $dep1 = $this->createDirectory('scale-submodule-dep1-');
        $super = $this->createDirectory('scale-submodule-super-');
        $pitClone = $this->createDirectory('scale-submodule-pit-');
        $gitClone = $this->createDirectory('scale-submodule-git-');

        $this->initRepo($dep2);
        file_put_contents($dep2 . '/dep2.txt', "dep2\n");
        $this->git($dep2, 'add dep2.txt');
        $this->git($dep2, 'commit -m dep2');

        $this->initRepo($dep1);
        $this->git($dep1, '-c protocol.file.allow=always submodule add ' . escapeshellarg($dep2) . ' nested/dep2');
        $this->git($dep1, 'commit -am "add dep2"');

        $this->initRepo($super);
        $this->git($super, '-c protocol.file.allow=always submodule add ' . escapeshellarg($dep1) . ' vendor/dep1');
        $this->git($super, 'commit -am "add dep1"');

        $this->git(dirname($pitClone), 'clone ' . escapeshellarg($super) . ' ' . escapeshellarg($pitClone));
        $this->git(dirname($gitClone), 'clone ' . escapeshellarg($super) . ' ' . escapeshellarg($gitClone));

        $pitRepo = Pitmaster::open($pitClone);
        $topManager = new SubmoduleManager($pitRepo->objectDatabase(), $pitRepo->workDir(), $pitRepo->gitDir());
        $topManager->init();
        $topManager->update($pitRepo->head()->tree);

        $nestedRepo = Pitmaster::open($pitClone . '/vendor/dep1');
        $nestedManager = new SubmoduleManager($nestedRepo->objectDatabase(), $nestedRepo->workDir(), $nestedRepo->gitDir());
        $nestedManager->init();
        $nestedManager->update($nestedRepo->head()->tree);

        $this->git($gitClone, '-c protocol.file.allow=always submodule update --init --recursive');

        self::assertSame(
            trim($this->git($gitClone . '/vendor/dep1', 'rev-parse HEAD')),
            trim($this->git($pitClone . '/vendor/dep1', 'rev-parse HEAD')),
        );
        self::assertSame(
            trim($this->git($gitClone . '/vendor/dep1/nested/dep2', 'rev-parse HEAD')),
            trim($this->git($pitClone . '/vendor/dep1/nested/dep2', 'rev-parse HEAD')),
        );
    }

    #[Test]
    public function notesStashAndReflogRemainReadableAcrossRepeatedReopenCycles(): void
    {
        $repoDir = $this->createDirectory('scale-longevity-');
        $this->initRepo($repoDir);
        file_put_contents($repoDir . '/tracked.txt', "base\n");
        $this->git($repoDir, 'add tracked.txt');
        $this->git($repoDir, 'commit -m base');

        for ($i = 1; $i <= 4; $i++) {
            file_put_contents($repoDir . '/tracked.txt', "commit {$i}\n");
            $this->git($repoDir, 'add tracked.txt');
            $this->git($repoDir, 'commit -m ' . escapeshellarg("commit {$i}"));
        }

        $repo = Pitmaster::open($repoDir);
        $notes = new Notes($repo->objectDatabase(), $repo->refDatabase());
        $commits = $repo->log(10);

        foreach (array_slice($commits, 0, 4) as $index => $commit) {
            $notes->set($commit->id, "note {$index}", 'refs/notes/review');
        }

        $stash = new Stash($repo->objectDatabase(), $repo->refDatabase(), $repo->gitDir(), $repo->workDir());

        for ($i = 1; $i <= 3; $i++) {
            file_put_contents($repoDir . '/tracked.txt', "stashed {$i}\n");
            file_put_contents($repoDir . "/untracked-{$i}.txt", "untracked {$i}\n");
            $stash->push("stash {$i}", true);
        }

        $repo->createBranch('longevity');
        $repo->checkout('longevity');
        file_put_contents($repoDir . '/tracked.txt', "longevity\n");
        $repo->add('tracked.txt');
        $repo->commit("longevity\n");
        $repo->checkout('main');

        $reopened = Pitmaster::open($repoDir);
        $reopenedNotes = new Notes($reopened->objectDatabase(), $reopened->refDatabase());
        $stashReflog = Reflog::open($reopened->gitDir(), 'refs/stash');

        self::assertSame('note 0', $reopenedNotes->get($commits[0]->id, 'refs/notes/review'));
        self::assertCount(3, (new Stash(
            $reopened->objectDatabase(),
            $reopened->refDatabase(),
            $reopened->gitDir(),
            $reopened->workDir(),
        ))->listEntries());
        self::assertSame(3, $stashReflog->count());
    }

    private function initRepo(string $dir): void
    {
        $this->git($dir, 'init --initial-branch=main');
        $this->git($dir, 'config user.email test@pitmaster.dev');
        $this->git($dir, 'config user.name "Test User"');
    }

    private function startGitHttpBackendServer(string $projectRoot): string
    {
        $dir = $this->createDirectory('scale-http-server-');
        $port = $this->freePort();
        $router = dirname(__DIR__) . '/Fixtures/git_http_backend_router.php';
        $process = proc_open(
            sprintf('%s -S 127.0.0.1:%d %s', escapeshellarg(PHP_BINARY), $port, escapeshellarg($router)),
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', $dir . '/server.out.log', 'a'],
                2 => ['file', $dir . '/server.err.log', 'a'],
            ],
            $pipes,
            dirname(__DIR__, 2),
            [
                'PITMASTER_GIT_HTTP_PROJECT_ROOT' => $projectRoot,
                'PITMASTER_GIT_HTTP_BACKEND' => GitTestRuntime::gitHttpBackend(),
            ],
        );

        if (!is_resource($process)) {
            self::fail('Failed to start git-http-backend server');
        }

        $this->processes[] = $process;
        $baseUrl = "http://127.0.0.1:{$port}";

        for ($i = 0; $i < 40; $i++) {
            $health = @file_get_contents($baseUrl . '/health');

            if ($health !== false) {
                return $baseUrl;
            }

            usleep(100000);
        }

        self::fail('git-http-backend test server did not become ready');
    }

    /**
     * @return list<string>
     */
    private function gitLines(string $dir, string $command): array
    {
        return array_values(array_filter(
            explode("\n", trim($this->git($dir, $command))),
            static fn (string $line): bool => $line !== '',
        ));
    }

    private function singlePath(string $dir, string $pattern): string
    {
        $matches = glob($dir . '/' . $pattern);

        if ($matches === false || count($matches) !== 1) {
            self::fail("Expected exactly one match for {$pattern}");
        }

        return $matches[0];
    }

    private function createDirectory(string $prefix): string
    {
        $path = Workspace::createDirectory($prefix);
        $this->paths[] = $path;

        return $path;
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($socket === false) {
            self::fail("Unable to allocate test port: {$errstr}");
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        if ($name === false) {
            self::fail('Unable to read allocated test port');
        }

        return (int) substr((string) strrchr($name, ':'), 1);
    }

    private function git(string $dir, string $command): string
    {
        exec(
            sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command),
            $output,
            $exitCode,
        );

        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            self::fail("git {$command} failed in {$dir}:\n{$result}");
        }

        return $result . ($result === '' ? '' : "\n");
    }
}
