<?php

declare(strict_types=1);

namespace Pitmaster\Stash;

use Pitmaster\Config\GitConfig;
use Pitmaster\Exceptions\MergeConflictException;
use Pitmaster\Index\Index;
use Pitmaster\Index\IndexEntry;
use Pitmaster\Index\IndexWriter;
use Pitmaster\Merge\ThreeWayMerge;
use Pitmaster\Object\Blob;
use Pitmaster\Object\Commit;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;
use Pitmaster\Object\Tree;
use Pitmaster\Object\TreeEntry;
use Pitmaster\Ref\RefDatabase;
use Pitmaster\Ref\Reflog;
use Pitmaster\Status\FileStatus;
use Pitmaster\Status\WorkingTreeStatus;
use Pitmaster\Storage\ObjectDatabase;

/**
 * Git stash: save and restore working directory state.
 *
 * Stash entries are stored as commits under refs/stash.
 * The stash "stack" is the reflog of refs/stash.
 * Each stash commit has two parents:
 *   - parent[0] = HEAD at time of stash
 *   - parent[1] = index state (tree commit)
 * The stash commit's own tree = worktree state.
 */
final class Stash
{
    public function __construct(
        private readonly ObjectDatabase $objects,
        private readonly RefDatabase $refs,
        private readonly string $gitDir,
        private readonly string $workDir,
    ) {
    }

    /**
     * Save current working state to the stash.
     *
     * @return ObjectId The stash commit ID
     */
    public function push(string $message = '', bool $includeUntracked = false): ObjectId
    {
        $headId = $this->refs->resolveHead();

        if ($headId === null) {
            throw new \RuntimeException('Cannot stash: HEAD is not set');
        }

        $headCommit = $this->objects->read($headId);

        if (!$headCommit instanceof Commit) {
            throw new \RuntimeException('Cannot stash: HEAD is not a commit');
        }

        $branch = $this->currentBranch();
        $firstLine = strtok($headCommit->message, "\n");
        $headSummary = substr($headId->hex, 0, 7) . ' ' . trim($firstLine === false ? '' : $firstLine);
        $stashMessage = $message !== ''
            ? "On {$branch}: {$message}"
            : "WIP on {$branch}: {$headSummary}";
        $indexMessage = "index on {$branch}: {$headSummary}";
        $identity = $this->currentIdentity();

        // Build tree from current index (staged state)
        $index = Index::open($this->gitDir . '/index', $this->hashBytes());
        $indexTreeId = $this->buildTreeFromIndex($index);

        // Create index commit
        $indexContent = Commit::buildContent(
            tree: $indexTreeId,
            parents: [$headId],
            author: $identity,
            committer: $identity,
            message: $indexMessage . "\n",
        );
        $indexCommitId = ObjectId::compute(ObjectType::Commit, $indexContent, $this->hashAlgo());
        $indexCommit = Commit::parse($indexContent, $indexCommitId);
        $this->objects->write($indexCommit);

        // Build tree from worktree (including unstaged changes)
        $dirtyPaths = [];
        $includedUntrackedPaths = [];
        $worktreeTreeId = $this->buildTreeFromWorktree(
            $index,
            $headId,
            $includeUntracked,
            $dirtyPaths,
            $includedUntrackedPaths,
        );

        // Create stash commit (worktree state, parents = HEAD + index commit)
        $stashContent = Commit::buildContent(
            tree: $worktreeTreeId,
            parents: [$headId, $indexCommitId],
            author: $identity,
            committer: $identity,
            message: $stashMessage . "\n",
        );
        $stashId = ObjectId::compute(ObjectType::Commit, $stashContent, $this->hashAlgo());
        $stashCommit = Commit::parse($stashContent, $stashId);
        $this->objects->write($stashCommit);

        // Update refs/stash (with reflog for stack)
        $oldStash = $this->refs->resolve('refs/stash');
        $this->refs->update('refs/stash', $stashId);

        $reflog = Reflog::open($this->gitDir, 'refs/stash');
        $reflog->append(
            $oldStash ?? ObjectId::zero($this->hashAlgo()),
            $stashId,
            $identity,
            $stashMessage,
        );

        // Reset worktree to HEAD
        $this->resetToHead($headId, $dirtyPaths, $includedUntrackedPaths);

        return $stashId;
    }

