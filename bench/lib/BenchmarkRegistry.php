<?php

declare(strict_types=1);

namespace Pitmaster\Bench;

use Pitmaster\Checkout\SparseCheckout;
use Pitmaster\Graph\Bisect;
use Pitmaster\Graph\Blame;
use Pitmaster\Graph\Grep;
use Pitmaster\Index\Index;
use Pitmaster\Index\IndexWriter;
use Pitmaster\Lfs\LfsClient;
use Pitmaster\Lfs\LfsPointer;
use Pitmaster\Object\ObjectId;
use Pitmaster\Pitmaster;
use Pitmaster\Protocol\GitProtocolClient;
use Pitmaster\Protocol\PktLine;
use Pitmaster\Protocol\SshClient;
use Pitmaster\Ref\Notes;
use Pitmaster\Stash\Stash;
use Pitmaster\Submodule\SubmoduleManager;

final class BenchmarkRegistry
{
    /**
     * @return list<BenchmarkCase>
     */
    public static function all(): array
    {
        return [
            self::openCase('open.small', 'repo-small', ['smoke', 'core', 'open'], 'Open a small repository', 10, 1),
            self::openCase('open.medium', 'repo-medium-clean', ['core', 'open'], 'Open a medium repository', 8, 1),
            self::openCase('open.large', 'repo-large-tree', ['smoke', 'core', 'open'], 'Open a large repository', 6, 1),
            self::statusCase('status.clean', 'repo-medium-clean', ['core', 'status'], 'Compute status() on a clean medium tree', 6, 1),
            self::statusCase('status.dirty', 'repo-medium-dirty', ['smoke', 'core', 'status'], 'Compute status() on a dirty medium tree', 6, 1),
            self::objectReadCase('objects.loose.read', 'objects-loose', ['core', 'objects'], 'Read a sample of loose objects', 5, 1),
            self::objectReadCase('objects.packed.read', 'objects-packed', ['smoke', 'core', 'objects'], 'Read a sample of packed objects', 5, 1),
            self::objectReadCase('objects.mixed.read', 'objects-mixed', ['core', 'objects'], 'Read a sample of mixed-store objects', 5, 1),
            self::logCase(),
            self::mergeBaseCase(),
            self::refsCase(),
            self::diffCase(),
            self::statusSparseCase(),
            self::indexReadCase(),
            self::indexWriteCase(),
            self::blameCase(),
            self::grepCase(),
            self::bisectCase(),
            self::checkoutCase(),
            self::resetHardCase(),
            self::restoreCase(),
            self::removeDirectoryCase(),
            self::moveDirectoryCase(),
            self::stashCase('workflow.stash.tracked', false),
            self::stashCase('workflow.stash.untracked', true),
            self::notesListCase(),
            self::worktreeListCase(),
            self::submoduleStatusCase(),
            self::pktLineEncodeCase(),
            self::pktLineDecodeCase(),
            self::smartHttpCloneCase(),
            self::smartHttpFetchCase(),
            self::smartHttpPushCase(),
            self::gitProtocolDiscoveryCase(),
            self::sshDiscoveryCase(),
            self::lfsBatchCase(),
        ];
    }

