<?php

declare(strict_types=1);

namespace Pitmaster;

use Pitmaster\Config\GitConfig;
use Pitmaster\Exceptions\ObjectNotFoundException;
use Pitmaster\Graph\CommitWalker;
use Pitmaster\Object\Blob;
use Pitmaster\Object\Commit;
use Pitmaster\Object\GitObject;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\ObjectType;
use Pitmaster\Object\Tree;
use Pitmaster\Ref\RefDatabase;
use Pitmaster\Ref\SymbolicRef;
use Pitmaster\Storage\ObjectDatabase;

/**
 * Repository handle. Wraps a .git directory and provides all operations.
 */
final class Repository
{
    private readonly ObjectDatabase $objects;
    private readonly RefDatabase $refs;
    private readonly GitConfig $config;
    private readonly string $gitDir;
    private readonly string $workDir;

    public function __construct(string $path)
    {
        // Accept either the repo root or the .git directory itself
        if (is_dir($path . '/.git')) {
            $this->workDir = $path;
            $this->gitDir = $path . '/.git';
        } elseif (is_file($path . '/HEAD')) {
            // Bare repo or .git directory passed directly
            $this->workDir = dirname($path);
            $this->gitDir = $path;
        } else {
            throw new \InvalidArgumentException("Not a git repository: {$path}");
        }

        $this->objects = new ObjectDatabase($this->gitDir . '/objects');
        $this->refs = new RefDatabase($this->gitDir);
        $this->config = GitConfig::fromFile($this->gitDir . '/config');
    }

    public function gitDir(): string
    {
        return $this->gitDir;
    }

    public function workDir(): string
    {
        return $this->workDir;
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
}