    /**
     * Apply the top stash entry (or a specific one) without removing it.
     */
    public function apply(int $index = 0): void
    {
        $stashId = $this->getStashEntry($index);
        $stash = $this->objects->read($stashId);

        if (!$stash instanceof Commit) {
            throw new \RuntimeException('Invalid stash entry');
        }

        $this->applyStashCommit($stash);
    }

    /**
     * Pop the top stash entry: apply and remove.
     */
    public function pop(int $index = 0): void
    {
        $this->apply($index);
        $this->drop($index);
    }

    /**
     * Drop a stash entry.
     */
    public function drop(int $index = 0): void
    {
        $reflog = Reflog::open($this->gitDir, 'refs/stash');
        $entries = $reflog->entries();

        if ($index >= count($entries)) {
            throw new \RuntimeException("No stash entry at index {$index}");
        }

        // For simplicity, if dropping the only entry, remove refs/stash
        if (count($entries) <= 1) {
            $this->refs->delete('refs/stash');
            $logPath = $this->gitDir . '/logs/refs/stash';

            if (is_file($logPath)) {
                unlink($logPath);
            }

            return;
        }

        // Remove entry from reflog (rebuild without the dropped entry)
        $newEntries = $entries;
        array_splice($newEntries, count($newEntries) - 1 - $index, 1);

        // Update refs/stash to point to the new top
        $top = end($newEntries);

        if ($top !== false) {
            $this->refs->update('refs/stash', ObjectId::fromHex($top['new']));
        }
    }

    /**
     * List all stash entries.
     *
     * @return array<int, array{index: int, message: string, hash: string}>
     */
    public function listEntries(): array
    {
        $reflog = Reflog::open($this->gitDir, 'refs/stash');
        $entries = $reflog->entries();
        $result = [];

        // Reflog is chronological; stash shows newest first
        $reversed = array_reverse($entries);

        foreach ($reversed as $i => $entry) {
            $result[] = [
                'index' => $i,
                'message' => $entry['message'],
                'hash' => $entry['new'],
            ];
        }

        return $result;
    }

    private function getStashEntry(int $index): ObjectId
    {
        $reflog = Reflog::open($this->gitDir, 'refs/stash');
        $entries = $reflog->entries();
        $reversed = array_reverse($entries);

        if ($index >= count($reversed)) {
            throw new \RuntimeException("No stash entry at index {$index}");
        }

        return ObjectId::fromHex($reversed[$index]['new']);
    }

    private function currentBranch(): string
    {
        $head = $this->refs->readHead();

        if ($head !== null && str_starts_with($head->target, 'refs/heads/')) {
            return substr($head->target, 11);
        }

        return 'HEAD';
    }

    private function currentIdentity(): string
    {
        $config = GitConfig::fromFile($this->gitDir . '/config');
        $name = getenv('GIT_COMMITTER_NAME')
            ?: getenv('GIT_AUTHOR_NAME')
            ?: $config->get('user.name')
            ?: 'Pitmaster';
        $email = getenv('GIT_COMMITTER_EMAIL')
            ?: getenv('GIT_AUTHOR_EMAIL')
            ?: $config->get('user.email')
            ?: 'pitmaster@example.invalid';
        $date = getenv('GIT_COMMITTER_DATE')
            ?: getenv('GIT_AUTHOR_DATE')
            ?: sprintf('%d %s', time(), date('O'));

        return "{$name} <{$email}> {$this->normalizeDate($date)}";
    }

    private function normalizeDate(string $date): string
    {
        if (preg_match('/^@?(\d+)\s+([+-]\d{4})$/', $date, $matches) === 1) {
            return $matches[1] . ' ' . $matches[2];
        }

        try {
            $parsed = new \DateTimeImmutable($date);
        } catch (\Exception) {
            return sprintf('%d %s', time(), date('O'));
        }

        return $parsed->format('U O');
    }

    private function buildTreeFromIndex(Index $index): ObjectId
    {
        $entries = [];

        foreach ($index->entries() as $entry) {
            $entries[$entry->path] = $entry;
        }

        return $this->writeTreeFromPaths($entries);
    }

