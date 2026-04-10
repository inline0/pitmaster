<?php

declare(strict_types=1);

namespace Pitmaster\Ref;

use Pitmaster\Object\Blob;
use Pitmaster\Object\Commit;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;
use Pitmaster\Object\Tree;
use Pitmaster\Object\TreeEntry;
use Pitmaster\Storage\ObjectDatabase;

/**
 * Git notes: attach metadata to commits without modifying them.
 *
 * Notes are stored as a tree under refs/notes/commits.
 * Each entry in the tree is named by the target commit hash
 * and points to a blob containing the note content.
 */
final class Notes
{
    private const DEFAULT_REF = 'refs/notes/commits';

    /** @var array<string, array<string, ObjectId>> */
    private array $noteMapCache = [];

    public function __construct(
        private readonly ObjectDatabase $objects,
        private readonly RefDatabase $refs,
    ) {
    }

    /**
     * Get the note for a commit.
     */
    public function get(ObjectId $commitId, string $notesRef = self::DEFAULT_REF): ?string
    {
        $treeId = $this->notesTreeId($notesRef);

        if ($treeId === null) {
            return null;
        }

        $blobId = $this->findNoteBlobId($treeId, $commitId);

        if ($blobId === null) {
            return null;
        }

        $blob = $this->objects->read($blobId);

        return $blob instanceof Blob ? rtrim($blob->content, "\n") : null;
    }

    /**
     * Add or update a note for a commit.
     */
    public function set(ObjectId $commitId, string $noteContent, string $notesRef = self::DEFAULT_REF): void
    {
        $blob = Blob::fromContent(str_ends_with($noteContent, "\n") ? $noteContent : $noteContent . "\n");
        $this->objects->write($blob);

        $entries = $this->readNoteMap($notesRef);
        unset($entries[$commitId->hex]);
        unset($entries[$this->notePath($commitId)]);
        $entries[$commitId->hex] = $blob->id;

        $newTree = $this->buildNotesTree($entries);
        $this->objects->write($newTree);
        $this->writeNotesRef($notesRef, $newTree);
    }

    /**
     * Remove a note from a commit.
     */
    public function remove(ObjectId $commitId, string $notesRef = self::DEFAULT_REF): void
    {
        $entries = $this->readNoteMap($notesRef);
        $removed = false;

        foreach ([$this->notePath($commitId), $commitId->hex] as $path) {
            if (isset($entries[$path])) {
                unset($entries[$path]);
                $removed = true;
            }
        }

        if (!$removed) {
            return; // Note didn't exist
        }

        $newTree = $this->buildNotesTree($entries);
        $this->objects->write($newTree);
        $this->writeNotesRef($notesRef, $newTree);
    }

    /**
     * List all notes.
     *
     * @return array<string, string> commitHash => noteContent
     */
    public function listAll(string $notesRef = self::DEFAULT_REF): array
    {
        $notes = [];

        foreach ($this->readNoteMap($notesRef) as $path => $blobId) {
            $blob = $this->objects->read($blobId);

            if ($blob instanceof Blob) {
                $notes[str_replace('/', '', $path)] = rtrim($blob->content, "\n");
            }
        }

        return $notes;
    }

    /**
     * Merge notes from one ref into another.
     */
    public function merge(string $sourceRef, string $targetRef = self::DEFAULT_REF): void
    {
        $targetEntries = $this->readNoteMap($targetRef);
        $sourceEntries = $this->readNoteMap($sourceRef);

        foreach ($sourceEntries as $path => $blobId) {
            if (!isset($targetEntries[$path])) {
                $targetEntries[$path] = $blobId;
                continue;
            }

            if ($targetEntries[$path]->hex === $blobId->hex) {
                continue;
            }

            $targetBlob = $this->objects->read($targetEntries[$path]);
            $sourceBlob = $this->objects->read($blobId);

            if (
                !$targetBlob instanceof Blob
                || !$sourceBlob instanceof Blob
                || rtrim($targetBlob->content, "\n") !== rtrim($sourceBlob->content, "\n")
            ) {
                throw new \RuntimeException("Cannot merge conflicting notes for {$path}");
            }
        }

        $newTree = $this->buildNotesTree($targetEntries);
        $this->objects->write($newTree);
        $this->writeNotesRef($targetRef, $newTree, $sourceRef, "notes: merged from {$sourceRef}\n");
    }

    /**
     * @return array<string, ObjectId>
     */
    private function readNoteMap(string $notesRef): array
    {
        if (isset($this->noteMapCache[$notesRef])) {
            return $this->noteMapCache[$notesRef];
        }

        $treeId = $this->notesTreeId($notesRef);

        if ($treeId === null) {
            return $this->noteMapCache[$notesRef] = [];
        }

        $result = [];
        $this->flattenNotesTreeInto($treeId, '', $result);

        return $this->noteMapCache[$notesRef] = $result;
    }

