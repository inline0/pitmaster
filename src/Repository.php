<?php

declare(strict_types=1);

namespace Pitmaster;

use Pitmaster\Checkout\SparseCheckout;
use Pitmaster\Config\GitConfig;
use Pitmaster\Diff\DiffResult;
use Pitmaster\Diff\MyersDiff;
use Pitmaster\Diff\TreeDiff;
use Pitmaster\Exceptions\MergeConflictException;
use Pitmaster\Exceptions\ObjectNotFoundException;
use Pitmaster\Graph\CommitWalker;
use Pitmaster\Graph\RevisionParser;
use Pitmaster\Hooks\HookRunner;
use Pitmaster\Index\Index;
use Pitmaster\Index\IndexEntry;
use Pitmaster\Index\IndexWriter;
use Pitmaster\Merge\ConflictMarker;
use Pitmaster\Merge\MergeBase;
use Pitmaster\Merge\MergeResult;
use Pitmaster\Merge\ThreeWayMerge;
use Pitmaster\Exceptions\ProtocolException;
use Pitmaster\Object\Blob;
use Pitmaster\Object\Commit;
use Pitmaster\Object\GitObject;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;
use Pitmaster\Object\Tag;
use Pitmaster\Object\Tree;
use Pitmaster\Object\TreeEntry;
use Pitmaster\Pack\PackIndexer;
use Pitmaster\Pack\PackWriter;
use Pitmaster\Protocol\DumbHttpClient;
use Pitmaster\Protocol\SmartHttpClient;
use Pitmaster\Protocol\UploadPackClient;
use Pitmaster\Ref\RefDatabase;
use Pitmaster\Ref\Reflog;
use Pitmaster\Ref\SymbolicRef;
use Pitmaster\Status\FileStatus;
use Pitmaster\Status\StatusEntry;
use Pitmaster\Status\WorkingTreeStatus;
use Pitmaster\Storage\ObjectDatabase;

/**
 * Repository handle. Wraps a .git directory and provides all operations.
 *
 * Supports both regular repos (.git is a directory) and linked worktrees
 * (.git is a file containing "gitdir: <path>"). For linked worktrees,
 * shared resources (objects, packed-refs, config) come from the common
 * git dir, while per-worktree resources (HEAD, index) come from the
 * worktree-specific git dir.
 */
final class Repository
{
    private readonly ObjectDatabase $objects;
    private readonly RefDatabase $refs;
    private readonly GitConfig $config;

    /** Per-worktree git dir (may be .git/worktrees/<name> for linked worktrees) */
    private readonly string $gitDir;

    /** Common git dir (shared objects, config, packed-refs) */
    private readonly string $commonDir;

    /** Working tree root */
    private readonly string $workDir;

    /** Whether this is a linked worktree */
    private readonly bool $isLinkedWorktree;

    /** Whether this repository has no working tree */
    private readonly bool $isBare;

    public function __construct(string $path)
    {
        if (is_dir($path . '/.git')) {
            // Regular repo: .git is a directory
            $this->workDir = $path;
            $this->gitDir = $path . '/.git';
            $this->commonDir = $this->gitDir;
            $this->isLinkedWorktree = false;
            $this->isBare = false;
        } elseif (is_file($path . '/.git')) {
            // Linked worktree: .git is a file with "gitdir: <path>"
            $content = trim((string) file_get_contents($path . '/.git'));

            if (!str_starts_with($content, 'gitdir: ')) {
                throw new \InvalidArgumentException("Invalid .git file at {$path}");
            }

            $gitdir = substr($content, 8);

            // Resolve relative path
            if (!str_starts_with($gitdir, '/')) {
                $gitdir = $path . '/' . $gitdir;
            }

            $this->workDir = $path;
            $this->gitDir = realpath($gitdir) ?: $gitdir;
            $this->isLinkedWorktree = true;
            $this->isBare = false;

            // Resolve common dir from the worktree metadata
            $commonDirFile = $this->gitDir . '/commondir';

            if (is_file($commonDirFile)) {
                $rel = trim((string) file_get_contents($commonDirFile));
                $resolved = realpath($this->gitDir . '/' . $rel);
                $this->commonDir = $resolved ?: ($this->gitDir . '/' . $rel);
            } else {
                // Fall back: assume parent of worktrees/<name> is the common dir
                $this->commonDir = dirname($this->gitDir, 2);
            }
        } elseif (is_file($path . '/HEAD')) {
            // Bare repo or .git directory passed directly
            $this->isBare = basename($path) !== '.git';
            $this->workDir = $this->isBare ? $path : dirname($path);
            $this->gitDir = $path;
            $this->commonDir = $path;
            $this->isLinkedWorktree = false;
        } else {
            throw new \InvalidArgumentException("Not a git repository: {$path}");
        }

        // Objects and config come from common dir (shared)
        $this->objects = new ObjectDatabase($this->commonDir . '/objects');
        $this->config = GitConfig::fromFile($this->commonDir . '/config');

        // Refs use per-worktree gitDir for HEAD + loose refs,
        // but common dir for packed-refs
        $this->refs = new RefDatabase($this->gitDir, $this->commonDir);
    }

    public function gitDir(): string
    {
        return $this->gitDir;
    }

    /**
     * The common git directory (shared objects, config, packed-refs).
     * Same as gitDir() for regular repos. Different for linked worktrees.
     */
    public function commonGitDir(): string
    {
        return $this->commonDir;
    }

    public function workDir(): string
    {
        return $this->workDir;
    }

    public function isLinkedWorktree(): bool
    {
        return $this->isLinkedWorktree;
    }

    public function isBare(): bool
    {
        return $this->isBare;
    }

    /**
     * Resolve the repository's default/stable branch.
     *
     * Checks: remote HEAD symref -> local HEAD -> fallback to main/master.
     */
    public function defaultBranch(): string
    {
        // Check remote HEAD if we have an origin
        $remoteUrl = $this->config->get('remote.origin.url');

        if ($remoteUrl !== null) {
            try {
                $http = new SmartHttpClient();
                $discovery = $http->discoverRefs($remoteUrl);
                $symref = $discovery->headSymref();

                if ($symref !== null && str_starts_with($symref, 'refs/heads/')) {
                    return substr($symref, 11);
                }
            } catch (\Throwable) {
                // Network unavailable, fall through
            }
        }

        // Check local HEAD
        $head = $this->refs->readHead();

        if ($head !== null && str_starts_with($head->target, 'refs/heads/')) {
            return substr($head->target, 11);
        }

        // Fallback: check which of main/master exists
        if ($this->refs->resolve('refs/heads/main') !== null) {
            return 'main';
        }

        if ($this->refs->resolve('refs/heads/master') !== null) {
            return 'master';
        }

        return 'main';
    }

    /**
     * Check if a branch is fully merged into another branch.
     */
    public function isBranchMerged(string $branch, ?string $target = null): bool
    {
        $target = $target ?? $this->defaultBranch();

        $branchId = $this->refs->resolve("refs/heads/{$branch}");
        $targetId = $this->refs->resolve("refs/heads/{$target}");

        if ($branchId === null || $targetId === null) {
            return false;
        }

        // Branch is merged if it's an ancestor of target
        $mergeBase = new MergeBase($this->objects);

        return $mergeBase->isAncestor($branchId, $targetId);
    }

    /**
     * Add a linked worktree with a full checkout.
     *
     * @return \Pitmaster\Worktree\Worktree
     */
    public function addWorktree(
        string $path,
        string $branch,
        ?ObjectId $from = null,
        ?string $name = null,
    ): \Pitmaster\Worktree\Worktree {
        $manager = new \Pitmaster\Worktree\WorktreeManager($this->commonDir, $this->workDir);
        $createdBranch = false;
        $branchTarget = null;

        // Ensure the branch exists
        if ($this->refs->resolve("refs/heads/{$branch}") === null) {
            $target = $from ?? $this->refs->resolveHead();

            if ($target !== null) {
                $this->refs->update("refs/heads/{$branch}", $target);
                $createdBranch = true;
                $branchTarget = $target;
            }
        }

        $wt = $manager->add($path, $branch, $name);

        // Materialize the working tree files
        $branchId = $this->refs->resolve("refs/heads/{$branch}");

        if ($branchId !== null) {
            if ($createdBranch) {
                $this->appendReflogEntry(
                    "refs/heads/{$branch}",
                    null,
                    $branchTarget ?? $branchId,
                    'branch: Created from ' . ($from !== null ? $from->hex : 'HEAD'),
                );
            }

            $this->appendLinkedWorktreeHeadReflog($wt->gitDir, $branchId);
            $commit = $this->objects->read($branchId);

            if ($commit instanceof Commit) {
                $this->checkoutTree($commit->tree, $path);

                // Write index for the worktree
                $treeMap = $this->flattenTree($commit->tree);
                $index = new Index();

                foreach ($treeMap as $filePath => $hash) {
                    $fullPath = $path . '/' . $filePath;

                    if (is_file($fullPath)) {
                        $entry = IndexEntry::fromStat($filePath, ObjectId::fromHex($hash), $fullPath);
                        $index->addEntry($entry);
                    }
                }

                IndexWriter::write($index, $wt->gitDir . '/index');
            }
        }

        return $wt;
    }

    /**
     * Remove a linked worktree.
     */
    public function removeWorktree(string $pathOrName, bool $force = false): void
    {
        $manager = new \Pitmaster\Worktree\WorktreeManager($this->commonDir, $this->workDir);
        $manager->remove($pathOrName, $force);
    }

    /**
     * List all worktrees.
     *
     * @return array<int, \Pitmaster\Worktree\Worktree>
     */
    public function worktrees(): array
    {
        $manager = new \Pitmaster\Worktree\WorktreeManager($this->commonDir, $this->workDir);

        return $manager->list();
    }

    /**
     * Read any object by hash.
     */
    public function readObject(string $hash): GitObject
    {
        $id = ObjectId::fromHex($hash);
        $object = $this->objects->read($id);

        if ($object === null) {
            throw ObjectNotFoundException::forHash($hash);
        }

        return $object;
    }

    /**
     * Write an object, returns its hash.
     */
    public function writeObject(GitObject $object): ObjectId
    {
        return $this->objects->write($object);
    }

    /**
     * Raw content of an object (like git cat-file -p).
     */
    public function catFile(string $hash): string
    {
        return $this->readObject($hash)->content;
    }

    /**
     * Check if an object exists.
     */
    public function objectExists(string $hash): bool
    {
        return $this->objects->exists(ObjectId::fromHex($hash));
    }

    /**
     * List all object hashes in the repository.
     *
     * @return array<int, string>
     */
    public function listObjects(): array
    {
        return $this->objects->listAll();
    }

    /**
     * Current HEAD commit.
     */
    public function head(): Commit
    {
        $id = $this->refs->resolveHead();

        if ($id === null) {
            throw new \RuntimeException('HEAD does not point to a valid commit');
        }

        $object = $this->objects->read($id);

        if (!$object instanceof Commit) {
            throw new \RuntimeException("HEAD points to a non-commit object: {$id->hex}");
        }

        return $object;
    }

    /**
     * Current branch name (from HEAD symbolic ref).
     * Returns null if HEAD is detached.
     */
    public function branch(?string $name = null): ?string
    {
        if ($name !== null) {
            // Resolve a branch name to its hash
            $id = $this->refs->resolve("refs/heads/{$name}");

            return $id?->hex;
        }

        $head = $this->refs->readHead();

        if ($head === null) {
            return null;
        }

        if (str_starts_with($head->target, 'refs/heads/')) {
            return substr($head->target, 11);
        }

        return null;
    }

    /**
     * List all branch names.
     *
     * @return array<int, string>
     */
    public function branches(): array
    {
        $branches = [];

        foreach ($this->refs->list() as $name => $id) {
            if (str_starts_with($name, 'refs/heads/')) {
                $branches[] = substr($name, 11);
            }
        }

        sort($branches);

        return $branches;
    }

    /**
     * List all tag names.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        $tags = [];

        foreach ($this->refs->list() as $name => $id) {
            if (str_starts_with($name, 'refs/tags/') && !str_ends_with($name, '^{}')) {
                $tags[] = substr($name, 10);
            }
        }

        sort($tags);

        return $tags;
    }

    /**
     * Resolve a revision expression to an ObjectId.
     */
    public function resolve(string $revision): ObjectId
    {
        // Direct hash
        if (strlen($revision) === 40 && ctype_xdigit($revision)) {
            return ObjectId::fromHex($revision);
        }

        // HEAD
        if ($revision === 'HEAD') {
            $id = $this->refs->resolveHead();

            if ($id === null) {
                throw new \RuntimeException('Cannot resolve HEAD');
            }

            return $id;
        }

        // Try as a ref name (branch or tag)
        $id = $this->refs->resolve("refs/heads/{$revision}")
            ?? $this->refs->resolve("refs/tags/{$revision}")
            ?? $this->refs->resolve($revision);

        if ($id !== null) {
            return $id;
        }

        // Try as a revision expression (HEAD~3, main^2, etc.)
        if (str_contains($revision, '~') || str_contains($revision, '^')) {
            $parser = new RevisionParser($this->objects, $this->refs);
            $id = $parser->resolve($revision);

            if ($id !== null) {
                return $id;
            }
        }

        throw new \RuntimeException("Cannot resolve revision: {$revision}");
    }

    /**
     * Update a ref to point to a new target.
     */
    public function updateRef(string $name, ObjectId $target): void
    {
        $this->refs->update($name, $target);
    }

    /**
     * Create a new branch.
     */
    public function createBranch(string $name, ?ObjectId $from = null): void
    {
        $target = $from ?? $this->refs->resolveHead();

        if ($target === null) {
            throw new \RuntimeException('Cannot create branch: no HEAD to derive from');
        }

        $refName = "refs/heads/{$name}";
        $this->refs->update($refName, $target);
        $this->appendReflogEntry($refName, null, $target, 'branch: Created from ' . ($from !== null ? $from->hex : 'HEAD'));
    }

    /**
     * Delete a branch.
     */
    public function deleteBranch(string $name): void
    {
        if ($this->branch() === $name) {
            throw new \RuntimeException("Cannot delete checked out branch: {$name}");
        }

        $refName = "refs/heads/{$name}";

        if (!$this->refs->exists($refName)) {
            throw new \RuntimeException("Branch not found: {$name}");
        }

        $this->refs->delete($refName);
        $this->deleteReflog($refName);
    }

    /**
     * Create a lightweight tag.
     */
    public function createLightweightTag(string $name, ?ObjectId $target = null): void
    {
        $target = $target ?? $this->refs->resolveHead();

        if ($target === null) {
            throw new \RuntimeException('Cannot create tag: no HEAD');
        }

        $this->refs->update("refs/tags/{$name}", $target);
    }

    /**
     * Create an annotated tag.
     */
    public function createTag(string $name, string $message, ?ObjectId $target = null, ?string $tagger = null): ObjectId
    {
        $target = $target ?? $this->refs->resolveHead();

        if ($target === null) {
            throw new \RuntimeException('Cannot create tag: no HEAD');
        }

        $targetObject = $this->objects->read($target);

        if ($targetObject === null) {
            throw new \RuntimeException("Cannot create tag: target not found {$target->hex}");
        }

        if ($tagger === null) {
            $tagger = $this->currentCommitterIdentity();
        }

        $content = "object {$target->hex}\n"
            . "type {$targetObject->type->value}\n"
            . "tag {$name}\n"
            . "tagger {$tagger}\n"
            . "\n"
            . $message;

        $id = ObjectId::compute(ObjectType::Tag, $content);
        $tag = Tag::parse($content, $id);
        $this->objects->write($tag);
        $this->refs->update("refs/tags/{$name}", $id);

        return $id;
    }