    /**
     * @param array<string, true> $dirtyPaths
     * @param array<string, true> $includedUntrackedPaths
     */
    private function buildTreeFromWorktree(
        Index $index,
        ObjectId $headId,
        bool $includeUntracked,
        array &$dirtyPaths = [],
        array &$includedUntrackedPaths = [],
    ): ObjectId {
        $entries = [];
        $modified = [];
        $deleted = [];
        $untracked = [];
        $statusByPath = [];
        $status = new WorkingTreeStatus($this->objects, $this->workDir, $this->gitDir);

        foreach ($status->compute($index, $headId) as $entry) {
            if ($entry->index === FileStatus::Ignored) {
                continue;
            }

            $statusByPath[$entry->path] = $entry;

            if ($entry->index === FileStatus::Untracked) {
                if ($includeUntracked) {
                    $untracked[] = $entry->path;
                    $includedUntrackedPaths[$entry->path] = true;
                }

                continue;
            }

            if ($entry->worktree === FileStatus::Deleted) {
                $deleted[$entry->path] = true;
                continue;
            }

            if ($entry->worktree !== FileStatus::Unmodified || $entry->index !== FileStatus::Unmodified) {
                $modified[$entry->path] = true;
                $dirtyPaths[$entry->path] = true;
            }
        }

        foreach ($index->entries() as $entry) {
            if (isset($deleted[$entry->path])) {
                continue;
            }

            if (!isset($modified[$entry->path])) {
                $entries[$entry->path] = $entry;
                continue;
            }

            $pathStatus = $statusByPath[$entry->path] ?? null;

            if (
                $pathStatus !== null
                && $pathStatus->worktree === FileStatus::Unmodified
                && $pathStatus->index !== FileStatus::Deleted
            ) {
                $entries[$entry->path] = $entry;
                continue;
            }

            $fullPath = $this->workDir . '/' . $entry->path;

            if (!is_file($fullPath)) {
                continue;
            }

            $content = file_get_contents($fullPath);
            $blob = Blob::fromContent($content !== false ? $content : '', $this->hashAlgo());
            $this->objects->write($blob);
            $entries[$entry->path] = IndexEntry::create($entry->path, $blob->id, $entry->mode);
        }

        if ($includeUntracked) {
            foreach ($untracked as $path) {
                $fullPath = $this->workDir . '/' . $path;

                if (!is_file($fullPath)) {
                    continue;
                }

                $content = file_get_contents($fullPath);
                $blob = Blob::fromContent($content !== false ? $content : '', $this->hashAlgo());
                $this->objects->write($blob);
                $entries[$path] = IndexEntry::fromStat($path, $blob->id, $fullPath);
            }
        }

        return $this->writeTreeFromPaths($entries);
    }

    /**
     * @param array<string, IndexEntry> $entries Path -> IndexEntry
     */
    private function writeTreeFromPaths(array $entries): ObjectId
    {
        $direct = [];
        $subDirs = [];

        foreach ($entries as $path => $entry) {
            $slashPos = strpos($path, '/');

            if ($slashPos === false) {
                $direct[$path] = $entry;
                continue;
            }

            $dirName = substr($path, 0, $slashPos);
            $rest = substr($path, $slashPos + 1);
            $subDirs[$dirName] ??= [];
            $subDirs[$dirName][$rest] = $entry;
        }

        $treeEntries = [];

        foreach ($direct as $name => $entry) {
            $mode = $entry->mode === 0100755 ? '100755' : '100644';
            $treeEntries[] = new TreeEntry($mode, $name, $entry->hash);
        }

        foreach ($subDirs as $dirName => $subEntries) {
            $subtreeId = $this->writeTreeFromPaths($subEntries);
            $treeEntries[] = new TreeEntry('40000', $dirName, $subtreeId);
        }

        usort($treeEntries, fn (TreeEntry $a, TreeEntry $b) => strcmp(
            $a->isTree() ? $a->name . '/' : $a->name,
            $b->isTree() ? $b->name . '/' : $b->name,
        ));

        $tree = Tree::fromEntries($treeEntries, $this->hashAlgo());
        $this->objects->write($tree);

        return $tree->id;
    }

