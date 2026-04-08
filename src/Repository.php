<?php

declare(strict_types=1);

namespace Pitmaster;

use Pitmaster\Config\GitConfig;
use Pitmaster\Diff\DiffResult;
use Pitmaster\Diff\MyersDiff;
use Pitmaster\Diff\TreeDiff;
use Pitmaster\Exceptions\ObjectNotFoundException;
use Pitmaster\Graph\CommitWalker;
use Pitmaster\Graph\RevisionParser;
use Pitmaster\Index\Index;
use Pitmaster\Index\IndexEntry;
use Pitmaster\Index\IndexWriter;
use Pitmaster\Merge\ConflictMarker;
use Pitmaster\Merge\MergeBase;
use Pitmaster\Merge\MergeResult;
use Pitmaster\Merge\ThreeWayMerge;
use Pitmaster\Object\Blob;
use Pitmaster\Object\Commit;
use Pitmaster\Object\GitObject;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;
use Pitmaster\Object\Tag;
use Pitmaster\Object\Tree;
use Pitmaster\Object\TreeEntry;
use Pitmaster\Pack\PackIndexer;
use Pitmaster\Protocol\SmartHttpClient;
use Pitmaster\Protocol\UploadPackClient;
use Pitmaster\Ref\RefDatabase;
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

    public function __construct(string $path)
    {
        if (is_dir($path . '/.git')) {
            // Regular repo: .git is a directory
            $this->workDir = $path;
            $this->gitDir = $path . '/.git';
            $this->commonDir = $this->gitDir;
            $this->isLinkedWorktree = false;
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
            $this->workDir = dirname($path);
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

    // -- Default branch --

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

    // -- Worktree lifecycle --

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

        // Ensure the branch exists
        if ($this->refs->resolve("refs/heads/{$branch}") === null) {
            $target = $from ?? $this->refs->resolveHead();

            if ($target !== null) {
                $this->refs->update("refs/heads/{$branch}", $target);
            }
        }

        $wt = $manager->add($path, $branch, $name);

        // Materialize the working tree files
        $branchId = $this->refs->resolve("refs/heads/{$branch}");

        if ($branchId !== null) {
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

    // -- Objects --

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

    // -- Refs --

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
            if (str_starts_with($name, 'refs/tags/')) {
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

        $this->refs->update("refs/heads/{$name}", $target);
    }

    /**
     * Delete a branch.
     */
    public function deleteBranch(string $name): void
    {
        $this->refs->delete("refs/heads/{$name}");
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

        if ($tagger === null) {
            $taggerName = $this->config->get('user.name') ?? 'Pitmaster';
            $taggerEmail = $this->config->get('user.email') ?? 'pitmaster@localhost';
            $tagger = "{$taggerName} <{$taggerEmail}> " . time() . ' ' . date('O');
        }

        $content = "object {$target->hex}\n"
            . "type commit\n"
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
     * Checkout a branch or commit (update HEAD + worktree + index).
     */
    public function checkout(string $target): void
    {
        $trackedPaths = array_keys($this->index()->entries());

        // Try as branch first
        $branchId = $this->refs->resolve("refs/heads/{$target}");

        if ($branchId !== null) {
            // Switch to branch: update HEAD symbolic ref
            $this->refs->updateSymbolic('HEAD', "refs/heads/{$target}");
            $this->resetWorktree($branchId, $trackedPaths);

            return;
        }

        // Try as tag or direct hash (detached HEAD)
        $id = $this->resolve($target);
        $this->refs->looseStore()->update('HEAD', $id);
        $this->resetWorktree($id, $trackedPaths);
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

    // -- Log --

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
     * Show a commit: metadata + diff against parent.
     *
     * @return array{commit: Commit, diff: array<int, DiffResult>}
     */
    public function show(string $revision): array
    {
        $id = $this->resolve($revision);
        $object = $this->objects->read($id);

        if (!$object instanceof Commit) {
            throw new \RuntimeException("Not a commit: {$revision}");
        }

        $treeDiff = new TreeDiff($this->objects);
        $parentTree = $object->parents !== []
            ? $this->getCommitTree($object->parents[0])
            : null;

        $diffs = $treeDiff->diff($parentTree, $object->tree);

        return ['commit' => $object, 'diff' => $diffs];
    }

    // -- Index --

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
            $index->addEntry($entry);
        }

        IndexWriter::write($index, $this->gitDir . '/index');
    }

    /**
     * Move/rename a file in the index (git mv).
     */
    public function mv(string $source, string $destination): void
    {
        $srcFull = $this->workDir . '/' . $source;
        $dstFull = $this->workDir . '/' . $destination;

        if (!is_file($srcFull)) {
            throw new \RuntimeException("Source file not found: {$source}");
        }

        // Move in worktree
        $dstDir = dirname($dstFull);

        if (!is_dir($dstDir)) {
            mkdir($dstDir, 0777, true);
        }

        rename($srcFull, $dstFull);

        // Update index
        $this->remove($source);
        $this->add($destination);
    }

    /**
     * Unstage files (git rm --cached).
     */
    public function remove(string ...$paths): void
    {
        $index = $this->index();

        foreach ($paths as $path) {
            $index->removeEntry($path);
        }

        IndexWriter::write($index, $this->gitDir . '/index');
    }

    // -- Commits --

    /**
     * Create a commit from the current index.
     */
    public function commit(string $message, ?string $author = null): ObjectId
    {
        $index = $this->index();
        $treeId = $this->buildTreeFromIndex($index);
        $headId = $this->refs->resolveHead();

        if ($headId === null && $index->count() === 0) {
            throw new \RuntimeException('Nothing to commit: index is empty');
        }

        $headCommit = $headId !== null ? $this->objects->read($headId) : null;

        if ($headCommit instanceof Commit && $headCommit->tree->equals($treeId)) {
            throw new \RuntimeException('Nothing to commit: tree unchanged');
        }

        // Determine author/committer
        if ($author === null) {
            $name = $this->config->get('user.name')
                ?? (defined('PITMASTER_AUTHOR_NAME') ? constant('PITMASTER_AUTHOR_NAME') : 'Pitmaster');
            $email = $this->config->get('user.email')
                ?? (defined('PITMASTER_AUTHOR_EMAIL') ? constant('PITMASTER_AUTHOR_EMAIL') : 'pitmaster@localhost');
            $timestamp = time();
            $tz = date('O');
            $author = "{$name} <{$email}> {$timestamp} {$tz}";
        }

        // Build commit
        $parents = [];

        if ($headId !== null) {
            $parents[] = $headId;
        }

        $content = Commit::buildContent(
            tree: $treeId,
            parents: $parents,
            author: $author,
            committer: $author,
            message: $message,
        );

        $commitId = ObjectId::compute(ObjectType::Commit, $content);
        $commit = Commit::parse($content, $commitId);
        $this->objects->write($commit);

        // Update HEAD
        $head = $this->refs->readHead();

        if ($head !== null) {
            $this->refs->update($head->target, $commitId);
        } else {
            // Detached HEAD or first commit
            $this->refs->update('HEAD', $commitId);
        }

        return $commitId;
    }

    /**
     * Reset HEAD to a commit.
     *
     * @param string $mode 'soft' (HEAD only), 'mixed' (HEAD + index), 'hard' (HEAD + index + worktree)
     */
    public function reset(string $revision, string $mode = 'mixed'): void
    {
        $targetId = $this->resolve($revision);
        $trackedPaths = array_keys($this->index()->entries());

        // Move HEAD
        $head = $this->refs->readHead();

        if ($head !== null) {
            $this->refs->update($head->target, $targetId);
        } else {
            $this->refs->update('HEAD', $targetId);
        }

        if ($mode === 'soft') {
            return;
        }

        if ($mode === 'hard') {
            $this->resetWorktree($targetId, $trackedPaths);
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
    }

    /**
     * Restore a file from a tree/index (git restore).
     */
    public function restore(string $path, ?string $source = null): void
    {
        if ($source !== null) {
            // Restore from a specific commit
            $id = $this->resolve($source);
            $commit = $this->objects->read($id);

            if (!$commit instanceof Commit) {
                throw new \RuntimeException("Not a commit: {$source}");
            }

            $treeMap = $this->flattenTree($commit->tree);

            if (!isset($treeMap[$path])) {
                throw new \RuntimeException("File not in {$source}: {$path}");
            }

            $blob = $this->objects->read(ObjectId::fromHex($treeMap[$path]));

            if ($blob instanceof Blob) {
                file_put_contents($this->workDir . '/' . $path, $blob->content);
            }

            return;
        }

        // Restore from index
        $index = $this->index();
        $entry = $index->entry($path);

        if ($entry === null) {
            throw new \RuntimeException("File not in index: {$path}");
        }

        $blob = $this->objects->read($entry->hash);

        if ($blob instanceof Blob) {
            file_put_contents($this->workDir . '/' . $path, $blob->content);
        }
    }

    /**
     * Cherry-pick: apply a commit as a new commit on the current branch.
     */
    public function cherryPick(string $revision): ObjectId
    {
        $id = $this->resolve($revision);
        $commit = $this->objects->read($id);

        if (!$commit instanceof Commit) {
            throw new \RuntimeException("Not a commit: {$revision}");
        }

        // Get parent tree for diffing
        $parentTree = $commit->parents !== []
            ? $this->getCommitTree($commit->parents[0])
            : null;

        $treeDiff = new TreeDiff($this->objects);
        $changes = $treeDiff->diff($parentTree, $commit->tree);

        // Apply changes to current worktree and index
        $index = $this->index();

        foreach ($changes as $change) {
            $this->applyIndexedChange($index, $change);
        }

        IndexWriter::write($index, $this->gitDir . '/index');

        // Create the cherry-pick commit (preserving original author)
        return $this->commit($commit->message, $commit->author);
    }

    /**
     * Revert: create a commit that undoes another commit.
     */
    public function revert(string $revision): ObjectId
    {
        $id = $this->resolve($revision);
        $commit = $this->objects->read($id);

        if (!$commit instanceof Commit) {
            throw new \RuntimeException("Not a commit: {$revision}");
        }

        if ($commit->parents === []) {
            throw new \RuntimeException('Cannot revert a root commit');
        }

        // Get the inverse diff: diff from commit to its parent
        $treeDiff = new TreeDiff($this->objects);
        $parentTree = $this->getCommitTree($commit->parents[0]);
        $changes = $treeDiff->diff($commit->tree, $parentTree);

        // Apply inverse changes
        $index = $this->index();

        foreach ($changes as $change) {
            $this->applyIndexedChange($index, $change);
        }

        IndexWriter::write($index, $this->gitDir . '/index');

        $message = "Revert \"{$commit->message}\"\n\nThis reverts commit {$commit->id->hex}.\n";

        return $this->commit($message);
    }

    /**
     * Porcelain v2 status output.
     *
     * @return string Machine-readable status output
     */
    public function statusPorcelainV2(): string
    {
        $entries = $this->status();
        $lines = [];

        foreach ($entries as $entry) {
            if ($entry->index === FileStatus::Untracked) {
                $lines[] = "? {$entry->path}";
                continue;
            }

            if ($entry->index === FileStatus::Ignored) {
                $lines[] = "! {$entry->path}";
                continue;
            }

            $x = $entry->index->value;
            $y = $entry->worktree->value;
            $zero = str_repeat('0', 40);
            $lines[] = "1 {$x}{$y} N... 000000 000000 000000 {$zero} {$zero} {$entry->path}";
        }

        return implode("\n", $lines) . ($lines !== [] ? "\n" : '');
    }

    // -- Network --

    /**
     * Fetch from a remote.
     */
    public function fetch(string $remote = 'origin'): void
    {
        $url = $this->config->get("remote.{$remote}.url");

        if ($url === null) {
            throw new \RuntimeException("Remote not found: {$remote}");
        }

        $http = new SmartHttpClient();
        $discovery = $http->discoverRefs($url);
        $uploadPack = new UploadPackClient($http);

        // Determine what we need (all remote refs we don't have)
        $wants = [];
        $haves = [];

        foreach ($discovery->refs() as $refName => $refId) {
            if (!$this->objects->exists($refId)) {
                $wants[] = $refId;
            }
        }

        // Collect what we already have
        foreach ($this->refs->list() as $refName => $refId) {
            $haves[] = $refId;
        }

        if ($wants === []) {
            return; // Already up to date
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

        // Update remote tracking refs
        foreach ($discovery->refs() as $refName => $refId) {
            if (str_starts_with($refName, 'refs/heads/')) {
                $branch = substr($refName, 11);
                $this->refs->update("refs/remotes/{$remote}/{$branch}", $refId);
            } elseif (str_starts_with($refName, 'refs/tags/')) {
                $this->refs->update($refName, $refId);
            }
        }
    }

    /**
     * Push to a remote.
     */
    public function push(string $remote = 'origin', ?string $branch = null): void
    {
        $url = $this->config->get("remote.{$remote}.url");

        if ($url === null) {
            throw new \RuntimeException("Remote not found: {$remote}");
        }

        if ($branch === null) {
            $branch = $this->branch();

            if ($branch === null) {
                throw new \RuntimeException('Cannot push: not on a branch');
            }
        }

        $localRef = "refs/heads/{$branch}";
        $localId = $this->refs->resolve($localRef);

        if ($localId === null) {
            throw new \RuntimeException("Branch not found: {$branch}");
        }

        $http = new SmartHttpClient();
        $discovery = $http->discoverRefs($url);

        $remoteId = $discovery->ref($localRef);
        $oldId = $remoteId ?? ObjectId::fromHex(str_repeat('0', 40));

        $receivePack = new \Pitmaster\Protocol\ReceivePackClient($http);
        $receivePack->push($url, [
            ['old' => $oldId, 'new' => $localId, 'ref' => $localRef],
        ], '');
    }

    // -- Status --

    /**
     * Compute working tree status.
     *
     * @return array<int, StatusEntry>
     */
    public function status(): array
    {
        $index = $this->index();
        $headId = $this->refs->resolveHead();
        $status = new WorkingTreeStatus($this->objects, $this->workDir);

        return $status->compute($index, $headId);
    }

    // -- Diff --

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

    // -- Merge --

    /**
     * Merge a branch into HEAD.
     */
    public function merge(string $branch): MergeResult
    {
        $theirsId = $this->resolve($branch);
        $oursId = $this->refs->resolveHead();
        $trackedPaths = array_keys($this->index()->entries());

        if ($oursId === null) {
            throw new \RuntimeException('Cannot merge: HEAD is not set');
        }

        // Find merge base
        $mergeBaseFinder = new MergeBase($this->objects);
        $baseId = $mergeBaseFinder->find($oursId, $theirsId);

        // Fast-forward check
        if ($baseId !== null && $baseId->equals($oursId)) {
            // Fast-forward: just move HEAD
            $head = $this->refs->readHead();

            if ($head !== null) {
                $this->refs->update($head->target, $theirsId);
            }

            $this->resetWorktree($theirsId, $trackedPaths);

            return new MergeResult(clean: true, commitId: $theirsId);
        }

        $oursCommit = $this->objects->read($oursId);
        $theirsCommit = $this->objects->read($theirsId);

        if (!$oursCommit instanceof Commit || !$theirsCommit instanceof Commit) {
            throw new \RuntimeException('Cannot merge: invalid commit objects');
        }

        $baseEntries = $baseId !== null ? $this->flattenTreeEntries($this->getCommitTree($baseId)) : [];
        $oursEntries = $this->flattenTreeEntries($oursCommit->tree);
        $theirsEntries = $this->flattenTreeEntries($theirsCommit->tree);
        $allPaths = array_unique(array_merge(array_keys($baseEntries), array_keys($oursEntries), array_keys($theirsEntries)));
        sort($allPaths);

        $mergedEntries = [];
        $mergedContents = [];
        $conflicts = [];

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
                $conflicts[] = $path;
                $mergedContents[$path] = ConflictMarker::mark(
                    $oursHash !== null ? $this->readBlobContent(ObjectId::fromHex($oursHash)) : '',
                    $theirsHash !== null ? $this->readBlobContent(ObjectId::fromHex($theirsHash)) : '',
                );
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
                $conflicts[] = $path;
                $mergedContents[$path] = ConflictMarker::mark($oursContent, $theirsContent);
                continue;
            }

            $merge = ThreeWayMerge::merge($baseContent, $oursContent, $theirsContent);

            if (!$merge['clean']) {
                $conflicts[] = $path;
                $mergedContents[$path] = $merge['content'];
                continue;
            }

            $blob = Blob::fromContent($merge['content']);
            $this->objects->write($blob);
            $mergedEntries[$path] = [
                'hash' => $blob->id->hex,
                'mode' => $ours['mode'],
            ];
        }

        if ($conflicts !== []) {
            $this->materializeConflictContents($mergedContents);

            return new MergeResult(clean: false, conflictPaths: $conflicts, mergedContents: $mergedContents);
        }

        $treeId = $this->buildTreeFromEntries($mergedEntries);
        $name = $this->config->get('user.name') ?? 'Pitmaster';
        $email = $this->config->get('user.email') ?? 'pitmaster@localhost';
        $timestamp = time();
        $tz = date('O');
        $author = "{$name} <{$email}> {$timestamp} {$tz}";

        $content = Commit::buildContent(
            tree: $treeId,
            parents: [$oursId, $theirsId],
            author: $author,
            committer: $author,
            message: "Merge branch '{$branch}'\n",
        );

        $commitId = ObjectId::compute(ObjectType::Commit, $content);
        $commit = Commit::parse($content, $commitId);
        $this->objects->write($commit);

        $head = $this->refs->readHead();

        if ($head !== null) {
            $this->refs->update($head->target, $commitId);
        }

        $this->resetWorktree($commitId, $trackedPaths);

        return new MergeResult(clean: true, commitId: $commitId);
    }

    /**
     * Find the merge base of two commits.
     */
    public function mergeBase(ObjectId $a, ObjectId $b): ?ObjectId
    {
        return (new MergeBase($this->objects))->find($a, $b);
    }

    // -- Config --

    public function config(): GitConfig
    {
        return $this->config;
    }

    // -- Internal access --

    public function objectDatabase(): ObjectDatabase
    {
        return $this->objects;
    }

    public function refDatabase(): RefDatabase
    {
        return $this->refs;
    }

    // -- Private helpers --

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
    private function resetWorktree(ObjectId $commitId, array $pathsToPrune = []): void
    {
        $commit = $this->objects->read($commitId);

        if (!$commit instanceof Commit) {
            return;
        }

        $treeMap = $this->flattenTree($commit->tree);
        $this->materializeTreeMap($treeMap, $this->workDir, $pathsToPrune);
        $index = new Index();

        foreach ($treeMap as $path => $hash) {
            $blob = $this->objects->read(ObjectId::fromHex($hash));

            if (!$blob instanceof Blob) {
                continue;
            }

            $fullPath = $this->workDir . '/' . $path;
            $entry = IndexEntry::fromStat($path, $blob->id, $fullPath);
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
        $this->pruneMissingPaths($treeMap, $targetDir, $pathsToPrune);

        foreach ($treeMap as $path => $hash) {
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

    private function buildTreeFromEntries(array $entries): ObjectId
    {
        $index = new Index();

        foreach ($entries as $path => $entry) {
            $index->addEntry(IndexEntry::create($path, ObjectId::fromHex($entry['hash']), $entry['mode']));
        }

        return $this->buildTreeFromIndex($index);
    }

    private function applyIndexedChange(Index $index, DiffResult $change): void
    {
        if ($change->newHash === null) {
            $path = $change->oldPath;
            $fullPath = $this->workDir . '/' . $path;

            if (is_file($fullPath) || is_link($fullPath)) {
                unlink($fullPath);
                $this->removeEmptyParentDirectories(dirname($fullPath), $this->workDir);
            }

            $index->removeEntry($path);

            return;
        }

        $blob = $this->objects->read(ObjectId::fromHex($change->newHash));

        if (!$blob instanceof Blob) {
            return;
        }

        $path = $change->newPath;
        $fullPath = $this->workDir . '/' . $path;
        $dir = dirname($fullPath);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($fullPath, $blob->content);
        $index->addEntry(IndexEntry::fromStat($path, $blob->id, $fullPath));
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
}