    /**
     * @return list<BenchmarkCase>
     */
    public static function forSuite(string $suite): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (BenchmarkCase $case): bool => $case->belongsTo($suite),
        ));
    }

    private static function openCase(
        string $name,
        string $fixture,
        array $groups,
        string $description,
        int $runs,
        int $warmups,
    ): BenchmarkCase {
        return BenchmarkCase::define(
            $name,
            $description,
            $groups,
            $runs,
            $warmups,
            ['fixture' => $fixture],
            static function (BenchmarkRuntime $runtime) use ($fixture): array {
                $runtime->fixtures->ensure($fixture);

                return ['path' => $runtime->fixtures->repoPath($fixture)];
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                Pitmaster::open($suiteContext['path']);
            },
        );
    }

    private static function statusCase(
        string $name,
        string $fixture,
        array $groups,
        string $description,
        int $runs,
        int $warmups,
    ): BenchmarkCase {
        return BenchmarkCase::define(
            $name,
            $description,
            $groups,
            $runs,
            $warmups,
            ['fixture' => $fixture],
            static function (BenchmarkRuntime $runtime) use ($fixture): array {
                $runtime->fixtures->ensure($fixture);

                return ['path' => $runtime->fixtures->repoPath($fixture)];
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $repo = Pitmaster::open($suiteContext['path']);
                $repo->status();
            },
        );
    }

    private static function objectReadCase(
        string $name,
        string $fixture,
        array $groups,
        string $description,
        int $runs,
        int $warmups,
    ): BenchmarkCase {
        return BenchmarkCase::define(
            $name,
            $description,
            $groups,
            $runs,
            $warmups,
            ['fixture' => $fixture, 'sample_objects' => 100],
            static function (BenchmarkRuntime $runtime) use ($fixture): array {
                $manifest = $runtime->fixtures->ensure($fixture);

                return [
                    'path' => $runtime->fixtures->repoPath($fixture),
                    'objects' => array_slice($manifest['objects'] ?? [], 0, 100),
                ];
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $repo = Pitmaster::open($suiteContext['path']);

                foreach ($suiteContext['objects'] as $object) {
                    $repo->readObject($object);
                }
            },
        );
    }

    private static function logCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'log.long-history',
            'Walk log() through a long history fixture',
            ['smoke', 'core', 'graph'],
            5,
            1,
            ['fixture' => 'history-long', 'limit' => 300],
            static function (BenchmarkRuntime $runtime): array {
                $runtime->fixtures->ensure('history-long');

                return ['path' => $runtime->fixtures->repoPath('history-long')];
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $repo = Pitmaster::open($suiteContext['path']);
                $repo->log(300);
            },
        );
    }

    private static function mergeBaseCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'graph.merge-base',
            'Compute merge-base() across diverged long-history tips',
            ['core', 'graph'],
            5,
            1,
            ['fixture' => 'history-long'],
            static function (BenchmarkRuntime $runtime): array {
                $manifest = $runtime->fixtures->ensure('history-long');

                return [
                    'path' => $runtime->fixtures->repoPath('history-long'),
                    'main' => $manifest['main_head'],
                    'feature' => $manifest['feature_head'],
                ];
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $repo = Pitmaster::open($suiteContext['path']);
                $repo->mergeBase(
                    ObjectId::fromHex($suiteContext['main']),
                    ObjectId::fromHex($suiteContext['feature']),
                );
            },
        );
    }

    private static function refsCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'refs.list-many',
            'List refs in a repo with many branches and tags',
            ['core', 'refs'],
            5,
            1,
            ['fixture' => 'refs-many'],
            static function (BenchmarkRuntime $runtime): array {
                $runtime->fixtures->ensure('refs-many');

                return ['path' => $runtime->fixtures->repoPath('refs-many')];
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $repo = Pitmaster::open($suiteContext['path']);
                $repo->allRefs();
            },
        );
    }

    private static function diffCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'diff.rename-heavy',
            'Run diffTree() across a rename-heavy history pair',
            ['smoke', 'core', 'diff'],
            4,
            1,
            ['fixture' => 'rename-heavy', 'algorithm' => 'myers'],
            static function (BenchmarkRuntime $runtime): array {
                $manifest = $runtime->fixtures->ensure('rename-heavy');

                return [
                    'path' => $runtime->fixtures->repoPath('rename-heavy'),
                    'from_tree' => $manifest['from_tree'],
                    'to_tree' => $manifest['to_tree'],
                ];
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $repo = Pitmaster::open($suiteContext['path']);
                $repo->diffTree(
                    ObjectId::fromHex($suiteContext['from_tree']),
                    ObjectId::fromHex($suiteContext['to_tree']),
                    'myers',
                );
            },
        );
    }

    private static function statusSparseCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'status.sparse.large',
            'Compute status() on a sparse large tree',
            ['core', 'status', 'workflow'],
            4,
            1,
            ['fixture' => 'repo-large-tree'],
            static function (BenchmarkRuntime $runtime): array {
                $manifest = $runtime->fixtures->ensure('repo-large-tree');

                return [
                    'source' => $runtime->fixtures->repoPath('repo-large-tree'),
                    'paths' => $manifest['paths'] ?? [],
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, int $iteration): array {
                $iterationRoot = $runtime->freshWorkspace("bench-status-sparse-{$iteration}");
                $repoPath = $iterationRoot . '/repo';
                BenchmarkFilesystem::copyDirectory($suiteContext['source'], $repoPath);
                $repo = Pitmaster::open($repoPath);
                $sparse = new SparseCheckout($repo->gitDir());
                $directories = array_values(array_unique(array_map(
                    static fn (string $path): string => explode('/', $path)[0] . '/' . explode('/', $path)[1],
                    array_slice($suiteContext['paths'], 0, 24),
                )));
                $sparse->init();
                $sparse->set($directories);

                return [
                    'repo_path' => $repoPath,
                    'workspace' => $iterationRoot,
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                Pitmaster::open($iterationContext['repo_path'])->status();
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                BenchmarkFilesystem::removeDirectory($iterationContext['workspace']);
            },
        );
    }

    private static function indexReadCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'index.read.large',
            'Open and parse a large working-tree index',
            ['core', 'workflow', 'index'],
            6,
            1,
            ['fixture' => 'repo-large-tree'],
            static function (BenchmarkRuntime $runtime): array {
                $runtime->fixtures->ensure('repo-large-tree');
                $path = $runtime->fixtures->repoPath('repo-large-tree');
                $repo = Pitmaster::open($path);

                return [
                    'index_path' => $path . '/.git/index',
                    'hash_bytes' => $repo->head()->id->hashLength(),
                ];
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                Index::open($suiteContext['index_path'], $suiteContext['hash_bytes']);
            },
        );
    }

    private static function indexWriteCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'index.write.large',
            'Serialize a large index to disk',
            ['core', 'workflow', 'index'],
            5,
            1,
            ['fixture' => 'repo-large-tree'],
            static function (BenchmarkRuntime $runtime): array {
                $runtime->fixtures->ensure('repo-large-tree');

                return ['source' => $runtime->fixtures->repoPath('repo-large-tree')];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, int $iteration): array {
                $iterationRoot = $runtime->freshWorkspace("bench-index-write-{$iteration}");
                $repoPath = $iterationRoot . '/repo';
                BenchmarkFilesystem::copyDirectory($suiteContext['source'], $repoPath);
                $repo = Pitmaster::open($repoPath);

                return [
                    'workspace' => $iterationRoot,
                    'index' => $repo->index(),
                    'index_path' => $iterationRoot . '/bench.index',
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                IndexWriter::write($iterationContext['index'], $iterationContext['index_path']);
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                BenchmarkFilesystem::removeDirectory($iterationContext['workspace']);
            },
        );
    }

    private static function blameCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'graph.blame.medium',
            'Run blame() across a long-history file',
            ['core', 'graph', 'search'],
            3,
            1,
            ['fixture' => 'history-long', 'path' => 'history.txt'],
            static function (BenchmarkRuntime $runtime): array {
                $manifest = $runtime->fixtures->ensure('history-long');

                return [
                    'path' => $runtime->fixtures->repoPath('history-long'),
                    'head' => $manifest['head'],
                    'target' => 'history.txt',
                ];
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $repo = Pitmaster::open($suiteContext['path']);
                $blame = new Blame($repo->objectDatabase());
                $blame->blame(ObjectId::fromHex($suiteContext['head']), $suiteContext['target']);
            },
        );
    }

    private static function grepCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'graph.grep.large',
            'Run grep() across a large tree',
            ['core', 'graph', 'search'],
            4,
            1,
            ['fixture' => 'repo-large-tree', 'pattern' => 'a'],
            static function (BenchmarkRuntime $runtime): array {
                $runtime->fixtures->ensure('repo-large-tree');
                $path = $runtime->fixtures->repoPath('repo-large-tree');
                $repo = Pitmaster::open($path);

                return [
                    'path' => $path,
                    'tree' => $repo->head()->tree->hex,
                    'pattern' => 'a',
                ];
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $repo = Pitmaster::open($suiteContext['path']);
                $grep = new Grep($repo->objectDatabase());
                $grep->grep(ObjectId::fromHex($suiteContext['tree']), $suiteContext['pattern']);
            },
        );
    }

    private static function bisectCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'graph.bisect.long-history',
            'Start bisect across a long diverged history',
            ['core', 'graph', 'workflow'],
            3,
            1,
            ['fixture' => 'history-long'],
            static function (BenchmarkRuntime $runtime): array {
                $manifest = $runtime->fixtures->ensure('history-long');

                return [
                    'source' => $runtime->fixtures->repoPath('history-long'),
                    'bad' => $manifest['main_head'],
                    'good' => $manifest['merge_base'],
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, int $iteration): array {
                $iterationRoot = $runtime->freshWorkspace("bench-bisect-{$iteration}");
                $repoPath = $iterationRoot . '/repo';
                BenchmarkFilesystem::copyDirectory($suiteContext['source'], $repoPath);

                return [
                    'workspace' => $iterationRoot,
                    'repo_path' => $repoPath,
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                $repo = Pitmaster::open($iterationContext['repo_path']);
                $repo->bisectStart($suiteContext['bad'], $suiteContext['good']);
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                BenchmarkFilesystem::removeDirectory($iterationContext['workspace']);
            },
        );
    }

    private static function checkoutCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'workflow.checkout.large',
            'Checkout a side branch in a large tree',
            ['workflow', 'core'],
            4,
            1,
            ['fixture' => 'repo-large-tree'],
            static function (BenchmarkRuntime $runtime): array {
                $runtime->fixtures->ensure('repo-large-tree');

                return ['source' => $runtime->fixtures->repoPath('repo-large-tree')];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, int $iteration): array {
                $iterationRoot = $runtime->freshWorkspace("bench-checkout-{$iteration}");
                $repoPath = $iterationRoot . '/repo';
                BenchmarkFilesystem::copyDirectory($suiteContext['source'], $repoPath);
                BenchmarkShell::git('branch bench HEAD~1', $repoPath);

                return [
                    'workspace' => $iterationRoot,
                    'repo_path' => $repoPath,
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                Pitmaster::open($iterationContext['repo_path'])->checkout('bench');
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                BenchmarkFilesystem::removeDirectory($iterationContext['workspace']);
            },
        );
    }

    private static function resetHardCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'workflow.reset.hard.large',
            'Reset a changed large tree back to HEAD',
            ['workflow', 'core'],
            4,
            1,
            ['fixture' => 'repo-large-tree'],
            static function (BenchmarkRuntime $runtime): array {
                $manifest = $runtime->fixtures->ensure('repo-large-tree');

                return [
                    'source' => $runtime->fixtures->repoPath('repo-large-tree'),
                    'paths' => $manifest['paths'] ?? [],
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, int $iteration): array {
                $iterationRoot = $runtime->freshWorkspace("bench-reset-hard-{$iteration}");
                $repoPath = $iterationRoot . '/repo';
                BenchmarkFilesystem::copyDirectory($suiteContext['source'], $repoPath);
                $paths = array_slice($suiteContext['paths'], 0, 24);

                foreach ($paths as $index => $path) {
                    file_put_contents($repoPath . '/' . $path, "reset {$iteration} {$index}\n", FILE_APPEND);
                }

                BenchmarkShell::git('add ' . implode(' ', array_map('escapeshellarg', array_slice($paths, 0, 12))), $repoPath);
                @unlink($repoPath . '/' . $paths[20]);
                BenchmarkFilesystem::writeFile($repoPath . '/bench-untracked.txt', "untracked\n");

                return [
                    'workspace' => $iterationRoot,
                    'repo_path' => $repoPath,
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                Pitmaster::open($iterationContext['repo_path'])->reset('HEAD', 'hard');
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                BenchmarkFilesystem::removeDirectory($iterationContext['workspace']);
            },
        );
    }

    private static function restoreCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'workflow.restore.path.large',
            'Restore a modified path in a large tree',
            ['workflow', 'core'],
            5,
            1,
            ['fixture' => 'repo-large-tree'],
            static function (BenchmarkRuntime $runtime): array {
                $manifest = $runtime->fixtures->ensure('repo-large-tree');

                return [
                    'source' => $runtime->fixtures->repoPath('repo-large-tree'),
                    'target' => $manifest['paths'][0] ?? 'large/dir-00/file-000.txt',
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, int $iteration): array {
                $iterationRoot = $runtime->freshWorkspace("bench-restore-{$iteration}");
                $repoPath = $iterationRoot . '/repo';
                BenchmarkFilesystem::copyDirectory($suiteContext['source'], $repoPath);
                file_put_contents($repoPath . '/' . $suiteContext['target'], "restore {$iteration}\n", FILE_APPEND);

                return [
                    'workspace' => $iterationRoot,
                    'repo_path' => $repoPath,
                    'target' => $suiteContext['target'],
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                Pitmaster::open($iterationContext['repo_path'])->restore($iterationContext['target'], null, false, true);
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                BenchmarkFilesystem::removeDirectory($iterationContext['workspace']);
            },
        );
    }

    private static function removeDirectoryCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'workflow.rm.directory.large',
            'Remove a full directory worth of tracked files',
            ['workflow', 'core'],
            4,
            1,
            ['fixture' => 'repo-large-tree'],
            static function (BenchmarkRuntime $runtime): array {
                $manifest = $runtime->fixtures->ensure('repo-large-tree');
                $directoryPaths = array_values(array_filter(
                    $manifest['paths'] ?? [],
                    static fn (string $path): bool => str_starts_with($path, 'large/dir-00/'),
                ));

                return [
                    'source' => $runtime->fixtures->repoPath('repo-large-tree'),
                    'paths' => $directoryPaths,
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, int $iteration): array {
                $iterationRoot = $runtime->freshWorkspace("bench-rm-{$iteration}");
                $repoPath = $iterationRoot . '/repo';
                BenchmarkFilesystem::copyDirectory($suiteContext['source'], $repoPath);

                return [
                    'workspace' => $iterationRoot,
                    'repo_path' => $repoPath,
                    'paths' => $suiteContext['paths'],
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                Pitmaster::open($iterationContext['repo_path'])->remove(...$iterationContext['paths']);
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                BenchmarkFilesystem::removeDirectory($iterationContext['workspace']);
            },
        );
    }

    private static function moveDirectoryCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'workflow.mv.directory.large',
            'Move a full directory in a large tree',
            ['workflow', 'core'],
            4,
            1,
            ['fixture' => 'repo-large-tree'],
            static function (BenchmarkRuntime $runtime): array {
                $runtime->fixtures->ensure('repo-large-tree');

                return ['source' => $runtime->fixtures->repoPath('repo-large-tree')];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, int $iteration): array {
                $iterationRoot = $runtime->freshWorkspace("bench-mv-{$iteration}");
                $repoPath = $iterationRoot . '/repo';
                BenchmarkFilesystem::copyDirectory($suiteContext['source'], $repoPath);

                return [
                    'workspace' => $iterationRoot,
                    'repo_path' => $repoPath,
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                Pitmaster::open($iterationContext['repo_path'])->mv('large/dir-00', 'bench-moved/dir-00');
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                BenchmarkFilesystem::removeDirectory($iterationContext['workspace']);
            },
        );
    }

    private static function stashCase(string $name, bool $includeUntracked): BenchmarkCase
    {
        return BenchmarkCase::define(
            $name,
            $includeUntracked ? 'Push a stash including untracked files' : 'Push a tracked-file stash',
            ['workflow', 'advanced'],
            4,
            1,
            ['fixture' => 'repo-medium-dirty', 'include_untracked' => $includeUntracked],
            static function (BenchmarkRuntime $runtime): array {
                $runtime->fixtures->ensure('repo-medium-dirty');

                return ['source' => $runtime->fixtures->repoPath('repo-medium-dirty')];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, int $iteration): array {
                $iterationRoot = $runtime->freshWorkspace("bench-stash-{$iteration}");
                $repoPath = $iterationRoot . '/repo';
                BenchmarkFilesystem::copyDirectory($suiteContext['source'], $repoPath);

                return [
                    'workspace' => $iterationRoot,
                    'repo_path' => $repoPath,
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext) use ($includeUntracked): void {
                $repo = Pitmaster::open($iterationContext['repo_path']);
                $stash = new Stash($repo->objectDatabase(), $repo->refDatabase(), $repo->gitDir(), $repo->workDir());
                $stash->push('bench stash', $includeUntracked);
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                BenchmarkFilesystem::removeDirectory($iterationContext['workspace']);
            },
        );
    }

    private static function notesListCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'workflow.notes.list-heavy',
            'List a heavy notes tree',
            ['workflow', 'advanced', 'refs'],
            4,
            1,
            ['fixture' => 'history-long'],
            static function (BenchmarkRuntime $runtime): array {
                $manifest = $runtime->fixtures->ensure('history-long');

                return [
                    'source' => $runtime->fixtures->repoPath('history-long'),
                    'commits' => array_slice($manifest['commits'] ?? [], 0, 64),
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, int $iteration): array {
                $iterationRoot = $runtime->freshWorkspace("bench-notes-{$iteration}");
                $repoPath = $iterationRoot . '/repo';
                BenchmarkFilesystem::copyDirectory($suiteContext['source'], $repoPath);
                $repo = Pitmaster::open($repoPath);
                $notes = new Notes($repo->objectDatabase(), $repo->refDatabase());

                foreach ($suiteContext['commits'] as $index => $hex) {
                    $notes->set(ObjectId::fromHex($hex), "note {$index}", 'refs/notes/review');
                }

                return [
                    'workspace' => $iterationRoot,
                    'repo_path' => $repoPath,
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                $repo = Pitmaster::open($iterationContext['repo_path']);
                $notes = new Notes($repo->objectDatabase(), $repo->refDatabase());
                $notes->listAll('refs/notes/review');
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                BenchmarkFilesystem::removeDirectory($iterationContext['workspace']);
            },
        );
    }

    private static function smartHttpCloneCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'transport.smart-http.clone',
            'Clone a local smart HTTP remote over loopback',
            ['all', 'transport'],
            3,
            0,
            ['fixture' => 'repo-small'],
            static function (BenchmarkRuntime $runtime): array {
                $runtime->fixtures->ensure('repo-small');
                $workspace = $runtime->freshWorkspace('bench-clone');
                $projectRoot = $workspace . '/projects';
                BenchmarkFilesystem::ensureDirectory($projectRoot);
                $server = new GitHttpServer($projectRoot, $runtime->root . '/tests/Fixtures/git_http_backend_router.php');
                $server->start();

                return [
                    'workspace' => $workspace,
                    'project_root' => $projectRoot,
                    'server' => $server,
                    'source' => $runtime->fixtures->repoPath('repo-small'),
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, int $iteration): array {
                $remoteDir = $suiteContext['project_root'] . "/remote-{$iteration}.git";
                $destDir = $suiteContext['workspace'] . "/clone-{$iteration}";
                BenchmarkShell::git('clone --bare ' . escapeshellarg($suiteContext['source']) . ' ' . escapeshellarg($remoteDir), $suiteContext['workspace']);

                return [
                    'remote_dir' => $remoteDir,
                    'remote_url' => $suiteContext['server']->baseUrl() . '/remote-' . $iteration . '.git',
                    'dest_dir' => $destDir,
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                Pitmaster::clone($iterationContext['remote_url'], $iterationContext['dest_dir']);
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                BenchmarkFilesystem::removeDirectory($iterationContext['dest_dir']);
                BenchmarkFilesystem::removeDirectory($iterationContext['remote_dir']);
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $suiteContext['server']->stop();
                BenchmarkFilesystem::removeDirectory($suiteContext['workspace']);
            },
        );
    }

    private static function smartHttpFetchCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'transport.smart-http.fetch',
            'Fetch a small remote update over loopback smart HTTP',
            ['all', 'transport'],
            3,
            0,
            ['fixture' => 'repo-small'],
            static function (BenchmarkRuntime $runtime): array {
                $runtime->fixtures->ensure('repo-small');
                $workspace = $runtime->freshWorkspace('bench-fetch');
                $projectRoot = $workspace . '/projects';
                BenchmarkFilesystem::ensureDirectory($projectRoot);
                $server = new GitHttpServer($projectRoot, $runtime->root . '/tests/Fixtures/git_http_backend_router.php');
                $server->start();

                return [
                    'workspace' => $workspace,
                    'project_root' => $projectRoot,
                    'server' => $server,
                    'source' => $runtime->fixtures->repoPath('repo-small'),
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, int $iteration): array {
                $iterationRoot = $suiteContext['workspace'] . "/iteration-{$iteration}";
                BenchmarkFilesystem::ensureDirectory($iterationRoot);
                $remoteDir = $suiteContext['project_root'] . "/fetch-remote-{$iteration}.git";
                $sourceDir = $iterationRoot . '/source';
                $cloneDir = $iterationRoot . '/clone';
                BenchmarkFilesystem::copyDirectory($suiteContext['source'], $sourceDir);
                BenchmarkShell::git('clone --bare ' . escapeshellarg($sourceDir) . ' ' . escapeshellarg($remoteDir), $iterationRoot);
                BenchmarkShell::git('config http.receivepack true', $remoteDir);
                Pitmaster::clone($suiteContext['server']->baseUrl() . '/fetch-remote-' . $iteration . '.git', $cloneDir);

                BenchmarkFilesystem::writeFile($sourceDir . '/bench-fetch.txt', "fetch {$iteration}\n");
                BenchmarkShell::git('add bench-fetch.txt', $sourceDir);
                BenchmarkShell::git('commit -m ' . escapeshellarg("bench fetch {$iteration}"), $sourceDir, [
                    'GIT_AUTHOR_NAME' => 'Pitmaster Bench',
                    'GIT_AUTHOR_EMAIL' => 'bench@pitmaster.dev',
                    'GIT_COMMITTER_NAME' => 'Pitmaster Bench',
                    'GIT_COMMITTER_EMAIL' => 'bench@pitmaster.dev',
                    'GIT_AUTHOR_DATE' => '2024-01-01T00:00:00 +0000',
                    'GIT_COMMITTER_DATE' => '2024-01-01T00:00:00 +0000',
                ]);
                BenchmarkShell::git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
                BenchmarkShell::git('push origin main', $sourceDir);

                return [
                    'iteration_root' => $iterationRoot,
                    'clone_dir' => $cloneDir,
                    'remote_dir' => $remoteDir,
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                $repo = Pitmaster::open($iterationContext['clone_dir']);
                $repo->fetch();
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                BenchmarkFilesystem::removeDirectory($iterationContext['iteration_root']);
                BenchmarkFilesystem::removeDirectory($iterationContext['remote_dir']);
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $suiteContext['server']->stop();
                BenchmarkFilesystem::removeDirectory($suiteContext['workspace']);
            },
        );
    }

    private static function smartHttpPushCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'transport.smart-http.push',
            'Push a local commit over loopback smart HTTP',
            ['all', 'transport'],
            3,
            0,
            ['fixture' => 'repo-small'],
            static function (BenchmarkRuntime $runtime): array {
                $runtime->fixtures->ensure('repo-small');
                $workspace = $runtime->freshWorkspace('bench-push');
                $projectRoot = $workspace . '/projects';
                BenchmarkFilesystem::ensureDirectory($projectRoot);
                $server = new GitHttpServer($projectRoot, $runtime->root . '/tests/Fixtures/git_http_backend_router.php');
                $server->start();

                return [
                    'workspace' => $workspace,
                    'project_root' => $projectRoot,
                    'server' => $server,
                    'source' => $runtime->fixtures->repoPath('repo-small'),
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, int $iteration): array {
                $iterationRoot = $suiteContext['workspace'] . "/iteration-{$iteration}";
                BenchmarkFilesystem::ensureDirectory($iterationRoot);
                $remoteDir = $suiteContext['project_root'] . "/push-remote-{$iteration}.git";
                BenchmarkShell::git('clone --bare ' . escapeshellarg($suiteContext['source']) . ' ' . escapeshellarg($remoteDir), $iterationRoot);
                BenchmarkShell::git('config http.receivepack true', $remoteDir);
                $cloneDir = $iterationRoot . '/clone';
                $repo = Pitmaster::clone($suiteContext['server']->baseUrl() . '/push-remote-' . $iteration . '.git', $cloneDir);
                BenchmarkFilesystem::writeFile($cloneDir . '/bench-push.txt', "push {$iteration}\n");
                $repo->add('bench-push.txt');
                $repo->commit("bench push {$iteration}\n");

                return [
                    'iteration_root' => $iterationRoot,
                    'clone_dir' => $cloneDir,
                    'remote_dir' => $remoteDir,
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                $repo = Pitmaster::open($iterationContext['clone_dir']);
                $repo->push();
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                BenchmarkFilesystem::removeDirectory($iterationContext['iteration_root']);
                BenchmarkFilesystem::removeDirectory($iterationContext['remote_dir']);
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $suiteContext['server']->stop();
                BenchmarkFilesystem::removeDirectory($suiteContext['workspace']);
            },
        );
    }

    private static function worktreeListCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'workflow.worktree.list-many',
            'List many linked worktrees from the main repository',
            ['workflow', 'advanced'],
            4,
            1,
            ['fixture' => 'repo-medium-clean'],
            static function (BenchmarkRuntime $runtime): array {
                $runtime->fixtures->ensure('repo-medium-clean');

                return ['source' => $runtime->fixtures->repoPath('repo-medium-clean')];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, int $iteration): array {
                $iterationRoot = $runtime->freshWorkspace("bench-worktrees-{$iteration}");
                $repoPath = $iterationRoot . '/repo';
                BenchmarkFilesystem::copyDirectory($suiteContext['source'], $repoPath);
                $repo = Pitmaster::open($repoPath);

                for ($i = 0; $i < 8; $i++) {
                    $repo->addWorktree(
                        $iterationRoot . "/wt-{$i}",
                        "bench-worktree-{$i}",
                        null,
                        "bench-worktree-{$i}",
                    );
                }

                return [
                    'workspace' => $iterationRoot,
                    'repo_path' => $repoPath,
                ];
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                Pitmaster::open($iterationContext['repo_path'])->worktrees();
            },
            static function (BenchmarkRuntime $runtime, array $suiteContext, array $iterationContext): void {
                BenchmarkFilesystem::removeDirectory($iterationContext['workspace']);
            },
        );
    }

    private static function submoduleStatusCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'workflow.submodule.status',
            'Compute submodule status in a nested topology',
            ['workflow', 'advanced'],
            3,
            1,
            ['topology' => 'nested-submodules'],
            static function (BenchmarkRuntime $runtime): array {
                $workspace = $runtime->freshWorkspace('bench-submodules');
                $dep2 = $workspace . '/dep2';
                $dep1 = $workspace . '/dep1';
                $super = $workspace . '/super';
                self::initializeBenchmarkRepo($dep2);
                BenchmarkFilesystem::writeFile($dep2 . '/dep2.txt', "dep2\n");
                self::commitAll($dep2, 'dep2 root');
                self::initializeBenchmarkRepo($dep1);
                BenchmarkFilesystem::writeFile($dep1 . '/dep1.txt', "dep1\n");
                self::commitAll($dep1, 'dep1 root');
                BenchmarkShell::git('-c protocol.file.allow=always submodule add ' . escapeshellarg($dep2) . ' nested/dep2', $dep1);
                self::commitAll($dep1, 'Add nested dep2');
                self::initializeBenchmarkRepo($super);
                BenchmarkFilesystem::writeFile($super . '/app.txt', "super\n");
                self::commitAll($super, 'super root');
                BenchmarkShell::git('-c protocol.file.allow=always submodule add ' . escapeshellarg($dep1) . ' vendor/dep1', $super);
                self::commitAll($super, 'Add dep1');
                $pitClone = $workspace . '/pit-clone';
                BenchmarkFilesystem::copyDirectory($super, $pitClone);
                $repo = Pitmaster::open($pitClone);
                $manager = new SubmoduleManager($repo->objectDatabase(), $repo->workDir(), $repo->gitDir());
                $manager->update($repo->head()->tree);

                return [
                    'workspace' => $workspace,
                    'repo_path' => $pitClone,
                ];
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $repo = Pitmaster::open($suiteContext['repo_path']);
                $manager = new SubmoduleManager($repo->objectDatabase(), $repo->workDir(), $repo->gitDir());
                $manager->status($repo->head()->tree);
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                BenchmarkFilesystem::removeDirectory($suiteContext['workspace']);
            },
        );
    }

    private static function gitProtocolDiscoveryCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'transport.git.discovery',
            'Discover refs over loopback git:// against a ref-heavy remote',
            ['transport', 'protocol'],
            4,
            0,
            ['fixture' => 'refs-many'],
            static function (BenchmarkRuntime $runtime): array {
                $runtime->fixtures->ensure('refs-many');
                $workspace = $runtime->freshWorkspace('bench-git-daemon');
                $projectRoot = $workspace . '/exports';
                BenchmarkFilesystem::ensureDirectory($projectRoot);
                BenchmarkShell::git('clone --bare ' . escapeshellarg($runtime->fixtures->repoPath('refs-many')) . ' ' . escapeshellarg($projectRoot . '/remote.git'), $workspace);
                $daemon = new GitDaemonServer($projectRoot, $workspace . '/logs');
                $daemon->start();

                return [
                    'workspace' => $workspace,
                    'daemon' => $daemon,
                ];
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $client = new GitProtocolClient();
                $client->discoverRefs($suiteContext['daemon']->url('remote.git'));
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $suiteContext['daemon']->stop();
                BenchmarkFilesystem::removeDirectory($suiteContext['workspace']);
            },
        );
    }

    private static function sshDiscoveryCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'transport.ssh.discovery',
            'Discover refs through the SSH transport using a repo-local mock command',
            ['transport', 'protocol'],
            4,
            0,
            ['fixture' => 'refs-many'],
            static function (BenchmarkRuntime $runtime): array {
                $runtime->fixtures->ensure('refs-many');
                $workspace = $runtime->freshWorkspace('bench-ssh');
                $sshRoot = $workspace . '/ssh-root';
                BenchmarkFilesystem::ensureDirectory($sshRoot);
                BenchmarkShell::git('clone --bare ' . escapeshellarg($runtime->fixtures->repoPath('refs-many')) . ' ' . escapeshellarg($sshRoot . '/ssh-remote.git'), $workspace);
                $sshCommand = $workspace . '/mock-ssh.sh';
                BenchmarkFilesystem::writeFile($sshCommand, implode("\n", [
                    '#!/usr/bin/env bash',
                    'set -euo pipefail',
                    'remote_cmd="${@: -1}"',
                    'cd "${PITMASTER_BENCH_SSH_ROOT:?}"',
                    'exec bash -lc "$remote_cmd"',
                    '',
                ]));
                chmod($sshCommand, 0755);

                $previousCommand = getenv('PITMASTER_SSH_COMMAND');
                $previousRoot = getenv('PITMASTER_BENCH_SSH_ROOT');
                $previousStrict = getenv('PITMASTER_SSH_STRICT_HOST_KEY_CHECKING');
                putenv('PITMASTER_SSH_COMMAND=' . $sshCommand);
                putenv('PITMASTER_BENCH_SSH_ROOT=' . $sshRoot);
                putenv('PITMASTER_SSH_STRICT_HOST_KEY_CHECKING=no');

                return [
                    'workspace' => $workspace,
                    'previous_command' => $previousCommand === false ? null : $previousCommand,
                    'previous_root' => $previousRoot === false ? null : $previousRoot,
                    'previous_strict' => $previousStrict === false ? null : $previousStrict,
                    'url' => 'git@pitmaster-bench:ssh-remote.git',
                ];
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $client = new SshClient();
                $client->discoverRefs($suiteContext['url']);
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                putenv($suiteContext['previous_command'] !== null ? 'PITMASTER_SSH_COMMAND=' . $suiteContext['previous_command'] : 'PITMASTER_SSH_COMMAND');
                putenv($suiteContext['previous_root'] !== null ? 'PITMASTER_BENCH_SSH_ROOT=' . $suiteContext['previous_root'] : 'PITMASTER_BENCH_SSH_ROOT');
                putenv($suiteContext['previous_strict'] !== null ? 'PITMASTER_SSH_STRICT_HOST_KEY_CHECKING=' . $suiteContext['previous_strict'] : 'PITMASTER_SSH_STRICT_HOST_KEY_CHECKING');
                BenchmarkFilesystem::removeDirectory($suiteContext['workspace']);
            },
        );
    }

    private static function lfsBatchCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'transport.lfs.batch',
            'Request a download batch from the repo-local LFS mock',
            ['transport', 'advanced'],
            5,
            0,
            ['objects' => 48],
            static function (BenchmarkRuntime $runtime): array {
                $workspace = $runtime->freshWorkspace('bench-lfs');
                $storage = $workspace . '/storage';
                BenchmarkFilesystem::ensureDirectory($storage);
                $server = new PhpRouterServer(
                    $runtime->root . '/tests/Fixtures/lfs_router.php',
                    '/__health',
                    $workspace . '/logs',
                    ['LFS_STORAGE_DIR' => $storage],
                );
                $server->start();
                $objects = [];

                for ($i = 0; $i < 48; $i++) {
                    $payload = "lfs bench {$i}\n";
                    $pointer = LfsPointer::forContent($payload);
                    BenchmarkFilesystem::writeFile($storage . '/' . $pointer->oid, $payload);
                    $objects[] = [
                        'oid' => $pointer->oid,
                        'size' => $pointer->size,
                    ];
                }

                return [
                    'workspace' => $workspace,
                    'server' => $server,
                    'objects' => $objects,
                    'url' => $server->baseUrl() . '/repo.git/info/lfs',
                ];
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $client = new LfsClient($suiteContext['url']);
                $client->batchDownload($suiteContext['objects']);
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $suiteContext['server']->stop();
                BenchmarkFilesystem::removeDirectory($suiteContext['workspace']);
            },
        );
    }

    private static function pktLineEncodeCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'protocol.pkt-line.encode',
            'Encode a medium pkt-line frame corpus',
            ['all', 'micro', 'protocol'],
            10,
            1,
            ['packets' => 512],
            static function (BenchmarkRuntime $runtime): array {
                $lines = [];

                for ($i = 0; $i < 512; $i++) {
                    $lines[] = sprintf(
                        "%040x refs/heads/bench-%03d\0multi_ack_detailed side-band-64k ofs-delta\n",
                        $i + 1,
                        $i,
                    );
                }

                return ['lines' => $lines];
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $buffer = '';

                foreach ($suiteContext['lines'] as $line) {
                    $buffer .= PktLine::encode($line);
                }

                $buffer .= PktLine::flush();

                if ($buffer === '') {
                    throw new \RuntimeException('pkt-line encode produced empty output');
                }
            },
        );
    }

    private static function pktLineDecodeCase(): BenchmarkCase
    {
        return BenchmarkCase::define(
            'protocol.pkt-line.decode',
            'Decode a medium pkt-line frame corpus',
            ['all', 'micro', 'protocol'],
            10,
            1,
            ['packets' => 512],
            static function (BenchmarkRuntime $runtime): array {
                $payload = '';

                for ($i = 0; $i < 512; $i++) {
                    $payload .= PktLine::encode(sprintf(
                        "%040x refs/heads/bench-%03d\0multi_ack_detailed side-band-64k ofs-delta\n",
                        $i + 1,
                        $i,
                    ));
                }

                $payload .= PktLine::flush();

                return ['payload' => $payload];
            },
            null,
            static function (BenchmarkRuntime $runtime, array $suiteContext): void {
                $decoded = PktLine::decode($suiteContext['payload']);

                if ($decoded === []) {
                    throw new \RuntimeException('pkt-line decode produced no frames');
                }
            },
        );
    }

    private static function initializeBenchmarkRepo(string $path): void
    {
        BenchmarkShell::git('init --initial-branch=main ' . escapeshellarg($path), dirname($path));
        BenchmarkShell::git('config user.name ' . escapeshellarg('Pitmaster Bench'), $path);
        BenchmarkShell::git('config user.email ' . escapeshellarg('bench@pitmaster.dev'), $path);
        BenchmarkShell::git('config commit.gpgSign false', $path);
        BenchmarkShell::git('config gc.auto 0', $path);
    }

    private static function commitAll(string $path, string $message): string
    {
        BenchmarkShell::git('add -A', $path);
        BenchmarkShell::git('commit -m ' . escapeshellarg($message), $path);

        return trim(BenchmarkShell::git('rev-parse HEAD', $path));
    }
}