    /**
     * Delete a tag.
     */
    public function deleteTag(string $name): void
    {
        $refName = "refs/tags/{$name}";

        if (!$this->refs->exists($refName)) {
            throw new \RuntimeException("Tag not found: {$name}");
        }

        $this->refs->delete($refName);
    }

    /**
     * Pack shared refs into packed-refs and prune their loose files.
     */
    public function packRefs(): void
    {
        $refs = [];
        $peeled = [];

        foreach ($this->refs->list() as $name => $id) {
            if (!$this->isPackableRef($name) || $this->isCommonSymbolicRef($name) || $this->isPerWorktreeRef($name)) {
                continue;
            }

            $refs[$name] = $id;
            $peeledId = $this->peelRefTarget($id);

            if ($peeledId !== null) {
                $peeled[$name] = $peeledId;
            }
        }

        $packed = $this->refs->packedStore();
        $packed->replace($refs, $peeled);
        $packed->write();

        foreach (array_keys($refs) as $name) {
            $this->deleteLooseCommonRef($name);
        }
    }

    /**
     * Checkout a branch or commit (update HEAD + worktree + index).
     */
    public function checkout(string $target): void
    {
        $trackedPaths = $this->index()->paths();
        $oldHeadId = $this->refs->resolveHead();
        $fromLabel = $this->currentLocationLabel($oldHeadId);

        // Try as branch first
        $branchId = $this->refs->resolve("refs/heads/{$target}");

        if ($branchId !== null) {
            $this->assertSafeCheckout($branchId);

            // Switch to branch: update HEAD symbolic ref
            $this->refs->updateSymbolic('HEAD', "refs/heads/{$target}");
            $this->appendReflogEntry(
                'HEAD',
                $oldHeadId,
                $branchId,
                "checkout: moving from {$fromLabel} to {$target}",
            );
            $this->resetWorktree($branchId, $trackedPaths);
            $this->runPostCheckoutHook($oldHeadId, $branchId);

            return;
        }

        // Try as tag or direct hash (detached HEAD)
        $id = $this->resolve($target);
        $this->assertSafeCheckout($id);
        $this->refs->looseStore()->update('HEAD', $id);
        $this->appendReflogEntry(
            'HEAD',
            $oldHeadId,
            $id,
            "checkout: moving from {$fromLabel} to " . $this->targetLabel($target, $id),
        );
        $this->resetWorktree($id, $trackedPaths);
        $this->runPostCheckoutHook($oldHeadId, $id);
    }

    /**
     * Get all refs as name => hex hash.
     *
     * @return array<string, string>
     */
    public function allRefs(): array
    {
        $result = [];

        foreach ($this->refs->list() as $name => $id) {
            $result[$name] = $id->hex;
        }

        return $result;
    }

    /**
     * Walk commit history.
     *
     * @return array<int, Commit>
     */
    public function log(int $limit = 50, ?ObjectId $from = null): array
    {
        if ($from === null) {
            $from = $this->refs->resolveHead();

            if ($from === null) {
                return [];
            }
        }

        $walker = new CommitWalker($this->objects);

        return $walker->walk($from, $limit);
    }

    /**
     * Walk commits reachable from every ref tip.
     *
     * @return array<int, Commit>
     */
    public function logAll(int $limit = 50): array
    {
        $tips = [];

        foreach ($this->refs->list() as $id) {
            $tips[$id->hex] = $id;
        }

        $headId = $this->refs->resolveHead();

        if ($headId !== null) {
            $tips[$headId->hex] = $headId;
        }

        if ($tips === []) {
            return [];
        }

        $walker = new CommitWalker($this->objects);

        return $walker->walkAll(array_values($tips), $limit);
    }

    /**
     * Render log output in oneline format.
     *
     * @return list<string>
     */
    public function logOneline(int $limit = 50, bool $all = false, ?string $path = null): array
    {
        $commits = $path !== null
            ? $this->logPath($path, $limit)
            : ($all ? $this->logAll($limit) : $this->log($limit));

        return array_map(
            fn (Commit $commit): string => substr($commit->id->hex, 0, 7) . ' ' . $this->subjectLine($commit->message),
            $commits,
        );
    }