    /**
     * @param array<string, true> $dirtyPaths
     * @param array<string, true> $prunePaths
     */
    private function resetToHead(ObjectId $headId, array $dirtyPaths = [], array $prunePaths = []): void
    {
        $commit = $this->objects->read($headId);

        if (!$commit instanceof Commit) {
            return;
        }

        $treeEntries = $this->flattenTreeEntries($commit->tree);
        $currentIndex = Index::open($this->gitDir . '/index', $this->hashBytes());
        $currentEntries = $currentIndex->entries();

        if ($dirtyPaths === []) {
            $dirtyPaths = $this->dirtyPathSet($currentIndex, $headId);
        }

        $index = new Index($this->hashBytes());
        $nextEntries = [];

        foreach ($treeEntries as $path => $entryInfo) {
            $currentEntry = $currentEntries[$path] ?? null;

            if (
                $currentEntry !== null
                && !isset($dirtyPaths[$path])
                && $currentEntry->hash->hex === $entryInfo['hash']
                && $currentEntry->mode === $entryInfo['mode']
            ) {
                $nextEntries[] = $currentEntry;
                continue;
            }

            $blob = $this->objects->read(ObjectId::fromHex($entryInfo['hash']));

            if (!$blob instanceof Blob) {
                continue;
            }

            $fullPath = $this->workDir . '/' . $path;
            $dir = dirname($fullPath);

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            file_put_contents($fullPath, $blob->content);
            $nextEntries[] = IndexEntry::fromStat($path, $blob->id, $fullPath);
        }

        if ($prunePaths !== []) {
            $this->pruneWorktreePaths(array_keys($prunePaths));
        }

        $index->addEntries($nextEntries);
        IndexWriter::write($index, $this->gitDir . '/index');
    }

    private function applyStashCommit(Commit $stash): void
    {
        $headId = $this->refs->resolveHead();
        $headCommit = $headId !== null ? $this->objects->read($headId) : null;
        $baseCommit = isset($stash->parents[0]) ? $this->objects->read($stash->parents[0]) : null;

        if (!$headCommit instanceof Commit || !$baseCommit instanceof Commit) {
            throw new \RuntimeException('Cannot apply stash without valid HEAD and base commits');
        }

        $baseEntries = $this->flattenTree($baseCommit->tree);
        $currentEntries = $this->flattenTree($headCommit->tree);
        $stashEntries = $this->flattenTree($stash->tree);
        $allPaths = array_unique(array_merge(
            array_keys($baseEntries),
            array_keys($currentEntries),
            array_keys($stashEntries),
        ));
        sort($allPaths);

        $written = [];
        $deleted = [];
        $conflicts = [];

        foreach ($allPaths as $path) {
            $baseHash = $baseEntries[$path] ?? null;
            $currentHash = $currentEntries[$path] ?? null;
            $stashHash = $stashEntries[$path] ?? null;

            if ($currentHash === $stashHash) {
                if ($currentHash !== null) {
                    $written[$path] = $this->readBlobContent(ObjectId::fromHex($currentHash));
                }

                continue;
            }

            if ($baseHash === $currentHash) {
                if ($stashHash === null) {
                    $deleted[] = $path;
                } else {
                    $written[$path] = $this->readBlobContent(ObjectId::fromHex($stashHash));
                }

                continue;
            }

            if ($baseHash === $stashHash) {
                if ($currentHash === null) {
                    $deleted[] = $path;
                } else {
                    $written[$path] = $this->readBlobContent(ObjectId::fromHex($currentHash));
                }

                continue;
            }

            $baseContent = $baseHash !== null ? $this->readBlobContent(ObjectId::fromHex($baseHash)) : '';
            $currentContent = $currentHash !== null ? $this->readBlobContent(ObjectId::fromHex($currentHash)) : '';
            $stashContent = $stashHash !== null ? $this->readBlobContent(ObjectId::fromHex($stashHash)) : '';
            $merged = ThreeWayMerge::merge(
                $baseContent,
                $currentContent,
                $stashContent,
                'Updated upstream',
                'Stashed changes',
            );

            $written[$path] = $merged['content'];

            if (!$merged['clean']) {
                $conflicts[] = $path;
            }
        }

        foreach ($deleted as $path) {
            $fullPath = $this->workDir . '/' . $path;

            if (is_file($fullPath) || is_link($fullPath)) {
                unlink($fullPath);
                $this->removeEmptyParentDirectories(dirname($fullPath));
            }
        }

        foreach ($written as $path => $content) {
            $fullPath = $this->workDir . '/' . $path;
            $dir = dirname($fullPath);

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            file_put_contents($fullPath, $content);
        }

        if ($conflicts !== []) {
            throw new MergeConflictException($conflicts, 'Stash apply stopped due to conflicts');
        }
    }