    private function notesTreeId(string $notesRef): ?ObjectId
    {
        $objectId = $this->refs->resolve($notesRef);

        if ($objectId === null) {
            return null;
        }

        $object = $this->objects->read($objectId);

        if ($object instanceof Commit) {
            return $object->tree;
        }

        return $object instanceof Tree ? $object->id : null;
    }

    private function notePath(ObjectId $commitId): string
    {
        return substr($commitId->hex, 0, 2) . '/' . substr($commitId->hex, 2);
    }

    private function writeNotesRef(
        string $notesRef,
        Tree $tree,
        ?string $extraParentRef = null,
        string $message = "Notes added by 'git notes add'\n",
    ): void {
        $parent = $this->refs->resolve($notesRef);
        $parents = [];

        if ($parent !== null) {
            $object = $this->objects->read($parent);

            if ($object instanceof Commit) {
                $parents[] = $parent;
            }
        }

        if ($extraParentRef !== null) {
            $extraParent = $this->refs->resolve($extraParentRef);
            $extraObject = $extraParent !== null ? $this->objects->read($extraParent) : null;

            if ($extraParent !== null && $extraObject instanceof Commit && !in_array($extraParent, $parents, false)) {
                $parents[] = $extraParent;
            }
        }

        $identity = $this->currentIdentity();
        $content = Commit::buildContent(
            tree: $tree->id,
            parents: $parents,
            author: $identity,
            committer: $identity,
            message: $message,
        );
        $commitId = ObjectId::compute(ObjectType::Commit, $content);
        $commit = Commit::parse($content, $commitId);
        $this->objects->write($commit);
        $this->refs->update($notesRef, $commitId);
        unset($this->noteMapCache[$notesRef]);
    }

    private function findNoteBlobId(ObjectId $treeId, ObjectId $commitId): ?ObjectId
    {
        foreach ([$this->notePath($commitId), $commitId->hex] as $path) {
            $blobId = $this->findBlobAtPath($treeId, explode('/', $path));

            if ($blobId !== null) {
                return $blobId;
            }
        }

        return null;
    }

    private function findBlobAtPath(ObjectId $treeId, array $parts): ?ObjectId
    {
        $tree = $this->objects->read($treeId);

        if (!$tree instanceof Tree || $parts === []) {
            return null;
        }

        $entry = $tree->entry(array_shift($parts));

        if ($entry === null) {
            return null;
        }

        if ($parts === []) {
            return $entry->isBlob() ? $entry->hash : null;
        }

        return $entry->isTree() ? $this->findBlobAtPath($entry->hash, $parts) : null;
    }

    /**
     * @return array<string, ObjectId>
     */
    /**
     * @param array<string, ObjectId> $result
     */
    private function flattenNotesTreeInto(ObjectId $treeId, string $prefix, array &$result): void
    {
        $tree = $this->objects->read($treeId);

        if (!$tree instanceof Tree) {
            return;
        }

        foreach ($tree->entries as $entry) {
            $path = $prefix !== '' ? $prefix . '/' . $entry->name : $entry->name;

            if ($entry->isTree()) {
                $this->flattenNotesTreeInto($entry->hash, $path, $result);
                continue;
            }

            if ($entry->isBlob()) {
                $result[$path] = $entry->hash;
            }
        }
    }

    /**
     * @param array<string, ObjectId> $entries
     */
    private function buildNotesTree(array $entries): Tree
    {
        $tree = [];
        ksort($entries);

        foreach ($entries as $path => $blobId) {
            $this->insertTreeEntry($tree, explode('/', $path), $blobId);
        }

        return $this->writeTreeNode($tree);
    }

    /**
     * @param array<string, mixed> $node
     * @param array<int, string> $parts
     */
    private function insertTreeEntry(array &$node, array $parts, ObjectId $blobId): void
    {
        $name = array_shift($parts);

        if ($name === null) {
            return;
        }

        if ($parts === []) {
            $node[$name] = $blobId;
            return;
        }

        $node[$name] ??= [];
        $this->insertTreeEntry($node[$name], $parts, $blobId);
    }

    /**
     * @param array<string, mixed> $node
     */
    private function writeTreeNode(array $node): Tree
    {
        ksort($node);
        $entries = [];

        foreach ($node as $name => $value) {
            $name = (string) $name;

            if ($value instanceof ObjectId) {
                $entries[] = new TreeEntry('100644', $name, $value);
                continue;
            }

            $tree = $this->writeTreeNode($value);
            $this->objects->write($tree);
            $entries[] = new TreeEntry('40000', $name, $tree->id);
        }

        return Tree::fromEntries($entries);
    }

    private function currentIdentity(): string
    {
        $name = getenv('GIT_COMMITTER_NAME')
            ?: getenv('GIT_AUTHOR_NAME')
            ?: 'Pitmaster';
        $email = getenv('GIT_COMMITTER_EMAIL')
            ?: getenv('GIT_AUTHOR_EMAIL')
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
}