    /**
     * Log filtered by path (only commits that touch the given file).
     *
     * @return array<int, Commit>
     */
    public function logPath(string $path, int $limit = 50): array
    {
        $allCommits = $this->log($limit * 5); // over-fetch to filter
        $treeDiff = new TreeDiff($this->objects);
        $result = [];

        foreach ($allCommits as $commit) {
            $parentTree = $commit->parents !== []
                ? $this->getCommitTree($commit->parents[0])
                : null;

            $diffs = $treeDiff->diff($parentTree, $commit->tree);

            foreach ($diffs as $diff) {
                if ($diff->oldPath === $path || $diff->newPath === $path) {
                    $result[] = $commit;
                    break;
                }
            }

            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    /**
     * Show a commit-ish: metadata + diff against the first parent.
     *
     * @return array{commit: Commit, diff: array<int, DiffResult>, tag?: Tag}
     */
    public function show(string $revision): array
    {
        $id = $this->resolve($revision);
        $object = $this->objects->read($id);
        $tag = null;

        if ($object instanceof Tag) {
            $tag = $object;
            $object = $this->objects->read($object->object);
        }

        if (!$object instanceof Commit) {
            throw new \RuntimeException("Not a commit-ish: {$revision}");
        }

        $treeDiff = new TreeDiff($this->objects);
        $parentTree = $object->parents !== []
            ? $this->getCommitTree($object->parents[0])
            : null;

        $result = [
            'commit' => $object,
            'diff' => $treeDiff->diff($parentTree, $object->tree),
        ];

        if ($tag instanceof Tag) {
            $result['tag'] = $tag;
        }

        return $result;
    }

    /**
     * Read the current index.
     */
    public function index(): Index
    {
        return Index::open($this->gitDir . '/index');
    }

    /**
     * Stage files (git add).
     */
    public function add(string ...$paths): void
    {
        $index = $this->index();

        foreach ($paths as $path) {
            $fullPath = $this->workDir . '/' . $path;

            if (!is_file($fullPath)) {
                throw new \RuntimeException("File not found: {$path}");
            }

            $content = file_get_contents($fullPath);
            $blob = Blob::fromContent($content);
            $this->objects->write($blob);

            $entry = IndexEntry::fromStat($path, $blob->id, $fullPath);
            $index->resolveConflict($path, $entry);
        }

        IndexWriter::write($index, $this->gitDir . '/index');
    }

    /**
     * Move/rename a file in the index (git mv).
     */
    public function mv(string $source, string $destination): void
    {
        $source = trim($source, '/');
        $destination = $this->resolvedMoveDestination($source, trim($destination, '/'));
        $srcFull = $this->workDir . '/' . $source;
        $dstFull = $this->workDir . '/' . $destination;
        $index = $this->index();
        $entries = $this->trackedEntriesForPath($index, $source);

        if ($entries === []) {
            throw new \RuntimeException("Source path not tracked: {$source}");
        }

        if (!file_exists($srcFull) && !is_link($srcFull)) {
            throw new \RuntimeException("Source path not found: {$source}");
        }

        if ($source === $destination) {
            throw new \RuntimeException("Source and destination are the same: {$source}");
        }

        $dstDir = dirname($dstFull);

        if (!is_dir($dstDir)) {
            mkdir($dstDir, 0777, true);
        }

        if (!rename($srcFull, $dstFull)) {
            throw new \RuntimeException("Failed to move {$source} to {$destination}");
        }

        foreach ($entries as $entry) {
            $index->removeEntry($entry->path, $entry->stage());
        }

        foreach ($entries as $entry) {
            $newPath = $this->movedPath($source, $destination, $entry->path);
            $index->addEntry($this->relocateIndexEntry($entry, $newPath));
        }

        IndexWriter::write($index, $this->gitDir . '/index');
    }

    /**
     * Remove tracked paths from the index and worktree (git rm).
     *
     * Supports `--cached` for index-only removal and `-r` / `--recursive`
     * when removing tracked directories.
     */
    public function remove(string ...$paths): void
    {
        ['cached' => $cached, 'recursive' => $recursive, 'paths' => $paths] = $this->parseRemoveArguments($paths);

        if ($paths === []) {
            throw new \RuntimeException('No pathspec given for remove');
        }

        if ($cached) {
            $this->removeCached(...$paths);
            return;
        }

        $index = $this->index();
        $headId = $this->refs->resolveHead();
        $headEntries = $this->flattenTreeEntries($headId !== null ? $this->getCommitTree($headId) : null);

        foreach ($paths as $path) {
            $entries = $this->trackedEntriesForPath($index, $path);

            if ($entries === []) {
                throw new \RuntimeException("pathspec '{$path}' did not match any tracked files");
            }

            $isDirectory = count($entries) > 1 || ($entries[0]->path !== $path && str_starts_with($entries[0]->path, $path . '/'));

            if ($isDirectory && !$recursive) {
                throw new \RuntimeException("not removing '{$path}' recursively without -r");
            }

            foreach ($entries as $entry) {
                $this->assertRemoveEntryIsSafe($entry, $headEntries[$entry->path] ?? null, false);
            }

            foreach ($entries as $entry) {
                $index->removeEntry($entry->path);
                $this->removeWorktreePath($entry->path);
            }
        }

        IndexWriter::write($index, $this->gitDir . '/index');
    }

    /**
     * Remove tracked paths from the index only (git rm --cached).
     */
    public function removeCached(string ...$paths): void
    {
        if ($paths === []) {
            throw new \RuntimeException('No pathspec given for remove');
        }

        $index = $this->index();
        $headId = $this->refs->resolveHead();
        $headEntries = $this->flattenTreeEntries($headId !== null ? $this->getCommitTree($headId) : null);

        foreach ($paths as $path) {
            $entries = $this->trackedEntriesForPath($index, $path);

            if ($entries === []) {
                throw new \RuntimeException("pathspec '{$path}' did not match any tracked files");
            }

            foreach ($entries as $entry) {
                $this->assertRemoveEntryIsSafe($entry, $headEntries[$entry->path] ?? null, true);
                $index->removeEntry($entry->path);
            }
        }

        IndexWriter::write($index, $this->gitDir . '/index');
    }

    /**
     * Create a commit from the current index.
     */
    public function commit(?string $message = null, ?string $author = null): ObjectId
    {
        $index = $this->index();
        $headId = $this->refs->resolveHead();
        $state = $this->pendingOperationState($headId);

        if ($index->hasUnmerged()) {
            throw new \RuntimeException('Cannot commit with unmerged paths');
        }

        $message = $this->resolveCommitMessage($message, $state);
        $message = $this->prepareCommitMessage($message, $state);
        $treeId = $this->buildTreeFromIndex($index);

        if ($headId === null && $index->count() === 0) {
            throw new \RuntimeException('Nothing to commit: index is empty');
        }

        $headCommit = $headId !== null ? $this->objects->read($headId) : null;

        if ($headCommit instanceof Commit && $headCommit->tree->equals($treeId)) {
            throw new \RuntimeException('Nothing to commit: tree unchanged');
        }

        $parents = $state['parents'] ?? ($headId !== null ? [$headId] : []);
        $author = $author ?? ($state['author'] ?? null);
        $commitId = $this->createCommitFromTree($treeId, $message, $parents, $author);

        if (($state['type'] ?? null) === 'rebase') {
            $this->moveDetachedHeadTo($commitId, 'rebase (continue): ' . $this->subjectLine($message));
            $this->clearOperationState(['REBASE_HEAD']);
            $this->advanceRebaseState();
            $this->runPostCommitHook();

            return $commitId;
        }

        $this->moveHeadTo($commitId, $this->commitReflogMessage($state, $message));
        $this->clearOperationState();
        $this->runPostCommitHook();

        return $commitId;
    }

    /**
     * Reset HEAD to a commit.
     *
     * @param string $mode 'soft' (HEAD only), 'mixed' (HEAD + index), 'hard' (HEAD + index + worktree)
     */
    public function reset(string $revision, string $mode = 'mixed'): void
    {
        if ($mode === 'soft' && $this->index()->hasUnmerged()) {
            throw new \RuntimeException('Cannot do a soft reset in the middle of a merge.');
        }

        $targetId = $this->resolve($revision);
        $oldHeadId = $this->refs->resolveHead();
        $trackedPaths = $this->index()->paths();

        if ($oldHeadId !== null) {
            $this->refs->looseStore()->update('ORIG_HEAD', $oldHeadId);
        }

        $this->moveHeadTo($targetId, "reset: moving to {$revision}");

        if ($mode === 'soft') {
            return;
        }

        if ($mode === 'hard') {
            $this->resetWorktree($targetId, $trackedPaths);
            $this->clearOperationState();
            return;
        }

        // Reset index to match target tree
        $commit = $this->objects->read($targetId);

        if (!$commit instanceof Commit) {
            return;
        }

        $treeMap = $this->flattenTree($commit->tree);
        $index = new Index();

        foreach ($treeMap as $path => $hash) {
            $entry = IndexEntry::create($path, ObjectId::fromHex($hash));
            $index->addEntry($entry);
        }

        IndexWriter::write($index, $this->gitDir . '/index');
        $this->clearOperationState();
    }

    /**
     * Restore a path from the index or a source tree (git restore).
     */
    public function restore(string $path, ?string $source = null, bool $staged = false, bool $worktree = false): void
    {
        if (!$staged && !$worktree) {
            $worktree = true;
        }

        if ($staged) {
            $this->restoreIndexPath($path, $source);
        }

        if (!$worktree) {
            return;
        }

        if ($source !== null) {
            $this->restoreWorktreePathFromTree($path, $source);
            return;
        }

        $this->restoreWorktreePathFromIndex($path);
    }

    /**
     * Cherry-pick: apply a commit as a new commit on the current branch.
     */
    public function cherryPick(string $revision): ObjectId
    {
        $id = $this->resolve($revision);
        $commit = $this->objects->read($id);
        $headId = $this->refs->resolveHead();
        $headCommit = $headId !== null ? $this->objects->read($headId) : null;

        if (!$commit instanceof Commit || !$headCommit instanceof Commit) {
            throw new \RuntimeException("Not a commit: {$revision}");
        }

        $parentTree = $commit->parents !== []
            ? $this->getCommitTree($commit->parents[0])
            : null;
        $trackedPaths = $this->index()->paths();
        $merge = $this->mergeTreeEntries(
            $parentTree,
            $headCommit->tree,
            $commit->tree,
            'HEAD',
            $this->cherryPickConflictLabel($commit),
            $commit->parents !== [] ? substr($commit->parents[0]->hex, 0, 7) : 'base',
        );

        if ($merge['conflictPaths'] !== []) {
            $this->writeOperationConflictState(
                $merge['mergedEntries'],
                $merge['conflictEntries'],
                $merge['conflictContents'],
                $trackedPaths,
                'CHERRY_PICK_HEAD',
                $commit->id,
                $this->buildCherryPickMessage($commit, $merge['conflictPaths']),
            );

            throw new MergeConflictException($merge['conflictPaths'], 'Cherry-pick stopped due to conflicts');
        }

        $treeId = $this->buildTreeFromEntries($merge['mergedEntries']);
        $commitId = $this->createCommitFromTree($treeId, $commit->message, [$headId], $commit->author);
        $this->moveHeadTo($commitId, 'cherry-pick: ' . $this->subjectLine($commit->message));
        $this->resetWorktree($commitId, $trackedPaths);

        return $commitId;
    }

    /**
     * Continue an in-progress cherry-pick after resolving conflicts.
     */
    public function cherryPickContinue(): ObjectId
    {
        if ($this->refs->resolve('CHERRY_PICK_HEAD') === null) {
            throw new \RuntimeException('Cannot continue: no cherry-pick in progress');
        }

        if ($this->index()->hasUnmerged()) {
            throw new \RuntimeException('Cannot continue cherry-pick with unmerged paths');
        }

        return $this->commit();
    }

    /**
     * Abort an in-progress cherry-pick and restore the worktree to HEAD.
     */
    public function cherryPickAbort(): void
    {
        $headId = $this->refs->resolveHead();
        $this->abortHeadBasedOperation(
            'CHERRY_PICK_HEAD',
            true,
            $headId !== null ? "reset: moving to {$headId->hex}" : null,
        );
    }

    /**
     * Revert: create a commit that undoes another commit.
     */
    public function revert(string $revision): ObjectId
    {
        $id = $this->resolve($revision);
        $commit = $this->objects->read($id);
        $headId = $this->refs->resolveHead();
        $headCommit = $headId !== null ? $this->objects->read($headId) : null;

        if (!$commit instanceof Commit || !$headCommit instanceof Commit) {
            throw new \RuntimeException("Not a commit: {$revision}");
        }

        if ($commit->parents === []) {
            throw new \RuntimeException('Cannot revert a root commit');
        }

        $parentTree = $this->getCommitTree($commit->parents[0]);
        $message = "Revert \"{$this->subjectLine($commit->message)}\"\n\nThis reverts commit {$commit->id->hex}.\n";
        $trackedPaths = $this->index()->paths();
        $merge = $this->mergeTreeEntries(
            $commit->tree,
            $headCommit->tree,
            $parentTree,
            'HEAD',
            $this->revertConflictLabel($commit),
            substr($commit->id->hex, 0, 7),
        );

        if ($merge['conflictPaths'] !== []) {
            $this->writeOperationConflictState(
                $merge['mergedEntries'],
                $merge['conflictEntries'],
                $merge['conflictContents'],
                $trackedPaths,
                'REVERT_HEAD',
                $commit->id,
                $this->buildRevertMessage($commit, $merge['conflictPaths']),
            );

            throw new MergeConflictException($merge['conflictPaths'], 'Revert stopped due to conflicts');
        }

        $treeId = $this->buildTreeFromEntries($merge['mergedEntries']);
        $commitId = $this->createCommitFromTree($treeId, $message, [$headId]);
        $this->moveHeadTo($commitId, 'revert: ' . $this->subjectLine($message));
        $this->resetWorktree($commitId, $trackedPaths);

        return $commitId;
    }

    /**
     * Continue an in-progress revert after resolving conflicts.
     */
    public function revertContinue(): ObjectId
    {
        if ($this->refs->resolve('REVERT_HEAD') === null) {
            throw new \RuntimeException('Cannot continue: no revert in progress');
        }

        if ($this->index()->hasUnmerged()) {
            throw new \RuntimeException('Cannot continue revert with unmerged paths');
        }

        return $this->commit();
    }

    /**
     * Abort an in-progress revert and restore the worktree to HEAD.
     */
    public function revertAbort(): void
    {
        $headId = $this->refs->resolveHead();
        $this->abortHeadBasedOperation(
            'REVERT_HEAD',
            true,
            $headId !== null ? "reset: moving to {$headId->hex}" : null,
        );
    }

    /**
     * Rebase the current branch onto another revision.
     *
     * @return array{success: bool, commits: int, conflicts: array<int, string>}
     */
    public function rebase(string $onto): array
    {
        $head = $this->refs->readHead();

        if ($head === null || !str_starts_with($head->target, 'refs/heads/')) {
            throw new \RuntimeException('Cannot rebase: HEAD must point to a branch');
        }

        if ($this->readRebaseState() !== null) {
            throw new \RuntimeException('Cannot rebase: a rebase is already in progress');
        }

        $headId = $this->refs->resolveHead();

        if ($headId === null) {
            throw new \RuntimeException('Cannot rebase: HEAD is not set');
        }

        $this->runPreRebaseHook($onto, $head);

        $ontoId = $this->resolve($onto);
        $mergeBase = new MergeBase($this->objects);
        $baseId = $mergeBase->find($headId, $ontoId);

        if ($baseId === null) {
            throw new \RuntimeException('Cannot rebase: no common ancestor');
        }

        if ($baseId->equals($ontoId)) {
            return ['success' => true, 'commits' => 0, 'conflicts' => []];
        }

        $trackedPaths = $this->index()->paths();
        $this->refs->looseStore()->update('ORIG_HEAD', $headId);

        if ($baseId->equals($headId)) {
            $this->refs->update($head->target, $ontoId);
            $this->appendReflogEntry(
                $head->target,
                $headId,
                $ontoId,
                'rebase (finish): ' . $head->target . ' onto ' . $ontoId->hex,
            );
            $this->appendReflogEntry(
                'HEAD',
                $headId,
                $ontoId,
                'rebase (finish): returning to ' . $head->target,
            );
            $this->resetWorktree($ontoId, $trackedPaths);

            return ['success' => true, 'commits' => 0, 'conflicts' => []];
        }

        $commits = $this->rebaseCommitsToReplay($headId, $baseId);
        $state = [
            'headName' => $head->target,
            'origHead' => $headId,
            'onto' => $ontoId,
            'current' => 0,
            'commits' => array_map(static fn (Commit $commit): ObjectId => $commit->id, $commits),
        ];

        $this->detachHeadTo(
            $ontoId,
            'rebase (start): checkout ' . $this->targetLabel($onto, $ontoId),
        );
        $this->resetWorktree($ontoId, $trackedPaths);
        $this->writeRebaseState($state);

        return $this->continueRebaseSequence();
    }

    /**
     * Continue an in-progress rebase after resolving conflicts.
     *
     * @return array{success: bool, commits: int, conflicts: array<int, string>}
     */
    public function rebaseContinue(): array
    {
        if ($this->readRebaseState() === null) {
            throw new \RuntimeException('Cannot continue: no rebase in progress');
        }

        if ($this->index()->hasUnmerged()) {
            throw new \RuntimeException('Cannot continue rebase with unmerged paths');
        }

        if ($this->refs->resolve('REBASE_HEAD') !== null) {
            $this->commit();
        }

        return $this->continueRebaseSequence();
    }

    /**
     * Skip the current commit in an in-progress rebase.
     *
     * @return array{success: bool, commits: int, conflicts: array<int, string>}
     */
    public function rebaseSkip(): array
    {
        $state = $this->readRebaseState();

        if ($state === null) {
            throw new \RuntimeException('Cannot skip: no rebase in progress');
        }

        $headId = $this->refs->resolveHead();

        if ($headId === null) {
            throw new \RuntimeException('Cannot skip: HEAD is not set');
        }

        if ($this->refs->resolve('REBASE_HEAD') !== null) {
            $trackedPaths = array_values(array_unique(array_merge(
                $this->index()->paths(),
                array_keys($this->flattenTree($this->getCommitTree($headId))),
            )));
            $this->resetWorktree($headId, $trackedPaths, ['REBASE_HEAD']);
            $this->advanceRebaseState();
        }

        return $this->continueRebaseSequence();
    }

    /**
     * Abort an in-progress rebase and restore the original branch tip.
     */
    public function rebaseAbort(): void
    {
        $state = $this->readRebaseState();

        if ($state === null) {
            throw new \RuntimeException('Cannot abort: no rebase in progress');
        }

        $currentHeadId = $this->refs->resolveHead();
        $trackedPaths = array_values(array_unique(array_merge(
            $this->index()->paths(),
            array_keys($this->flattenTree($this->getCommitTree($state['origHead']))),
        )));

        $this->refs->update($state['headName'], $state['origHead']);
        $this->refs->updateSymbolic('HEAD', $state['headName']);
        $this->appendReflogEntry(
            'HEAD',
            $currentHeadId,
            $state['origHead'],
            'rebase (abort): returning to ' . $state['headName'],
        );
        $this->resetWorktree($state['origHead'], $trackedPaths);
        $this->clearRebaseState();
    }

    /**
     * Porcelain v2 status output.
     *
     * @return string Machine-readable status output
     */
    public function statusPorcelainV2(): string
    {
        $entries = $this->status();
        $trackedLines = [];
        $untrackedLines = [];
        $ignoredLines = [];
        $headId = $this->refs->resolveHead();
        $headEntries = $this->flattenTreeEntries($headId !== null ? $this->getCommitTree($headId) : null);
        $index = $this->index();
        $indexEntries = $index->entries();
        $zero = str_repeat('0', 40);

        foreach ($entries as $entry) {
            if ($entry->index === FileStatus::Untracked) {
                $untrackedLines[] = "? {$entry->path}";
                continue;
            }

            if ($entry->index === FileStatus::Ignored) {
                $ignoredLines[] = "! {$entry->path}";
                continue;
            }

            if ($entry->index === FileStatus::Unmerged || $entry->worktree === FileStatus::Unmerged) {
                $stages = $index->stageEntries($entry->path);
                $stage1 = $stages[1] ?? null;
                $stage2 = $stages[2] ?? null;
                $stage3 = $stages[3] ?? null;
                $workMode = $this->worktreeMode($entry->path);

                $trackedLines[] = sprintf(
                    'u %s N... %s %s %s %s %s %s %s %s',
                    $this->unmergedPorcelainCode($stages),
                    $stage1 !== null ? sprintf('%06o', $stage1->mode) : '000000',
                    $stage2 !== null ? sprintf('%06o', $stage2->mode) : '000000',
                    $stage3 !== null ? sprintf('%06o', $stage3->mode) : '000000',
                    $workMode !== null ? sprintf('%06o', $workMode) : '000000',
                    $stage1?->hash->hex ?? $zero,
                    $stage2?->hash->hex ?? $zero,
                    $stage3?->hash->hex ?? $zero,
                    $entry->path,
                );
                continue;
            }

            $x = $entry->index === FileStatus::Unmodified ? '.' : $entry->index->value;
            $y = $entry->worktree === FileStatus::Unmodified ? '.' : $entry->worktree->value;
            $headEntry = $headEntries[$entry->path] ?? null;
            $indexEntry = $indexEntries[$entry->path] ?? null;
            $worktreeMode = $this->worktreeMode($entry->path);
            $headMode = $headEntry !== null ? sprintf('%06o', $headEntry['mode']) : '000000';
            $indexMode = $indexEntry !== null ? sprintf('%06o', $indexEntry->mode) : '000000';
            $workMode = $worktreeMode !== null ? sprintf('%06o', $worktreeMode) : '000000';
            $headHash = $headEntry['hash'] ?? $zero;
            $indexHash = $indexEntry?->hash->hex ?? $zero;

            if ($entry->index === FileStatus::Renamed && $entry->origPath !== null) {
                $oldHeadEntry = $headEntries[$entry->origPath] ?? null;
                $headMode = $oldHeadEntry !== null ? sprintf('%06o', $oldHeadEntry['mode']) : '000000';
                $headHash = $oldHeadEntry['hash'] ?? $zero;
                $renameScore = sprintf('R%03d', $entry->renameScore ?? 100);
                $trackedLines[] = "2 {$x}{$y} N... {$headMode} {$indexMode} {$workMode} {$headHash} {$indexHash} {$renameScore} {$entry->path}\t{$entry->origPath}";
                continue;
            }

            $trackedLines[] = "1 {$x}{$y} N... {$headMode} {$indexMode} {$workMode} {$headHash} {$indexHash} {$entry->path}";
        }

        $lines = array_merge($trackedLines, $untrackedLines, $ignoredLines);

        return implode("\n", $lines) . ($lines !== [] ? "\n" : '');
    }

    /**
     * Fetch from a remote.
     */
    public function fetch(string $remote = 'origin'): void
    {
        $url = $this->config->get("remote.{$remote}.url");

        if ($url === null) {
            throw new \RuntimeException("Remote not found: {$remote}");
        }

        try {
            $http = new SmartHttpClient();
            $discovery = $http->discoverRefs($url);
            $uploadPack = new UploadPackClient($http);
            $trackedRefs = $this->plannedFetchRefs($remote, $discovery->refs());

            $wants = [];
            $haves = [];

            foreach ($trackedRefs as $trackedRef) {
                if (!$this->objects->exists($trackedRef['id'])) {
                    $wants[] = $trackedRef['id'];
                }
            }

            foreach ($this->refs->list() as $refId) {
                $haves[] = $refId;
            }

            if ($wants === []) {
                return;
            }

            $packData = $uploadPack->fetch($url, $wants, $haves);

            if ($packData !== '' && str_starts_with($packData, 'PACK')) {
                $packDir = $this->commonDir . '/objects/pack';

                if (!is_dir($packDir)) {
                    mkdir($packDir, 0777, true);
                }

                $hash = sha1($packData);
                $packPath = $packDir . "/pack-{$hash}.pack";
                file_put_contents($packPath, $packData);
                PackIndexer::writeIndex($packPath);
                $this->objects->packStore()->refresh();
            }

            $this->updateFetchedRefs($trackedRefs, $discovery->refs());
        } catch (ProtocolException $smartError) {
            $this->fetchViaDumbHttp($remote, $url, $smartError);
        }
    }

    /**
     * Push to a remote.
     */
    public function push(string $remote = 'origin', ?string $branch = null): void
    {
        $branch = $branch ?? $this->currentPushBranch();
        $url = $this->remoteUrl($remote);
        $localRef = "refs/heads/{$branch}";
        $localId = $this->requireLocalRef($localRef, "Branch not found: {$branch}");
        $http = new SmartHttpClient();
        $discovery = $http->discoverReceivePackRefs($url);
        $remoteId = $discovery->ref($localRef);

        $this->assertFastForwardPush($branch, $remoteId, $localId);
        $this->pushUpdates($remote, $http, $url, $discovery, [[
            'old' => $remoteId ?? $this->zeroObjectId(),
            'new' => $localId,
            'ref' => $localRef,
        ]]);
    }

    /**
     * Push with force-with-lease semantics.
     *
     * Uses the remote-tracking branch as the default lease when available.
     */
    public function pushForceWithLease(string $remote = 'origin', ?string $branch = null, ?ObjectId $expected = null): void
    {
        $branch = $branch ?? $this->currentPushBranch();
        $url = $this->remoteUrl($remote);
        $localRef = "refs/heads/{$branch}";
        $localId = $this->requireLocalRef($localRef, "Branch not found: {$branch}");
        $http = new SmartHttpClient();
        $discovery = $http->discoverReceivePackRefs($url);
        $remoteId = $discovery->ref($localRef);
        $leaseId = $expected ?? $this->refs->resolve("refs/remotes/{$remote}/{$branch}") ?? $remoteId ?? $this->zeroObjectId();

        if (($remoteId ?? $this->zeroObjectId())->hex !== $leaseId->hex) {
            throw new \RuntimeException("force-with-lease rejected for {$branch}: remote changed");
        }

        $this->pushUpdates($remote, $http, $url, $discovery, [[
            'old' => $leaseId,
            'new' => $localId,
            'ref' => $localRef,
        ]]);
    }

    /**
     * Atomically push multiple local branches.
     *
     * @param array<int, string> $branches
     */
    public function pushAtomic(string $remote = 'origin', array $branches = []): void
    {
        if ($branches === []) {
            $branches = [$this->currentPushBranch()];
        }

        $url = $this->remoteUrl($remote);
        $http = new SmartHttpClient();
        $discovery = $http->discoverReceivePackRefs($url);
        $capabilities = $discovery->capabilities();

        if ($capabilities !== null && !$capabilities->has('atomic')) {
            throw new \RuntimeException('Remote does not support atomic push');
        }

        $updates = [];

        foreach ($branches as $branch) {
            $localRef = "refs/heads/{$branch}";
            $localId = $this->requireLocalRef($localRef, "Branch not found: {$branch}");
            $remoteId = $discovery->ref($localRef);
            $this->assertFastForwardPush($branch, $remoteId, $localId);
            $updates[] = [
                'old' => $remoteId ?? $this->zeroObjectId(),
                'new' => $localId,
                'ref' => $localRef,
            ];
        }

        $this->pushUpdates($remote, $http, $url, $discovery, $updates, ['atomic']);
    }

    /**
     * Mirror local branch and tag refs to the remote.
     */
    public function pushMirror(string $remote = 'origin'): void
    {
        $url = $this->config->get("remote.{$remote}.url");

        if ($url === null) {
            throw new \RuntimeException("Remote not found: {$remote}");
        }
        $http = new SmartHttpClient();
        $discovery = $http->discoverReceivePackRefs($url);
        $capabilities = $discovery->capabilities();
        $zero = $this->zeroObjectId();
        $localRefs = [];
        $updates = [];

        foreach ($this->refs->list() as $refName => $refId) {
            if (!$this->isMirrorPushRef($refName)) {
                continue;
            }

            $localRefs[$refName] = $refId;
            $remoteId = $discovery->ref($refName);

            if ($remoteId !== null && $remoteId->equals($refId)) {
                continue;
            }

            $updates[] = [
                'old' => $remoteId ?? $zero,
                'new' => $refId,
                'ref' => $refName,
            ];
        }

        foreach ($discovery->refs() as $refName => $remoteId) {
            if (!$this->isMirrorPushRef($refName) || isset($localRefs[$refName])) {
                continue;
            }

            if ($capabilities !== null && !$capabilities->has('delete-refs')) {
                throw new \RuntimeException('Remote does not support ref deletions required for mirror push');
            }

            $updates[] = [
                'old' => $remoteId,
                'new' => $zero,
                'ref' => $refName,
            ];
        }

        $this->pushUpdates($remote, $http, $url, $discovery, $updates);
    }

    /**
     * @param array<string, ObjectId> $remoteRefs
     * @return list<array{src: string, dst: string, id: ObjectId}>
     */
    private function plannedFetchRefs(string $remote, array $remoteRefs): array
    {
        $refspecs = $this->config->getAll("remote.{$remote}.fetch");

        if ($refspecs === []) {
            $refspecs = ["+refs/heads/*:refs/remotes/{$remote}/*"];
        }
        $negativePatterns = [];

        foreach ($refspecs as $refspec) {
            if (str_starts_with($refspec, '^')) {
                $negativePatterns[] = substr($refspec, 1);
            }
        }

        $planned = [];

        foreach ($refspecs as $refspec) {
            if (str_starts_with($refspec, '^')) {
                continue;
            }

            if (str_starts_with($refspec, '+')) {
                $refspec = substr($refspec, 1);
            }

            [$srcPattern, $dstPattern] = array_pad(explode(':', $refspec, 2), 2, null);

            if ($srcPattern === null || $dstPattern === null) {
                continue;
            }

            foreach ($remoteRefs as $src => $id) {
                if ($this->matchesRefspecPattern($negativePatterns, $src)) {
                    continue;
                }

                $dst = $this->mapFetchRefspec($srcPattern, $dstPattern, $src);

                if ($dst === null) {
                    continue;
                }

                $planned[$dst] = [
                    'src' => $src,
                    'dst' => $dst,
                    'id' => $id,
                ];
            }
        }

        return array_values($planned);
    }

    private function fetchViaDumbHttp(string $remote, string $url, ProtocolException $smartError): void
    {
        try {
            $client = new DumbHttpClient();
            $remoteRefs = $client->fetchRefs($url);
            $trackedRefs = $this->plannedFetchRefs($remote, $remoteRefs);

            if ($trackedRefs === []) {
                return;
            }

            $needsImport = false;

            foreach ($trackedRefs as $trackedRef) {
                if (!$this->objects->exists($trackedRef['id'])) {
                    $needsImport = true;
                    break;
                }
            }

            if ($needsImport) {
                $this->importDumbHttpObjects($client, $url, $trackedRefs, $remoteRefs);
            }

            $this->updateFetchedRefs($trackedRefs, $remoteRefs);
        } catch (ProtocolException) {
            throw $smartError;
        }
    }

    /**
     * @param list<array{src: string, dst: string, id: ObjectId}> $trackedRefs
     * @param array<string, ObjectId> $remoteRefs
     */
    private function updateFetchedRefs(array $trackedRefs, array $remoteRefs): void
    {
        foreach ($trackedRefs as $trackedRef) {
            $this->refs->update($trackedRef['dst'], $trackedRef['id']);
        }

        foreach ($remoteRefs as $refName => $refId) {
            if (str_starts_with($refName, 'refs/tags/') && !str_ends_with($refName, '^{}')) {
                $this->refs->update($refName, $refId);
            }
        }
    }

    /**
     * @param list<array{src: string, dst: string, id: ObjectId}> $trackedRefs
     * @param array<string, ObjectId> $remoteRefs
     */
    private function importDumbHttpObjects(
        DumbHttpClient $client,
        string $url,
        array $trackedRefs,
        array $remoteRefs,
    ): void {
        $packs = $client->fetchPackList($url);

        if ($packs !== []) {
            foreach ($packs as $packName) {
                $packData = $client->fetchPack($url, $packName);
                $packPath = $this->commonDir . '/objects/pack/' . $packName;
                $packDir = dirname($packPath);

                if (!is_dir($packDir)) {
                    mkdir($packDir, 0777, true);
                }

                file_put_contents($packPath, $packData);

                if (str_ends_with($packName, '.pack')) {
                    $idxName = preg_replace('/\.pack$/', '.idx', $packName) ?? ($packName . '.idx');
                    $idxPath = $this->commonDir . '/objects/pack/' . $idxName;
                    file_put_contents($idxPath, $client->fetchPack($url, $idxName));
                    $idxPath = substr($packPath, 0, -5) . '.idx';

                    if (!is_file($idxPath)) {
                        PackIndexer::writeIndex($packPath);
                    }
                }
            }

            $this->objects->packStore()->refresh();

            return;
        }

        $seen = [];

        foreach ($trackedRefs as $trackedRef) {
            $this->downloadDumbHttpObject($client, $url, $trackedRef['id'], $seen);
        }

        foreach ($remoteRefs as $refName => $refId) {
            if (str_starts_with($refName, 'refs/tags/') && !str_ends_with($refName, '^{}')) {
                $this->downloadDumbHttpObject($client, $url, $refId, $seen);
            }
        }
    }

    /**
     * @param array<string, bool> $seen
     */
    private function downloadDumbHttpObject(
        DumbHttpClient $client,
        string $url,
        ObjectId $id,
        array &$seen,
    ): void {
        if (isset($seen[$id->hex]) || $this->objects->exists($id)) {
            return;
        }

        $seen[$id->hex] = true;
        $this->objects->looseStore()->writeEncoded($id, $client->fetchObject($url, $id->hex));
        $object = $this->objects->read($id);

        if ($object instanceof Commit) {
            $this->downloadDumbHttpObject($client, $url, $object->tree, $seen);

            foreach ($object->parents as $parent) {
                $this->downloadDumbHttpObject($client, $url, $parent, $seen);
            }

            return;
        }

        if ($object instanceof Tree) {
            foreach ($object->entries as $entry) {
                $this->downloadDumbHttpObject($client, $url, $entry->hash, $seen);
            }

            return;
        }

        if ($object instanceof Tag) {
            $this->downloadDumbHttpObject($client, $url, $object->object, $seen);
        }
    }

    /**
     * @param list<string> $patterns
     */
    private function matchesRefspecPattern(array $patterns, string $srcRef): bool
    {
        foreach ($patterns as $pattern) {
            if ($this->mapFetchRefspec($pattern, $pattern, $srcRef) !== null) {
                return true;
            }
        }

        return false;
    }

    private function mapFetchRefspec(string $srcPattern, string $dstPattern, string $srcRef): ?string
    {
        if (str_contains($srcPattern, '*') !== str_contains($dstPattern, '*')) {
            return null;
        }

        if (!str_contains($srcPattern, '*')) {
            return $srcRef === $srcPattern ? $dstPattern : null;
        }

        [$srcPrefix, $srcSuffix] = explode('*', $srcPattern, 2);

        if (!str_starts_with($srcRef, $srcPrefix) || !str_ends_with($srcRef, $srcSuffix)) {
            return null;
        }

        $wildcard = substr($srcRef, strlen($srcPrefix), strlen($srcRef) - strlen($srcPrefix) - strlen($srcSuffix));

        return str_replace('*', $wildcard, $dstPattern);
    }

    /**
     * Compute working tree status.
     *
     * @return array<int, StatusEntry>
     */
    public function status(): array
    {
        $index = $this->index();
        $headId = $this->refs->resolveHead();
        $status = new WorkingTreeStatus($this->objects, $this->workDir, $this->gitDir);

        return $status->compute($index, $headId);
    }

    /**
     * Diff worktree vs index (unstaged changes).
     *
     * @return array<int, DiffResult>
     */
    public function diff(?string $pathspec = null): array
    {
        $index = $this->index();
        $results = [];

        foreach ($index->entries() as $entry) {
            if ($pathspec !== null && $entry->path !== $pathspec) {
                continue;
            }

            $fullPath = $this->workDir . '/' . $entry->path;

            if (!is_file($fullPath)) {
                // Deleted in worktree
                $oldContent = $this->readBlobContent($entry->hash);
                $hunks = MyersDiff::diff($oldContent, '');

                if ($hunks !== []) {
                    $results[] = new DiffResult($entry->path, $entry->path, $hunks, false, $entry->hash->hex, null);
                }

                continue;
            }

            $worktreeContent = file_get_contents($fullPath);

            if ($worktreeContent === false) {
                continue;
            }

            $indexContent = $this->readBlobContent($entry->hash);

            if ($indexContent !== $worktreeContent) {
                $newHash = ObjectId::compute(ObjectType::Blob, $worktreeContent);

                if (MyersDiff::isBinary($indexContent) || MyersDiff::isBinary($worktreeContent)) {
                    $results[] = new DiffResult(
                        $entry->path,
                        $entry->path,
                        [],
                        true,
                        $entry->hash->hex,
                        $newHash->hex
                    );
                } else {
                    $hunks = MyersDiff::diff($indexContent, $worktreeContent);
                    $results[] = new DiffResult(
                        $entry->path,
                        $entry->path,
                        $hunks,
                        false,
                        $entry->hash->hex,
                        $newHash->hex
                    );
                }
            }
        }

        return $results;
    }

    /**
     * Diff index vs HEAD (staged changes).
     *
     * @return array<int, DiffResult>
     */
    public function diffStaged(?string $pathspec = null): array
    {
        $headId = $this->refs->resolveHead();

        if ($headId === null) {
            return [];
        }

        $commit = $this->objects->read($headId);

        if (!$commit instanceof Commit) {
            return [];
        }

        $index = $this->index();
        $treeDiff = new TreeDiff($this->objects);

        // Build a tree from the index
        $indexTreeId = $this->buildTreeFromIndex($index);

        return $treeDiff->diff($commit->tree, $indexTreeId);
    }

    /**
     * Diff two trees by ObjectId.
     *
     * @return array<int, DiffResult>
     */
    public function diffTree(ObjectId $a, ObjectId $b): array
    {
        $treeDiff = new TreeDiff($this->objects);

        return $treeDiff->diff($a, $b);
    }

    /**
     * Merge a branch into HEAD.
     */
    public function merge(string $branch): MergeResult
    {
        $theirsId = $this->resolve($branch);
        $oursId = $this->refs->resolveHead();
        $trackedPaths = $this->index()->paths();

        if ($oursId === null) {
            throw new \RuntimeException('Cannot merge: HEAD is not set');
        }

        // Find merge base
        $mergeBaseFinder = new MergeBase($this->objects);
        $baseId = $mergeBaseFinder->find($oursId, $theirsId);

        // Fast-forward check
        if ($baseId !== null && $baseId->equals($oursId)) {
            $this->refs->looseStore()->update('ORIG_HEAD', $oursId);
            $this->moveHeadTo($theirsId, "merge {$branch}: Fast-forward");
            $this->resetWorktree($theirsId, $trackedPaths);
            $this->runPostMergeHook();

            return new MergeResult(clean: true, commitId: $theirsId);
        }

        $oursCommit = $this->objects->read($oursId);
        $theirsCommit = $this->objects->read($theirsId);

        if (!$oursCommit instanceof Commit || !$theirsCommit instanceof Commit) {
            throw new \RuntimeException('Cannot merge: invalid commit objects');
        }

        $merge = $this->mergeTreeEntries(
            $baseId !== null ? $this->getCommitTree($baseId) : null,
            $oursCommit->tree,
            $theirsCommit->tree,
            'HEAD',
            $branch,
            $baseId !== null ? substr($baseId->hex, 0, 7) : 'base',
        );

        if ($merge['conflictPaths'] !== []) {
            $this->writeOperationConflictState(
                $merge['mergedEntries'],
                $merge['conflictEntries'],
                $merge['conflictContents'],
                $trackedPaths,
                'MERGE_HEAD',
                $theirsId,
                $this->buildMergeMessage($branch, $merge['conflictPaths']),
                $oursId,
            );

            return new MergeResult(
                clean: false,
                conflictPaths: $merge['conflictPaths'],
                mergedContents: $merge['conflictContents'],
            );
        }

        $this->refs->looseStore()->update('ORIG_HEAD', $oursId);
        $treeId = $this->buildTreeFromEntries($merge['mergedEntries']);
        $commitId = $this->createCommitFromTree($treeId, "Merge branch '{$branch}'\n", [$oursId, $theirsId]);

        $this->moveHeadTo($commitId, "merge {$branch}: Merge made by Pitmaster");

        $this->resetWorktree($commitId, $trackedPaths);
        $this->runPostMergeHook();

        return new MergeResult(clean: true, commitId: $commitId);
    }

    /**
     * Continue an in-progress merge after resolving conflicts.
     */
    public function mergeContinue(): ObjectId
    {
        if ($this->refs->resolve('MERGE_HEAD') === null) {
            throw new \RuntimeException('Cannot continue: no merge in progress');
        }

        if ($this->index()->hasUnmerged()) {
            throw new \RuntimeException('Cannot continue merge with unmerged paths');
        }

        $commitId = $this->commit();
        $this->runPostMergeHook();

        return $commitId;
    }

    /**
     * Abort an in-progress merge and restore the worktree to the original HEAD.
     */
    public function mergeAbort(): void
    {
        $this->abortHeadBasedOperation('MERGE_HEAD', false);
    }

    /**
     * Find the merge base of two commits.
     */
    public function mergeBase(ObjectId $a, ObjectId $b): ?ObjectId
    {
        return (new MergeBase($this->objects))->find($a, $b);
    }

    public function config(): GitConfig
    {
        return $this->config;
    }

    public function objectDatabase(): ObjectDatabase
    {
        return $this->objects;
    }

    public function refDatabase(): RefDatabase
    {
        return $this->refs;
    }

    /**
     * Build a tree hierarchy from flat index entries.
     */
    private function buildTreeFromIndex(Index $index): ObjectId
    {
        // Group entries by directory
        $root = [];

        foreach ($index->entries() as $entry) {
            $parts = explode('/', $entry->path);
            $this->insertIntoTree($root, $parts, $entry);
        }

        return $this->writeTreeRecursive($root);
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, string> $parts
     */
    private function insertIntoTree(array &$node, array $parts, IndexEntry $entry): void
    {
        if (count($parts) === 1) {
            $node[$parts[0]] = $entry;

            return;
        }

        $dir = array_shift($parts);

        if (!isset($node[$dir]) || !is_array($node[$dir])) {
            $node[$dir] = [];
        }

        $this->insertIntoTree($node[$dir], $parts, $entry);
    }

    /**
     * @param array<string, mixed> $node
     */
    private function writeTreeRecursive(array $node): ObjectId
    {
        $entries = [];

        foreach ($node as $name => $value) {
            if ($value instanceof IndexEntry) {
                $mode = match ($value->mode) {
                    0100755 => '100755',
                    0120000 => '120000',
                    0160000 => '160000',
                    default => '100644',
                };
                $entries[] = new TreeEntry($mode, (string) $name, $value->hash);
            } elseif (is_array($value)) {
                $subtreeId = $this->writeTreeRecursive($value);
                $entries[] = new TreeEntry('40000', (string) $name, $subtreeId);
            }
        }

        // Sort entries (git sorts trees with trailing / for directories)
        usort($entries, function (TreeEntry $a, TreeEntry $b): int {
            $nameA = $a->isTree() ? $a->name . '/' : $a->name;
            $nameB = $b->isTree() ? $b->name . '/' : $b->name;

            return strcmp($nameA, $nameB);
        });

        $tree = Tree::fromEntries($entries);
        $this->objects->write($tree);

        return $tree->id;
    }

    private function readBlobContent(ObjectId $hash): string
    {
        $object = $this->objects->read($hash);

        if ($object instanceof Blob) {
            return $object->content;
        }

        return '';
    }

    private function getCommitTree(ObjectId $commitId): ?ObjectId
    {
        $commit = $this->objects->read($commitId);

        if ($commit instanceof Commit) {
            return $commit->tree;
        }

        return null;
    }

    /**
     * Flatten a tree into path => hex hash map.
     *
     * @return array<string, string>
     */
    private function flattenTree(?ObjectId $treeId, string $prefix = ''): array
    {
        $result = [];

        if ($treeId === null) {
            return $result;
        }

        $tree = $this->objects->read($treeId);

        if (!$tree instanceof Tree) {
            return $result;
        }

        foreach ($tree->entries as $entry) {
            $fullPath = $prefix !== '' ? $prefix . '/' . $entry->name : $entry->name;

            if ($entry->isTree()) {
                $result = array_merge($result, $this->flattenTree($entry->hash, $fullPath));
            } else {
                $result[$fullPath] = $entry->hash->hex;
            }
        }

        return $result;
    }

    /**
     * Flatten a tree into path => hash/mode map.
     *
     * @return array<string, array{hash: string, mode: int}>
     */
    private function flattenTreeEntries(?ObjectId $treeId, string $prefix = ''): array
    {
        $result = [];

        if ($treeId === null) {
            return $result;
        }

        $tree = $this->objects->read($treeId);

        if (!$tree instanceof Tree) {
            return $result;
        }

        foreach ($tree->entries as $entry) {
            $fullPath = $prefix !== '' ? $prefix . '/' . $entry->name : $entry->name;

            if ($entry->isTree()) {
                $result = array_merge($result, $this->flattenTreeEntries($entry->hash, $fullPath));
            } else {
                $result[$fullPath] = [
                    'hash' => $entry->hash->hex,
                    'mode' => octdec($entry->mode),
                ];
            }
        }

        return $result;
    }

    /**
     * Reset worktree and index to match a commit.
     */
    /**
     * @param array<int, string> $preserveRefs
     */
    private function resetWorktree(ObjectId $commitId, array $pathsToPrune = [], array $preserveRefs = []): void
    {
        $commit = $this->objects->read($commitId);

        if (!$commit instanceof Commit) {
            return;
        }

        $this->clearOperationState($preserveRefs);
        $treeMap = $this->flattenTree($commit->tree);
        $treeEntries = $this->flattenTreeEntries($commit->tree);
        $this->materializeTreeMap($treeMap, $this->workDir, $pathsToPrune);
        $index = new Index();
        $sparse = new SparseCheckout($this->gitDir);
        $sparseEnabled = $sparse->isEnabled();

        foreach ($treeMap as $path => $hash) {
            $blob = $this->objects->read(ObjectId::fromHex($hash));

            if (!$blob instanceof Blob) {
                continue;
            }

            $fullPath = $this->workDir . '/' . $path;
            $extendedFlags = $sparseEnabled && !$sparse->includes($path)
                ? IndexEntry::EXTENDED_SKIP_WORKTREE
                : 0;
            $entry = is_file($fullPath)
                ? IndexEntry::fromStat($path, $blob->id, $fullPath, $extendedFlags)
                : IndexEntry::create(
                    $path,
                    $blob->id,
                    $treeEntries[$path]['mode'] ?? 0100644,
                    strlen($blob->content),
                    0,
                    $extendedFlags,
                );
            $index->addEntry($entry);
        }

        IndexWriter::write($index, $this->gitDir . '/index');
    }

    /**
     * Checkout files from a tree into a directory.
     */
    private function checkoutTree(ObjectId $treeId, string $targetDir): void
    {
        $this->materializeTreeMap($this->flattenTree($treeId), $targetDir);
    }

    /**
     * @param array<string, string> $treeMap
     * @param array<int, string> $pathsToPrune
     */
    private function materializeTreeMap(array $treeMap, string $targetDir, array $pathsToPrune = []): void
    {
        $materializedTreeMap = $this->materializedTreeMap($treeMap, $targetDir);
        $this->pruneMissingPaths($materializedTreeMap, $targetDir, $pathsToPrune);

        foreach ($materializedTreeMap as $path => $hash) {
            $blob = $this->objects->read(ObjectId::fromHex($hash));

            if (!$blob instanceof Blob) {
                continue;
            }

            $fullPath = $targetDir . '/' . $path;
            $dir = dirname($fullPath);

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            file_put_contents($fullPath, $blob->content);
        }
    }

    /**
     * @param array<string, string> $treeMap
     * @return array<string, string>
     */
    private function materializedTreeMap(array $treeMap, string $targetDir): array
    {
        if ($targetDir !== $this->workDir) {
            return $treeMap;
        }

        $sparse = new SparseCheckout($this->gitDir);

        if (!$sparse->isEnabled()) {
            return $treeMap;
        }

        return array_filter(
            $treeMap,
            static fn (string $path): bool => $sparse->includes($path),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @param array<string, string> $treeMap
     * @param array<int, string> $pathsToPrune
     */
    private function pruneMissingPaths(array $treeMap, string $targetDir, array $pathsToPrune): void
    {
        foreach (array_unique($pathsToPrune) as $path) {
            if (isset($treeMap[$path])) {
                continue;
            }

            $fullPath = $targetDir . '/' . $path;

            if (is_file($fullPath) || is_link($fullPath)) {
                unlink($fullPath);
                $this->removeEmptyParentDirectories(dirname($fullPath), $targetDir);
            }
        }
    }

    private function removeEmptyParentDirectories(string $directory, string $stopAt): void
    {
        while ($directory !== $stopAt && str_starts_with($directory, $stopAt . '/')) {
            if (!is_dir($directory)) {
                $directory = dirname($directory);
                continue;
            }

            $entries = scandir($directory);

            if ($entries === false || count($entries) > 2) {
                return;
            }

            rmdir($directory);
            $directory = dirname($directory);
        }
    }

    private function resolvedMoveDestination(string $source, string $destination): string
    {
        $destinationFull = $this->workDir . '/' . $destination;

        if (is_dir($destinationFull)) {
            return trim($destination . '/' . basename($source), '/');
        }

        return $destination;
    }

    /**
     * @return list<IndexEntry>
     */
    private function trackedEntriesForPath(Index $index, string $path): array
    {
        $entries = [];
        $prefix = $path . '/';

        foreach ($index->allEntries() as $entry) {
            if ($entry->path === $path || str_starts_with($entry->path, $prefix)) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param list<string> $arguments
     * @return array{cached: bool, recursive: bool, paths: list<string>}
     */
    private function parseRemoveArguments(array $arguments): array
    {
        $cached = false;
        $recursive = false;
        $paths = [];

        foreach ($arguments as $argument) {
            if ($argument === '--cached') {
                $cached = true;
                continue;
            }

            if ($argument === '-r' || $argument === '--recursive') {
                $recursive = true;
                continue;
            }

            $paths[] = $argument;
        }

        return [
            'cached' => $cached,
            'recursive' => $recursive,
            'paths' => $paths,
        ];
    }

    /**
     * @param array{hash: string, mode: int}|null $headEntry
     */
    private function assertRemoveEntryIsSafe(IndexEntry $entry, ?array $headEntry, bool $cached): void
    {
        $indexHash = $entry->hash->hex;
        $headHash = $headEntry['hash'] ?? null;
        $worktreeHash = $this->worktreeBlobHash($entry->path);
        $worktreeDiffersFromIndex = $worktreeHash !== null && $worktreeHash !== $indexHash;
        $indexDiffersFromHead = $headHash !== null && $headHash !== $indexHash;

        if ($cached) {
            if ($worktreeDiffersFromIndex && $indexDiffersFromHead) {
                throw new \RuntimeException("cannot remove '{$entry->path}' from the index because it has staged and unstaged changes");
            }

            return;
        }

        if ($worktreeDiffersFromIndex) {
            throw new \RuntimeException("cannot remove '{$entry->path}' because it has local modifications");
        }

        if ($indexDiffersFromHead) {
            throw new \RuntimeException("cannot remove '{$entry->path}' because it has staged changes");
        }
    }

    private function worktreeBlobHash(string $path): ?string
    {
        $fullPath = $this->workDir . '/' . $path;

        if (!is_file($fullPath)) {
            return null;
        }

        $content = file_get_contents($fullPath);

        if ($content === false) {
            return null;
        }

        return Blob::fromContent($content)->id->hex;
    }

    private function removeWorktreePath(string $path): void
    {
        $fullPath = $this->workDir . '/' . $path;

        if (!is_file($fullPath) && !is_link($fullPath)) {
            return;
        }

        unlink($fullPath);
        $this->removeEmptyParentDirectories(dirname($fullPath), $this->workDir);
    }

    private function restoreWorktreePathFromIndex(string $path): void
    {
        $index = $this->index();
        $entry = $index->entry($path);

        if ($entry === null) {
            throw new \RuntimeException("File not in index: {$path}");
        }

        $blob = $this->objects->read($entry->hash);

        if (!$blob instanceof Blob) {
            throw new \RuntimeException("Index entry for {$path} is not a blob");
        }

        $this->writeRestoredBlob($path, $blob);
    }

    private function restoreWorktreePathFromTree(string $path, string $source): void
    {
        $entries = $this->sourceTreeEntries($source);
        $entry = $entries[$path] ?? null;

        if ($entry === null) {
            $this->removeWorktreePath($path);
            return;
        }

        $blob = $this->objects->read(ObjectId::fromHex($entry['hash']));

        if (!$blob instanceof Blob) {
            throw new \RuntimeException("Source entry for {$path} is not a blob");
        }

        $this->writeRestoredBlob($path, $blob);
    }

    private function restoreIndexPath(string $path, ?string $source): void
    {
        $index = $this->index();
        $entries = $source !== null ? $this->sourceTreeEntries($source) : $this->sourceTreeEntries('HEAD');
        $entry = $entries[$path] ?? null;

        if ($entry === null) {
            $index->removeEntry($path);
            IndexWriter::write($index, $this->gitDir . '/index');
            return;
        }

        $blob = $this->objects->read(ObjectId::fromHex($entry['hash']));

        if (!$blob instanceof Blob) {
            throw new \RuntimeException("Source entry for {$path} is not a blob");
        }

        $index->removeEntry($path);
        $index->addEntry(IndexEntry::create(
            $path,
            $blob->id,
            $entry['mode'],
            strlen($blob->content),
        ));
        IndexWriter::write($index, $this->gitDir . '/index');
    }

    /**
     * @return array<string, array{hash: string, mode: int}>
     */
    private function sourceTreeEntries(string $source): array
    {
        $id = $this->resolve($source);
        $object = $this->objects->read($id);

        if ($object instanceof Tag) {
            $object = $this->objects->read($object->object);
        }

        if (!$object instanceof Commit) {
            throw new \RuntimeException("Not a commit-ish: {$source}");
        }

        return $this->flattenTreeEntries($object->tree);
    }

    private function writeRestoredBlob(string $path, Blob $blob): void
    {
        $fullPath = $this->workDir . '/' . $path;
        $parent = dirname($fullPath);

        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }

        file_put_contents($fullPath, $blob->content);
    }

    private function movedPath(string $source, string $destination, string $path): string
    {
        if ($path === $source) {
            return $destination;
        }

        return $destination . substr($path, strlen($source));
    }

    private function relocateIndexEntry(IndexEntry $entry, string $path): IndexEntry
    {
        $fullPath = $this->workDir . '/' . $path;
        $stat = @stat($fullPath);
        $flags = min(strlen($path), 0x0FFF) | ($entry->stage() << 12);

        if ($entry->assumeValid()) {
            $flags |= 0x8000;
        }

        if ($entry->extendedFlags !== 0) {
            $flags |= 0x4000;
        }

        if ($stat === false) {
            return new IndexEntry(
                ctimeSec: $entry->ctimeSec,
                ctimeNsec: $entry->ctimeNsec,
                mtimeSec: $entry->mtimeSec,
                mtimeNsec: $entry->mtimeNsec,
                dev: $entry->dev,
                ino: $entry->ino,
                mode: $entry->mode,
                uid: $entry->uid,
                gid: $entry->gid,
                fileSize: $entry->fileSize,
                hash: $entry->hash,
                flags: $flags,
                path: $path,
                extendedFlags: $entry->extendedFlags,
            );
        }

        return new IndexEntry(
            ctimeSec: (int) $stat['ctime'],
            ctimeNsec: 0,
            mtimeSec: (int) $stat['mtime'],
            mtimeNsec: 0,
            dev: (int) $stat['dev'],
            ino: (int) $stat['ino'],
            mode: is_executable($fullPath) ? 0100755 : $entry->mode,
            uid: (int) $stat['uid'],
            gid: (int) $stat['gid'],
            fileSize: (int) $stat['size'],
            hash: $entry->hash,
            flags: $flags,
            path: $path,
            extendedFlags: $entry->extendedFlags,
        );
    }

    private function worktreeMode(string $path): ?int
    {
        $fullPath = $this->workDir . '/' . $path;

        if (is_link($fullPath)) {
            return 0120000;
        }

        if (!file_exists($fullPath)) {
            return null;
        }

        if (is_dir($fullPath)) {
            return 0040000;
        }

        return is_executable($fullPath) ? 0100755 : 0100644;
    }

    private function buildTreeFromEntries(array $entries): ObjectId
    {
        $index = new Index();

        foreach ($entries as $path => $entry) {
            $index->addEntry(IndexEntry::create($path, ObjectId::fromHex($entry['hash']), $entry['mode']));
        }

        return $this->buildTreeFromIndex($index);
    }

    /**
     * @return array{parents: array<int, ObjectId>, author?: string, message?: string, type?: string}|null
     */
    private function pendingOperationState(?ObjectId $headId): ?array
    {
        $message = $this->readStateMessage();

        if ($headId !== null) {
            $mergeHead = $this->refs->resolve('MERGE_HEAD');

            if ($mergeHead !== null) {
                return [
                    'parents' => [$headId, $mergeHead],
                    'message' => $message,
                    'type' => 'merge',
                ];
            }

            $cherryPickHead = $this->refs->resolve('CHERRY_PICK_HEAD');

            if ($cherryPickHead !== null) {
                $picked = $this->objects->read($cherryPickHead);

                return [
                    'parents' => [$headId],
                    'author' => $picked instanceof Commit ? $picked->author : null,
                    'message' => $message,
                    'type' => 'cherry-pick',
                ];
            }

            $revertHead = $this->refs->resolve('REVERT_HEAD');

            if ($revertHead !== null) {
                return [
                    'parents' => [$headId],
                    'message' => $message,
                    'type' => 'revert',
                ];
            }

            $rebaseHead = $this->refs->resolve('REBASE_HEAD');

            if ($rebaseHead !== null && $this->readRebaseState() !== null) {
                $picked = $this->objects->read($rebaseHead);

                return [
                    'parents' => [$headId],
                    'author' => $picked instanceof Commit ? $picked->author : null,
                    'message' => $message,
                    'type' => 'rebase',
                ];
            }
        }

        return null;
    }

    /**
     * @param array{message?: string}|null $state
     */
    private function resolveCommitMessage(?string $message, ?array $state): string
    {
        if ($message !== null) {
            return $message;
        }

        $stateMessage = $state['message'] ?? null;

        if (is_string($stateMessage) && trim($stateMessage) !== '') {
            return $this->stripCommentedMessage($stateMessage);
        }

        throw new \RuntimeException('Commit message required');
    }

    private function readStateMessage(): ?string
    {
        $path = $this->gitDir . '/MERGE_MSG';

        if (is_file($path)) {
            $message = file_get_contents($path);

            if ($message !== false) {
                return $message;
            }
        }

        $rebasePath = $this->gitDir . '/rebase-merge/message';

        if (!is_file($rebasePath)) {
            return null;
        }

        $message = file_get_contents($rebasePath);

        return $message !== false ? $message : null;
    }

    private function stripCommentedMessage(string $message): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $message) ?: [];
        $kept = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, '#')) {
                continue;
            }

            $kept[] = $line;
        }

        while ($kept !== [] && trim((string) end($kept)) === '') {
            array_pop($kept);
        }

        return implode("\n", $kept) . "\n";
    }

    /**
     * @param array<int, ObjectId> $parents
     */
    private function createCommitFromTree(
        ObjectId $treeId,
        string $message,
        array $parents,
        ?string $author = null,
        ?string $committer = null,
    ): ObjectId {
        $committer = $committer ?? $this->currentCommitterIdentity();
        $author = $author ?? $this->currentAuthorIdentity();

        $content = Commit::buildContent(
            tree: $treeId,
            parents: $parents,
            author: $author,
            committer: $committer,
            message: $message,
        );

        $commitId = ObjectId::compute(ObjectType::Commit, $content);
        $commit = Commit::parse($content, $commitId);
        $this->objects->write($commit);

        return $commitId;
    }

    /**
     * @return array{
     *   mergedEntries: array<string, array{hash: string, mode: int}>,
     *   conflictEntries: array<string, array<int, array{hash: string, mode: int}>>,
     *   conflictContents: array<string, string>,
     *   conflictPaths: array<int, string>
     * }
     */
    private function mergeTreeEntries(
        ?ObjectId $baseTreeId,
        ObjectId $oursTreeId,
        ObjectId $theirsTreeId,
        string $oursLabel,
        string $theirsLabel,
        string $baseLabel = 'base',
    ): array {
        $baseEntries = $this->flattenTreeEntries($baseTreeId);
        $oursEntries = $this->flattenTreeEntries($oursTreeId);
        $theirsEntries = $this->flattenTreeEntries($theirsTreeId);
        $allPaths = array_unique(array_merge(array_keys($baseEntries), array_keys($oursEntries), array_keys($theirsEntries)));
        sort($allPaths);

        $mergedEntries = [];
        $conflictEntries = [];
        $conflictContents = [];
        $conflictPaths = [];
        $conflictStyle = $this->mergeConflictStyle();

        foreach ($allPaths as $path) {
            $base = $baseEntries[$path] ?? null;
            $ours = $oursEntries[$path] ?? null;
            $theirs = $theirsEntries[$path] ?? null;
            $baseHash = $base['hash'] ?? null;
            $oursHash = $ours['hash'] ?? null;
            $theirsHash = $theirs['hash'] ?? null;

            if ($oursHash === $theirsHash) {
                if ($ours !== null) {
                    $mergedEntries[$path] = $ours;
                }

                continue;
            }

            if ($baseHash === $oursHash) {
                if ($theirs !== null) {
                    $mergedEntries[$path] = $theirs;
                }

                continue;
            }

            if ($baseHash === $theirsHash) {
                if ($ours !== null) {
                    $mergedEntries[$path] = $ours;
                }

                continue;
            }

            if ($ours === null || $theirs === null) {
                $conflictPaths[] = $path;
                $conflictEntries[$path] = $this->conflictStageEntries($base, $ours, $theirs);
                $conflictContents[$path] = $ours !== null
                    ? $this->readBlobContent(ObjectId::fromHex($ours['hash']))
                    : $this->readBlobContent(ObjectId::fromHex($theirs['hash']));
                continue;
            }

            $baseContent = $baseHash !== null ? $this->readBlobContent(ObjectId::fromHex($baseHash)) : '';
            $oursContent = $this->readBlobContent(ObjectId::fromHex($oursHash));
            $theirsContent = $this->readBlobContent(ObjectId::fromHex($theirsHash));

            if (
                MyersDiff::isBinary($baseContent)
                || MyersDiff::isBinary($oursContent)
                || MyersDiff::isBinary($theirsContent)
            ) {
                $conflictPaths[] = $path;
                $conflictEntries[$path] = $this->conflictStageEntries($base, $ours, $theirs);
                $conflictContents[$path] = $oursContent;
                continue;
            }

            if ($base === null) {
                $conflictPaths[] = $path;
                $conflictEntries[$path] = $this->conflictStageEntries($base, $ours, $theirs);
                $conflictContents[$path] = ConflictMarker::mark(
                    $oursContent,
                    $theirsContent,
                    $oursLabel,
                    $theirsLabel,
                    $conflictStyle === 'diff3' ? '' : null,
                    $baseLabel,
                );
                continue;
            }

            $merge = ThreeWayMerge::merge(
                $baseContent,
                $oursContent,
                $theirsContent,
                $oursLabel,
                $theirsLabel,
                $conflictStyle,
                $baseLabel,
            );

            if (!$merge['clean']) {
                $conflictPaths[] = $path;
                $conflictEntries[$path] = $this->conflictStageEntries($base, $ours, $theirs);
                $conflictContents[$path] = $merge['content'];
                continue;
            }

            $blob = Blob::fromContent($merge['content']);
            $this->objects->write($blob);
            $mergedEntries[$path] = [
                'hash' => $blob->id->hex,
                'mode' => $ours['mode'],
            ];
        }

        return [
            'mergedEntries' => $mergedEntries,
            'conflictEntries' => $conflictEntries,
            'conflictContents' => $conflictContents,
            'conflictPaths' => $conflictPaths,
        ];
    }

    /**
     * @param array{hash: string, mode: int}|null $base
     * @param array{hash: string, mode: int}|null $ours
     * @param array{hash: string, mode: int}|null $theirs
     * @return array<int, array{hash: string, mode: int}>
     */
    private function conflictStageEntries(?array $base, ?array $ours, ?array $theirs): array
    {
        $entries = [];

        if ($base !== null) {
            $entries[1] = $base;
        }

        if ($ours !== null) {
            $entries[2] = $ours;
        }

        if ($theirs !== null) {
            $entries[3] = $theirs;
        }

        return $entries;
    }

    /**
     * @param array<string, array{hash: string, mode: int}> $mergedEntries
     * @param array<string, array<int, array{hash: string, mode: int}>> $conflictEntries
     * @param array<string, string> $conflictContents
     * @param array<int, string> $pathsToPrune
     */
    private function writeOperationConflictState(
        array $mergedEntries,
        array $conflictEntries,
        array $conflictContents,
        array $pathsToPrune,
        string $headName,
        ObjectId $headTarget,
        string $message,
        ?ObjectId $origHead = null,
    ): void {
        $treeMap = [];

        foreach ($mergedEntries as $path => $entry) {
            $treeMap[$path] = $entry['hash'];
        }

        $this->materializeTreeMap($treeMap, $this->workDir, $pathsToPrune);
        $this->materializeConflictContents($conflictContents);

        $index = new Index();

        foreach ($mergedEntries as $path => $entry) {
            $fullPath = $this->workDir . '/' . $path;
            $id = ObjectId::fromHex($entry['hash']);

            if (is_file($fullPath)) {
                $index->addEntry(IndexEntry::fromStat($path, $id, $fullPath));
                continue;
            }

            $index->addEntry(IndexEntry::create($path, $id, $entry['mode']));
        }

        foreach ($conflictEntries as $path => $stages) {
            foreach ($stages as $stage => $entry) {
                $id = ObjectId::fromHex($entry['hash']);
                $blob = $this->objects->read($id);
                $size = $blob instanceof Blob ? strlen($blob->content) : 0;
                $index->addEntry(IndexEntry::create($path, $id, $entry['mode'], $size, $stage));
            }
        }

        IndexWriter::write($index, $this->gitDir . '/index');
        $this->refs->looseStore()->update($headName, $headTarget);
        file_put_contents($this->gitDir . '/MERGE_MSG', $message);
        $this->writeAutoMergeTree($mergedEntries, $conflictEntries, $conflictContents);

        if ($headName === 'MERGE_HEAD') {
            file_put_contents($this->gitDir . '/MERGE_MODE', '');
        } else {
            @unlink($this->gitDir . '/MERGE_MODE');
        }

        if ($origHead !== null) {
            $this->refs->looseStore()->update('ORIG_HEAD', $origHead);
        }
    }

    /**
     * @param array<int, string> $preserveRefs
     */
    private function clearOperationState(array $preserveRefs = []): void
    {
        foreach (['MERGE_HEAD', 'CHERRY_PICK_HEAD', 'REVERT_HEAD', 'REBASE_HEAD'] as $refName) {
            if (in_array($refName, $preserveRefs, true)) {
                continue;
            }

            $this->refs->looseStore()->delete($refName);
        }

        @unlink($this->gitDir . '/MERGE_MSG');
        @unlink($this->gitDir . '/AUTO_MERGE');
        @unlink($this->gitDir . '/MERGE_MODE');
    }

    /**
     * Abort an in-progress merge-family operation and restore HEAD state in the worktree/index.
     */
    private function abortHeadBasedOperation(string $operationRef, bool $writeOrigHead, ?string $headReflogMessage = null): void
    {
        if ($this->refs->resolve($operationRef) === null) {
            throw new \RuntimeException("Cannot abort: no {$this->humanOperationName($operationRef)} in progress");
        }

        $headId = $this->refs->resolveHead();

        if ($headId === null) {
            throw new \RuntimeException('Cannot abort: HEAD is not set');
        }

        if ($writeOrigHead) {
            $this->refs->looseStore()->update('ORIG_HEAD', $headId);
        }

        $trackedPaths = array_values(array_unique(array_merge(
            $this->index()->paths(),
            array_keys($this->flattenTree($this->getCommitTree($headId))),
        )));

        $this->resetWorktree($headId, $trackedPaths);

        if ($headReflogMessage !== null) {
            $this->appendReflogEntry('HEAD', $headId, $headId, $headReflogMessage);
        }
    }

    private function humanOperationName(string $operationRef): string
    {
        return match ($operationRef) {
            'MERGE_HEAD' => 'merge',
            'CHERRY_PICK_HEAD' => 'cherry-pick',
            'REVERT_HEAD' => 'revert',
            default => strtolower(str_replace('_HEAD', '', $operationRef)),
        };
    }

    private function mergeConflictStyle(): string
    {
        return strtolower((string) ($this->config->get('merge.conflictstyle') ?? 'merge')) === 'diff3'
            ? 'diff3'
            : 'merge';
    }

    /**
     * @param array<string, string> $mergedContents
     */
    private function materializeConflictContents(array $mergedContents): void
    {
        foreach ($mergedContents as $path => $content) {
            $fullPath = $this->workDir . '/' . $path;
            $dir = dirname($fullPath);

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            file_put_contents($fullPath, $content);
        }
    }

    /**
     * @param array<string, array{hash: string, mode: int}> $mergedEntries
     * @param array<string, array<int, array{hash: string, mode: int}>> $conflictEntries
     * @param array<string, string> $conflictContents
     */
    private function writeAutoMergeTree(array $mergedEntries, array $conflictEntries, array $conflictContents): void
    {
        $entries = $mergedEntries;

        foreach ($conflictContents as $path => $content) {
            $blob = Blob::fromContent($content);
            $this->objects->write($blob);
            $entries[$path] = [
                'hash' => $blob->id->hex,
                'mode' => $this->conflictWorktreeMode($conflictEntries[$path] ?? []),
            ];
        }

        $treeId = $this->buildTreeFromEntries($entries);
        file_put_contents($this->gitDir . '/AUTO_MERGE', $treeId->hex . "\n");
    }

    /**
     * @param array<int, array{hash: string, mode: int}> $stages
     */
    private function conflictWorktreeMode(array $stages): int
    {
        foreach ([2, 3, 1] as $stage) {
            if (isset($stages[$stage])) {
                return $stages[$stage]['mode'];
            }
        }

        return 0100644;
    }

    /**
     * @param array<int, string> $conflictPaths
     */
    private function buildMergeMessage(string $branch, array $conflictPaths): string
    {
        return "Merge branch '{$branch}'\n\n" . $this->formatConflictComments($conflictPaths);
    }

    /**
     * @param array<int, IndexEntry> $stages
     */
    private function unmergedPorcelainCode(array $stages): string
    {
        $hasBase = isset($stages[1]);
        $hasOurs = isset($stages[2]);
        $hasTheirs = isset($stages[3]);

        return match (true) {
            $hasBase && $hasOurs && $hasTheirs => 'UU',
            $hasBase && $hasOurs && !$hasTheirs => 'DU',
            $hasBase && !$hasOurs && $hasTheirs => 'UD',
            !$hasBase && $hasOurs && $hasTheirs => 'AA',
            !$hasBase && $hasOurs => 'AU',
            !$hasBase && $hasTheirs => 'UA',
            default => 'UU',
        };
    }

    /**
     * @param array<int, string> $conflictPaths
     */
    private function buildCherryPickMessage(Commit $commit, array $conflictPaths): string
    {
        return rtrim($commit->message, "\n") . "\n\n" . $this->formatConflictComments($conflictPaths);
    }

    /**
     * @param array<int, string> $conflictPaths
     */
    private function buildRevertMessage(Commit $commit, array $conflictPaths): string
    {
        return "Revert \"{$this->subjectLine($commit->message)}\"\n\n"
            . "This reverts commit {$commit->id->hex}.\n\n"
            . $this->formatConflictComments($conflictPaths);
    }

    /**
     * @param array<int, string> $conflictPaths
     */
    private function buildRebaseMessage(Commit $commit, array $conflictPaths): string
    {
        return rtrim($commit->message, "\n") . "\n\n" . $this->formatConflictComments($conflictPaths);
    }

    /**
     * @param array<int, string> $conflictPaths
     */
    private function formatConflictComments(array $conflictPaths): string
    {
        $lines = ["# Conflicts:"];

        foreach ($conflictPaths as $path) {
            $lines[] = "#\t{$path}";
        }

        return implode("\n", $lines) . "\n";
    }

    private function cherryPickConflictLabel(Commit $commit): string
    {
        return substr($commit->id->hex, 0, 7) . ' (' . $this->subjectLine($commit->message) . ')';
    }

    private function revertConflictLabel(Commit $commit): string
    {
        return 'parent of ' . substr($commit->id->hex, 0, 7) . ' (' . $this->subjectLine($commit->message) . ')';
    }

    private function rebaseConflictLabel(Commit $commit): string
    {
        return substr($commit->id->hex, 0, 7) . ' (' . $this->subjectLine($commit->message) . ')';
    }

    /**
     * @return array<int, Commit>
     */
    private function rebaseCommitsToReplay(ObjectId $headId, ObjectId $baseId): array
    {
        $commits = [];
        $currentId = $headId;

        while (!$currentId->equals($baseId)) {
            $commit = $this->objects->read($currentId);

            if (!$commit instanceof Commit) {
                throw new \RuntimeException("Cannot rebase: invalid commit {$currentId->hex}");
            }

            if (count($commit->parents) > 1) {
                throw new \RuntimeException('Cannot rebase merge commits yet');
            }

            if ($commit->parents === []) {
                throw new \RuntimeException('Cannot rebase: commit history does not reach the merge base');
            }

            $commits[] = $commit;
            $currentId = $commit->parents[0];
        }

        return array_reverse($commits);
    }

    /**
     * @return array{success: bool, commits: int, conflicts: array<int, string>}
     */
    private function continueRebaseSequence(): array
    {
        $state = $this->readRebaseState();

        if ($state === null) {
            throw new \RuntimeException('Cannot continue: no rebase in progress');
        }

        $applied = 0;

        while ($state['current'] < count($state['commits'])) {
            $headId = $this->refs->resolveHead();
            $headCommit = $headId !== null ? $this->objects->read($headId) : null;
            $replayId = $state['commits'][$state['current']];
            $commit = $this->objects->read($replayId);

            if (!$headCommit instanceof Commit || !$commit instanceof Commit) {
                throw new \RuntimeException('Cannot continue rebase: missing commit state');
            }

            $parentTree = $commit->parents !== []
                ? $this->getCommitTree($commit->parents[0])
                : null;
            $trackedPaths = array_values(array_unique(array_merge(
                $this->index()->paths(),
                array_keys($this->flattenTree($headCommit->tree)),
                array_keys($this->flattenTree($commit->tree)),
                array_keys($this->flattenTree($parentTree)),
            )));
            $merge = $this->mergeTreeEntries(
                $parentTree,
                $headCommit->tree,
                $commit->tree,
                'HEAD',
                $this->rebaseConflictLabel($commit),
                $commit->parents !== [] ? substr($commit->parents[0]->hex, 0, 7) : 'base',
            );

            if ($merge['conflictPaths'] !== []) {
                $message = $this->buildRebaseMessage($commit, $merge['conflictPaths']);
                $this->writeOperationConflictState(
                    $merge['mergedEntries'],
                    $merge['conflictEntries'],
                    $merge['conflictContents'],
                    $trackedPaths,
                    'REBASE_HEAD',
                    $commit->id,
                    $message,
                    $state['origHead'],
                );
                $this->writeRebaseState($state, $commit, $message);

                return [
                    'success' => false,
                    'commits' => $applied,
                    'conflicts' => $merge['conflictPaths'],
                ];
            }

            $treeId = $this->buildTreeFromEntries($merge['mergedEntries']);

            if ($headCommit->tree->equals($treeId)) {
                $state['current']++;
                $this->writeRebaseState($state);
                continue;
            }

            $commitId = $this->createCommitFromTree($treeId, $commit->message, [$headId], $commit->author);
            $this->moveDetachedHeadTo($commitId, 'rebase (pick): ' . $this->subjectLine($commit->message));
            $this->resetWorktree($commitId, $trackedPaths);
            $state['current']++;
            $this->writeRebaseState($state);
            $applied++;
        }

        $this->finishRebase($state);

        return ['success' => true, 'commits' => $applied, 'conflicts' => []];
    }

    /**
     * @param array{
     *   headName: string,
     *   origHead: ObjectId,
     *   onto: ObjectId,
     *   current: int,
     *   commits: array<int, ObjectId>
     * } $state
     */
    private function writeRebaseState(array $state, ?Commit $stoppedCommit = null, ?string $message = null): void
    {
        $dir = $this->gitDir . '/rebase-merge';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $json = [
            'headName' => $state['headName'],
            'origHead' => $state['origHead']->hex,
            'onto' => $state['onto']->hex,
            'current' => $state['current'],
            'commits' => array_map(static fn (ObjectId $id): string => $id->hex, $state['commits']),
        ];
        $todoLines = array_map(function (ObjectId $id): string {
            $commit = $this->objects->read($id);

            if (!$commit instanceof Commit) {
                throw new \RuntimeException("Cannot write rebase state: missing commit {$id->hex}");
            }

            return $this->rebaseTodoLine($commit);
        }, $state['commits']);
        $doneCount = $stoppedCommit !== null ? $state['current'] + 1 : $state['current'];
        $todoStart = $stoppedCommit !== null ? $state['current'] + 1 : $state['current'];

        file_put_contents($dir . '/pitmaster-state.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        file_put_contents($dir . '/head-name', $state['headName'] . "\n");
        file_put_contents($dir . '/onto', $state['onto']->hex . "\n");
        file_put_contents($dir . '/orig-head', $state['origHead']->hex . "\n");
        file_put_contents($dir . '/end', count($state['commits']) . "\n");
        file_put_contents($dir . '/git-rebase-todo.backup', $this->joinRebaseLines($todoLines));
        file_put_contents($dir . '/done', $this->joinRebaseLines(array_slice($todoLines, 0, $doneCount)));
        file_put_contents($dir . '/git-rebase-todo', $this->joinRebaseLines(array_slice($todoLines, $todoStart)));
        file_put_contents($dir . '/interactive', '');
        file_put_contents($dir . '/no-reschedule-failed-exec', '');
        file_put_contents($dir . '/drop_redundant_commits', '');

        if ($stoppedCommit !== null) {
            file_put_contents($dir . '/msgnum', (string) ($state['current'] + 1) . "\n");
            file_put_contents($dir . '/stopped-sha', $stoppedCommit->id->hex . "\n");
            file_put_contents($dir . '/message', $message ?? rtrim($stoppedCommit->message, "\n") . "\n");
            file_put_contents($dir . '/patch', $this->buildRebasePatch($stoppedCommit));
            file_put_contents($dir . '/author-script', $this->buildAuthorScript($stoppedCommit->author));

            return;
        }

        foreach (['msgnum', 'stopped-sha', 'message', 'patch', 'author-script'] as $file) {
            @unlink($dir . '/' . $file);
        }
    }

    /**
     * @return array{
     *   headName: string,
     *   origHead: ObjectId,
     *   onto: ObjectId,
     *   current: int,
     *   commits: array<int, ObjectId>
     * }|null
     */
    private function readRebaseState(): ?array
    {
        $path = $this->gitDir . '/rebase-merge/pitmaster-state.json';

        if (!is_file($path)) {
            return null;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return null;
        }

        $state = json_decode($content, true);

        if (!is_array($state)) {
            return null;
        }

        $commits = [];

        foreach (($state['commits'] ?? []) as $hex) {
            if (!is_string($hex)) {
                return null;
            }

            $commits[] = ObjectId::fromHex($hex);
        }

        if (!is_string($state['headName'] ?? null) || !is_string($state['origHead'] ?? null) || !is_string($state['onto'] ?? null)) {
            return null;
        }

        return [
            'headName' => $state['headName'],
            'origHead' => ObjectId::fromHex($state['origHead']),
            'onto' => ObjectId::fromHex($state['onto']),
            'current' => (int) ($state['current'] ?? 0),
            'commits' => $commits,
        ];
    }

    private function advanceRebaseState(): void
    {
        $state = $this->readRebaseState();

        if ($state === null) {
            throw new \RuntimeException('Cannot advance rebase: no rebase in progress');
        }

        $state['current']++;
        $this->writeRebaseState($state);
    }

    /**
     * @param array{
     *   headName: string,
     *   origHead: ObjectId,
     *   onto: ObjectId,
     *   current: int,
     *   commits: array<int, ObjectId>
     * } $state
     */
    private function finishRebase(array $state): void
    {
        $headId = $this->refs->resolveHead();
        $oldBranchId = $this->refs->resolve($state['headName']);

        if ($headId === null) {
            throw new \RuntimeException('Cannot finish rebase: HEAD is not set');
        }

        $this->refs->update($state['headName'], $headId);
        $this->appendReflogEntry(
            $state['headName'],
            $oldBranchId ?? $state['origHead'],
            $headId,
            'rebase (finish): ' . $state['headName'] . ' onto ' . $state['onto']->hex,
        );
        $this->refs->updateSymbolic('HEAD', $state['headName']);
        $this->appendReflogEntry(
            'HEAD',
            $headId,
            $headId,
            'rebase (finish): returning to ' . $state['headName'],
        );
        $this->clearOperationState($this->refs->resolve('REBASE_HEAD') !== null ? ['REBASE_HEAD'] : []);
        $this->clearRebaseState();
    }

    private function clearRebaseState(): void
    {
        $dir = $this->gitDir . '/rebase-merge';

        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $path) {
            if ($path->isDir()) {
                rmdir($path->getPathname());
                continue;
            }

            unlink($path->getPathname());
        }

        rmdir($dir);
    }

    private function rebaseTodoLine(Commit $commit): string
    {
        return "pick {$commit->id->hex} # {$this->subjectLine($commit->message)}";
    }

    /**
     * @param array<int, string> $lines
     */
    private function joinRebaseLines(array $lines): string
    {
        return $lines === [] ? '' : implode("\n", $lines) . "\n";
    }

    private function buildRebasePatch(Commit $commit): string
    {
        $diffs = (new TreeDiff($this->objects))->diff(
            $commit->parents !== [] ? $this->getCommitTree($commit->parents[0]) : null,
            $commit->tree,
        );
        $parts = [];

        foreach ($diffs as $diff) {
            $parts[] = rtrim($diff->format(), "\n");
        }

        return $parts === [] ? '' : implode("\n", $parts) . "\n";
    }

    private function buildAuthorScript(string $author): string
    {
        if (preg_match('/^(.*) <([^>]+)> (\\d+) ([+-]\\d{4})$/', $author, $matches) === 1) {
            return "GIT_AUTHOR_NAME='{$matches[1]}'\n"
                . "GIT_AUTHOR_EMAIL='{$matches[2]}'\n"
                . "GIT_AUTHOR_DATE='@{$matches[3]} {$matches[4]}'\n";
        }

        return '';
    }

    /**
     * @param array<int, array{old: ObjectId, new: ObjectId, ref: string}> $updates
     * @param array<int, string> $extraCapabilities
     */
    private function pushUpdates(
        string $remote,
        SmartHttpClient $http,
        string $url,
        \Pitmaster\Protocol\RefDiscovery $discovery,
        array $updates,
        array $extraCapabilities = [],
    ): void {
        if ($updates === []) {
            return;
        }

        $this->runPrePushHook($remote, $url, $updates);

        $packData = $this->buildPushPackDataForUpdates($updates, $discovery->refs());
        $receivePack = new \Pitmaster\Protocol\ReceivePackClient($http);
        $receivePack->push($url, $updates, $packData, $this->pushCapabilities($discovery, $extraCapabilities));
    }

    /**
     * @param array<int, array{old: ObjectId, new: ObjectId, ref: string}> $updates
     * @param array<string, ObjectId> $remoteRefs
     */
    private function buildPushPackDataForUpdates(array $updates, array $remoteRefs): string
    {
        $objects = [];

        foreach ($updates as $update) {
            if ($update['new']->equals($this->zeroObjectId())) {
                continue;
            }

            $this->collectReachableObjects($update['new'], $objects);
        }

        $excluded = [];

        foreach ($remoteRefs as $remoteId) {
            if ($this->objects->exists($remoteId)) {
                $this->collectReachableObjects($remoteId, $excluded);
            }
        }

        foreach (array_keys($excluded) as $hex) {
            unset($objects[$hex]);
        }

        return PackWriter::encode(array_values($objects));
    }

    /**
     * @param array<string, GitObject> $objects
     */
    private function collectReachableObjects(ObjectId $start, array &$objects): void
    {
        $stack = [$start];

        while ($stack !== []) {
            $id = array_pop($stack);

            if (!$id instanceof ObjectId || isset($objects[$id->hex])) {
                continue;
            }

            $object = $this->objects->read($id);

            if ($object === null) {
                throw new \RuntimeException("Missing object required for push: {$id->hex}");
            }

            $objects[$id->hex] = $object;

            if ($object instanceof Commit) {
                $stack[] = $object->tree;

                foreach ($object->parents as $parent) {
                    $stack[] = $parent;
                }

                continue;
            }

            if ($object instanceof Tree) {
                foreach ($object->entries as $entry) {
                    $stack[] = $entry->hash;
                }

                continue;
            }

            if ($object instanceof Tag) {
                $stack[] = $object->object;
            }
        }
    }

    private function currentPushBranch(): string
    {
        $branch = $this->branch();

        if ($branch === null) {
            throw new \RuntimeException('Cannot push: not on a branch');
        }

        return $branch;
    }

    private function remoteUrl(string $remote): string
    {
        $url = $this->config->get("remote.{$remote}.url");

        if ($url === null) {
            throw new \RuntimeException("Remote not found: {$remote}");
        }

        return $url;
    }

    private function requireLocalRef(string $refName, string $message): ObjectId
    {
        $id = $this->refs->resolve($refName);

        if ($id === null) {
            throw new \RuntimeException($message);
        }

        return $id;
    }

    private function assertFastForwardPush(string $branch, ?ObjectId $remoteId, ObjectId $localId): void
    {
        if ($remoteId === null) {
            return;
        }

        $mergeBase = new MergeBase($this->objects);

        if (!$mergeBase->isAncestor($remoteId, $localId)) {
            throw new \RuntimeException("Push rejected: non-fast-forward update to {$branch}");
        }
    }

    /**
     * @param array<int, string> $extraCapabilities
     * @return array<int, string>
     */
    private function pushCapabilities(\Pitmaster\Protocol\RefDiscovery $discovery, array $extraCapabilities = []): array
    {
        $capabilities = array_values(array_unique(array_merge(
            \Pitmaster\Protocol\ProtocolV1::DEFAULT_PUSH_CAPABILITIES,
            $extraCapabilities,
        )));
        $advertised = $discovery->capabilities();

        if ($advertised === null) {
            return $capabilities;
        }

        $result = [];

        foreach ($capabilities as $capability) {
            $name = explode('=', $capability, 2)[0];

            if ($name === 'agent' || $advertised->has($name)) {
                $result[] = $capability;
            }
        }

        return $result;
    }

    private function isMirrorPushRef(string $refName): bool
    {
        return str_starts_with($refName, 'refs/heads/') || str_starts_with($refName, 'refs/tags/');
    }

    private function moveHeadTo(ObjectId $target, string $message): void
    {
        $oldHeadId = $this->refs->resolveHead();
        $head = $this->refs->readHead();

        if ($head !== null) {
            $this->refs->update($head->target, $target);
            $this->appendReflogEntry($head->target, $oldHeadId, $target, $message);
        } else {
            $this->refs->update('HEAD', $target);
        }

        $this->appendReflogEntry('HEAD', $oldHeadId, $target, $message);
    }

    private function detachHeadTo(ObjectId $target, string $message): void
    {
        $oldHeadId = $this->refs->resolveHead();
        $this->refs->looseStore()->update('HEAD', $target);
        $this->appendReflogEntry('HEAD', $oldHeadId, $target, $message);
    }

    private function moveDetachedHeadTo(ObjectId $target, string $message): void
    {
        $this->detachHeadTo($target, $message);
    }

    private function assertSafeCheckout(ObjectId $targetId): void
    {
        if ($this->isBare) {
            return;
        }

        $headId = $this->refs->resolveHead();
        $currentTree = $headId !== null ? $this->flattenTree($this->getCommitTree($headId)) : [];
        $targetTree = $this->flattenTree($this->getCommitTree($targetId));
        $pathsChanging = [];

        foreach (array_keys(array_merge($currentTree, $targetTree)) as $path) {
            if (($currentTree[$path] ?? null) !== ($targetTree[$path] ?? null)) {
                $pathsChanging[$path] = true;
            }
        }

        if ($pathsChanging === []) {
            return;
        }

        $modified = [];
        $untracked = [];

        foreach ($this->status() as $entry) {
            if ($entry->index === FileStatus::Ignored) {
                continue;
            }

            if ($entry->index === FileStatus::Untracked) {
                if (isset($targetTree[$entry->path])) {
                    $untracked[] = $entry->path;
                }

                continue;
            }

            if (!isset($pathsChanging[$entry->path])) {
                continue;
            }

            if ($entry->index !== FileStatus::Unmodified || $entry->worktree !== FileStatus::Unmodified) {
                $modified[] = $entry->path;
            }
        }

        if ($modified !== []) {
            sort($modified);

            throw new \RuntimeException(
                'Your local changes to the following files would be overwritten by checkout: ' . implode(', ', $modified)
            );
        }

        if ($untracked !== []) {
            sort($untracked);

            throw new \RuntimeException(
                'The following untracked working tree files would be overwritten by checkout: ' . implode(', ', $untracked)
            );
        }
    }

    /**
     * @param array{type?: string}|null $state
     */
    private function commitReflogMessage(?array $state, string $message): string
    {
        return match ($state['type'] ?? null) {
            'cherry-pick' => 'commit (cherry-pick): ' . $this->subjectLine($message),
            default => 'commit: ' . $this->subjectLine($message),
        };
    }

    private function appendReflogEntry(string $refName, ?ObjectId $oldId, ObjectId $newId, string $message): void
    {
        if (!$this->shouldWriteReflog($refName)) {
            return;
        }

        $logDir = $refName === 'HEAD' ? $this->gitDir : $this->commonDir;
        $this->appendReflogEntryAt($logDir, $refName, $oldId, $newId, $message);
    }

    private function appendReflogEntryAt(string $gitDir, string $refName, ?ObjectId $oldId, ObjectId $newId, string $message): void
    {
        $reflog = Reflog::open($gitDir, $refName);
        $reflog->append($oldId ?? $this->zeroObjectId(), $newId, $this->currentCommitterIdentity(), $message);
    }

    private function appendLinkedWorktreeHeadReflog(string $gitDir, ObjectId $headId): void
    {
        if (!$this->shouldWriteReflog('HEAD')) {
            return;
        }

        $zero = $this->zeroObjectId();
        $this->appendReflogEntryAt($gitDir, 'HEAD', $zero, $headId, '');
        $this->appendReflogEntryAt($gitDir, 'HEAD', $headId, $headId, 'reset: moving to HEAD');
    }

    private function shouldWriteReflog(string $refName): bool
    {
        if ($this->config->getBool('core.logallrefupdates')) {
            return true;
        }

        $path = ($refName === 'HEAD' ? $this->gitDir : $this->commonDir) . '/logs/' . $refName;

        return is_file($path);
    }

    private function deleteReflog(string $refName): void
    {
        $path = $this->commonDir . '/logs/' . $refName;

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function currentAuthorIdentity(): string
    {
        [$timestamp, $timezone] = $this->identityDateParts('author');

        return $this->formattedIdentity(
            $this->identityName('author'),
            $this->identityEmail('author'),
            $timestamp,
            $timezone,
        );
    }

    private function currentCommitterIdentity(): string
    {
        [$timestamp, $timezone] = $this->identityDateParts('committer');

        return $this->formattedIdentity(
            $this->identityName('committer'),
            $this->identityEmail('committer'),
            $timestamp,
            $timezone,
        );
    }

    private function zeroObjectId(): ObjectId
    {
        return ObjectId::fromHex(str_repeat('0', 40));
    }

    private function formattedIdentity(string $name, string $email, ?int $timestamp = null, ?string $timezone = null): string
    {
        if ($timestamp === null || $timezone === null) {
            [$timestamp, $timezone] = $this->identityDateParts('committer');
        }

        return "{$name} <{$email}> {$timestamp} {$timezone}";
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function identityDateParts(string $role): array
    {
        foreach ($this->identityDateEnvNames($role) as $env) {
            $value = getenv($env);

            if ($value === false || trim($value) === '') {
                continue;
            }

            return $this->parseIdentityDate($value);
        }

        return [time(), date('O')];
    }

    private function identityName(string $role): string
    {
        foreach ($this->identityNameEnvNames($role) as $env) {
            $value = getenv($env);

            if ($value !== false && trim($value) !== '') {
                return trim($value);
            }
        }

        foreach ($this->identityNameConstants($role) as $constant) {
            if (defined($constant) && trim((string) constant($constant)) !== '') {
                return trim((string) constant($constant));
            }
        }

        return $this->config->get('user.name') ?? 'Pitmaster';
    }

    private function identityEmail(string $role): string
    {
        foreach ($this->identityEmailEnvNames($role) as $env) {
            $value = getenv($env);

            if ($value !== false && trim($value) !== '') {
                return trim($value);
            }
        }

        foreach ($this->identityEmailConstants($role) as $constant) {
            if (defined($constant) && trim((string) constant($constant)) !== '') {
                return trim((string) constant($constant));
            }
        }

        return $this->config->get('user.email') ?? 'pitmaster@localhost';
    }

    /**
     * @return list<string>
     */
    private function identityNameEnvNames(string $role): array
    {
        return $role === 'author'
            ? ['PITMASTER_AUTHOR_NAME', 'GIT_AUTHOR_NAME']
            : ['PITMASTER_COMMITTER_NAME', 'GIT_COMMITTER_NAME'];
    }

    /**
     * @return list<string>
     */
    private function identityEmailEnvNames(string $role): array
    {
        return $role === 'author'
            ? ['PITMASTER_AUTHOR_EMAIL', 'GIT_AUTHOR_EMAIL']
            : ['PITMASTER_COMMITTER_EMAIL', 'GIT_COMMITTER_EMAIL'];
    }

    /**
     * @return list<string>
     */
    private function identityDateEnvNames(string $role): array
    {
        return $role === 'author'
            ? ['PITMASTER_AUTHOR_DATE', 'GIT_AUTHOR_DATE']
            : ['PITMASTER_COMMITTER_DATE', 'GIT_COMMITTER_DATE'];
    }

    /**
     * @return list<string>
     */
    private function identityNameConstants(string $role): array
    {
        return $role === 'author'
            ? ['PITMASTER_AUTHOR_NAME']
            : ['PITMASTER_COMMITTER_NAME', 'PITMASTER_AUTHOR_NAME'];
    }

    /**
     * @return list<string>
     */
    private function identityEmailConstants(string $role): array
    {
        return $role === 'author'
            ? ['PITMASTER_AUTHOR_EMAIL']
            : ['PITMASTER_COMMITTER_EMAIL', 'PITMASTER_AUTHOR_EMAIL'];
    }

    /**
     * @param array{message?: string, type?: string}|null $state
     */
    private function prepareCommitMessage(string $message, ?array $state): string
    {
        $hooks = new HookRunner($this->gitDir);

        if (!$hooks->runAndCheck('pre-commit')) {
            throw new \RuntimeException('pre-commit hook failed');
        }

        $messageFile = $this->gitDir . '/COMMIT_EDITMSG';
        file_put_contents($messageFile, $message);

        [$source, $sourceArg] = $this->commitMessageHookSource($state);
        $prepareArgs = [$messageFile];

        if ($source !== null) {
            $prepareArgs[] = $source;
        }

        if ($sourceArg !== null) {
            $prepareArgs[] = $sourceArg;
        }

        if (!$hooks->runAndCheck('prepare-commit-msg', $prepareArgs)) {
            throw new \RuntimeException('prepare-commit-msg hook failed');
        }

        $preparedMessage = (string) file_get_contents($messageFile);

        if (!$hooks->runAndCheck('commit-msg', [$messageFile])) {
            throw new \RuntimeException('commit-msg hook failed');
        }

        $finalMessage = (string) file_get_contents($messageFile);

        return $finalMessage !== '' ? $finalMessage : $preparedMessage;
    }

    /**
     * @param array{type?: string}|null $state
     * @return array{0: ?string, 1: ?string}
     */
    private function commitMessageHookSource(?array $state): array
    {
        if ($state === null) {
            return ['message', null];
        }

        return match ($state['type'] ?? null) {
            'merge' => ['merge', null],
            default => ['message', null],
        };
    }

    private function runPostCommitHook(): void
    {
        (new HookRunner($this->gitDir))->run('post-commit');
    }

    private function runPostCheckoutHook(?ObjectId $oldHeadId, ObjectId $newHeadId): void
    {
        (new HookRunner($this->gitDir))->run('post-checkout', [
            ($oldHeadId ?? $this->zeroObjectId())->hex,
            $newHeadId->hex,
            '1',
        ]);
    }

    private function runPostMergeHook(): void
    {
        (new HookRunner($this->gitDir))->run('post-merge', ['0']);
    }

    private function runPreRebaseHook(string $onto, SymbolicRef $head): void
    {
        if (!(new HookRunner($this->gitDir))->runAndCheck('pre-rebase', [$onto])) {
            throw new \RuntimeException('pre-rebase hook failed');
        }
    }

    /**
     * @param array<int, array{old: ObjectId, new: ObjectId, ref: string}> $updates
     */
    private function runPrePushHook(string $remote, string $url, array $updates): void
    {
        $stdin = [];

        foreach ($updates as $update) {
            $stdin[] = sprintf(
                '%s %s %s %s',
                $update['new']->equals($this->zeroObjectId()) ? '(delete)' : $update['ref'],
                $update['new']->hex,
                $update['ref'],
                $update['old']->hex,
            );
        }

        $input = $stdin === [] ? null : implode("\n", $stdin) . "\n";

        if (!(new HookRunner($this->gitDir))->runAndCheck('pre-push', [$remote, $url], $input)) {
            throw new \RuntimeException('pre-push hook failed');
        }
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function parseIdentityDate(string $value): array
    {
        if (preg_match('/^@?(\\d+) ([+-]\\d{4})$/', trim($value), $matches) === 1) {
            return [(int) $matches[1], $matches[2]];
        }

        try {
            $date = new \DateTimeImmutable($value);
        } catch (\Exception) {
            return [time(), date('O')];
        }

        return [$date->getTimestamp(), $date->format('O')];
    }

    private function subjectLine(string $message): string
    {
        $message = trim($message);

        if ($message === '') {
            return '(empty message)';
        }

        $lines = preg_split("/\r\n|\n|\r/", $message);

        return trim((string) ($lines[0] ?? $message));
    }

    private function currentLocationLabel(?ObjectId $headId): string
    {
        $branch = $this->branch();

        if ($branch !== null) {
            return $branch;
        }

        return $headId !== null ? $headId->hex : substr($this->zeroObjectId()->hex, 0, 7);
    }

    private function targetLabel(string $target, ObjectId $resolvedId): string
    {
        if ($target === $resolvedId->hex) {
            return $resolvedId->hex;
        }

        return $target;
    }

    private function isPackableRef(string $name): bool
    {
        if (!str_starts_with($name, 'refs/')) {
            return false;
        }

        return !str_starts_with($name, 'refs/bisect/')
            && !str_starts_with($name, 'refs/worktree/');
    }

    private function isCommonSymbolicRef(string $name): bool
    {
        $path = $this->commonDir . '/' . $name;

        if (!is_file($path)) {
            return false;
        }

        $content = file_get_contents($path);

        return $content !== false && str_starts_with(trim($content), 'ref: ');
    }

    private function isPerWorktreeRef(string $name): bool
    {
        if ($this->gitDir === $this->commonDir) {
            return false;
        }

        $perWorktree = $this->gitDir . '/' . $name;
        $shared = $this->commonDir . '/' . $name;

        return is_file($perWorktree) && !is_file($shared);
    }

    private function peelRefTarget(ObjectId $id): ?ObjectId
    {
        $current = $id;

        while (true) {
            $object = $this->objects->read($current);

            if (!$object instanceof Tag) {
                return $current->equals($id) ? null : $current;
            }

            $current = $object->object;
        }
    }

    private function deleteLooseCommonRef(string $name): void
    {
        $path = $this->commonDir . '/' . $name;

        if (!is_file($path)) {
            return;
        }

        unlink($path);
        $this->removeEmptyRefDirectories(dirname($path), $this->commonDir . '/refs');
    }

    private function removeEmptyRefDirectories(string $dir, string $root): void
    {
        while ($dir !== $root && str_starts_with($dir, $root) && is_dir($dir)) {
            $entries = scandir($dir);

            if ($entries === false || $entries !== ['.', '..']) {
                return;
            }

            rmdir($dir);
            $dir = dirname($dir);
        }
    }
}