    /**
     * @return array<string, string>
     */
    private function flattenTree(ObjectId $treeId, string $prefix = ''): array
    {
        $result = [];
        $this->flattenTreeInto($treeId, $prefix, $result);

        return $result;
    }

    /**
     * @param array<string, string> $result
     */
    private function flattenTreeInto(ObjectId $treeId, string $prefix, array &$result): void
    {
        $tree = $this->objects->read($treeId);

        if (!$tree instanceof Tree) {
            return;
        }

        foreach ($tree->entries as $entry) {
            $fullPath = $prefix !== '' ? $prefix . '/' . $entry->name : $entry->name;

            if ($entry->isTree()) {
                $this->flattenTreeInto($entry->hash, $fullPath, $result);
                continue;
            }

            $result[$fullPath] = $entry->hash->hex;
        }
    }

    /**
     * @return array<string, array{hash: string, mode: int}>
     */
    private function flattenTreeEntries(ObjectId $treeId, string $prefix = ''): array
    {
        $result = [];
        $this->flattenTreeEntriesInto($treeId, $prefix, $result);

        return $result;
    }

    /**
     * @param array<string, array{hash: string, mode: int}> $result
     */
    private function flattenTreeEntriesInto(ObjectId $treeId, string $prefix, array &$result): void
    {
        $tree = $this->objects->read($treeId);

        if (!$tree instanceof Tree) {
            return;
        }

        foreach ($tree->entries as $entry) {
            $fullPath = $prefix !== '' ? $prefix . '/' . $entry->name : $entry->name;

            if ($entry->isTree()) {
                $this->flattenTreeEntriesInto($entry->hash, $fullPath, $result);
                continue;
            }

            $result[$fullPath] = [
                'hash' => $entry->hash->hex,
                'mode' => (int) octdec($entry->mode),
            ];
        }
    }

    private function readBlobContent(ObjectId $id): string
    {
        $object = $this->objects->read($id);

        return $object instanceof Blob ? $object->content : '';
    }

    private function removeEmptyParentDirectories(string $directory): void
    {
        while ($directory !== $this->workDir && str_starts_with($directory, $this->workDir . '/')) {
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

    /**
     * @param array<int, string> $paths
     */
    private function pruneWorktreePaths(array $paths): void
    {
        foreach (array_keys(array_fill_keys($paths, true)) as $path) {
            $fullPath = $this->workDir . '/' . $path;

            if (is_file($fullPath) || is_link($fullPath)) {
                unlink($fullPath);
                $this->removeEmptyParentDirectories(dirname($fullPath));
            }
        }
    }

    /**
     * @return array<string, true>
     */
    private function dirtyPathSet(Index $index, ObjectId $headId): array
    {
        $dirty = [];
        $status = new WorkingTreeStatus($this->objects, $this->workDir, $this->gitDir);

        foreach ($status->compute($index, $headId) as $entry) {
            if ($entry->index === FileStatus::Ignored) {
                continue;
            }

            if ($entry->index !== FileStatus::Unmodified || $entry->worktree !== FileStatus::Unmodified) {
                $dirty[$entry->path] = true;
            }
        }

        return $dirty;
    }

    private function hashAlgo(): string
    {
        return GitConfig::fromFile($this->gitDir . '/config')->get('extensions.objectformat') === 'sha256'
            ? 'sha256'
            : 'sha1';
    }

    private function hashBytes(): int
    {
        return ObjectId::hashBytesForAlgo($this->hashAlgo());
    }
}
