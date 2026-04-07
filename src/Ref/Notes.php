<?php

declare(strict_types=1);

namespace Pitmaster\Ref;

use Pitmaster\Object\Blob;
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
        $treeId = $this->refs->resolve($notesRef);

        if ($treeId === null) {
            return null;
        }

        $tree = $this->objects->read($treeId);

        if (!$tree instanceof Tree) {
            return null;
        }

        $entry = $tree->entry($commitId->hex);

        if ($entry === null) {
            return null;
        }

        $blob = $this->objects->read($entry->hash);

        if (!$blob instanceof Blob) {
            return null;
        }

        return $blob->content;
    }

    /**
     * Add or update a note for a commit.
     */
    public function set(ObjectId $commitId, string $noteContent, string $notesRef = self::DEFAULT_REF): void
    {
        // Write note blob
        $blob = Blob::fromContent($noteContent);
        $this->objects->write($blob);

        // Get existing notes tree
        $entries = [];
        $treeId = $this->refs->resolve($notesRef);

        if ($treeId !== null) {
            $tree = $this->objects->read($treeId);

            if ($tree instanceof Tree) {
                foreach ($tree->entries as $entry) {
                    if ($entry->name !== $commitId->hex) {
                        $entries[] = $entry;
                    }
                }
            }
        }

        // Add new note entry
        $entries[] = new TreeEntry('100644', $commitId->hex, $blob->id);

        // Write new tree
        $newTree = Tree::fromEntries($entries);
        $this->objects->write($newTree);

        // Update notes ref
        $this->refs->update($notesRef, $newTree->id);
    }

    /**
     * Remove a note from a commit.
     */
    public function remove(ObjectId $commitId, string $notesRef = self::DEFAULT_REF): void
    {
        $treeId = $this->refs->resolve($notesRef);

        if ($treeId === null) {
            return;
        }

        $tree = $this->objects->read($treeId);

        if (!$tree instanceof Tree) {
            return;
        }

        $entries = [];

        foreach ($tree->entries as $entry) {
            if ($entry->name !== $commitId->hex) {
                $entries[] = $entry;
            }
        }

        if (count($entries) === count($tree->entries)) {
            return; // Note didn't exist
        }

        $newTree = Tree::fromEntries($entries);
        $this->objects->write($newTree);
        $this->refs->update($notesRef, $newTree->id);
    }

    /**
     * List all notes.
     *
     * @return array<string, string> commitHash => noteContent
     */
    public function listAll(string $notesRef = self::DEFAULT_REF): array
    {
        $treeId = $this->refs->resolve($notesRef);

        if ($treeId === null) {
            return [];
        }

        $tree = $this->objects->read($treeId);

        if (!$tree instanceof Tree) {
            return [];
        }

        $notes = [];

        foreach ($tree->entries as $entry) {
            $blob = $this->objects->read($entry->hash);

            if ($blob instanceof Blob) {
                $notes[$entry->name] = $blob->content;
            }
        }

        return $notes;
    }
}
