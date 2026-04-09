<?php

declare(strict_types=1);

namespace Pitmaster\Ref;

use Pitmaster\Object\ObjectId;

/**
 * Composite ref store: loose refs take priority over packed refs.
 *
 * Writes go to loose refs. Packed refs are read-only (git gc manages them).
 */
final class RefDatabase implements RefStore
{
    private readonly LooseRefStore $loose;
    private readonly PackedRefStore $packed;
    private readonly ?ReftableStore $reftable;
    private readonly string $commonDir;

    /**
     * @param string $gitDir Per-worktree git dir (HEAD, loose refs)
     * @param string|null $commonDir Common git dir for packed-refs and shared refs (null = same as gitDir)
     */
    public function __construct(string $gitDir, ?string $commonDir = null)
    {
        $commonDir = $commonDir ?? $gitDir;
        $this->commonDir = $commonDir;

        // HEAD and per-worktree refs from gitDir
        $this->loose = new LooseRefStore($gitDir, $commonDir);
        // packed-refs from common dir
        $this->packed = new PackedRefStore($commonDir);
        $this->reftable = ReftableStore::open($commonDir);
    }

    public function resolve(string $name): ?ObjectId
    {
        if ($name === 'HEAD') {
            $id = $this->loose->resolve('HEAD');

            if ($id !== null) {
                return $id;
            }

            $head = $this->readHead();

            return $head !== null ? $this->resolve($head->target) : null;
        }

        // Loose takes priority
        $id = $this->loose->resolve($name);

        if ($id !== null) {
            return $id;
        }

        if ($this->reftable !== null) {
            $id = $this->reftable->resolve($name);

            if ($id !== null) {
                return $id;
            }
        }

        return $this->packed->resolve($name);
    }

    public function exists(string $name): bool
    {
        if ($name === 'HEAD') {
            return $this->readHead() !== null;
        }

        return $this->loose->exists($name)
            || ($this->reftable?->exists($name) ?? false)
            || $this->packed->exists($name);
    }

    /**
     * @return array<string, ObjectId>
     */
    public function list(): array
    {
        $shared = $this->reftable?->list() ?? $this->packed->list();

        return array_merge($shared, $this->loose->list());
    }

    /**
     * Read HEAD as a symbolic ref.
     */
    public function readHead(): ?SymbolicRef
    {
        $head = $this->loose->readHead();

        if ($this->isUsableHead($head)) {
            return $head;
        }

        return $this->reftable?->readHead();
    }

    /**
     * Resolve HEAD to an ObjectId (following symbolic ref chain).
     */
    public function resolveHead(): ?ObjectId
    {
        return $this->resolve('HEAD');
    }

    /**
     * Write a ref.
     */
    public function update(string $name, ObjectId $target): void
    {
        $this->loose->update($name, $target);
    }

    /**
     * Write a symbolic ref.
     */
    public function updateSymbolic(string $name, string $target): void
    {
        $this->loose->updateSymbolic($name, $target);
    }

    /**
     * Delete a ref.
     */
    public function delete(string $name): void
    {
        $this->loose->delete($name);

        if ($this->packed->exists($name)) {
            $this->packed->remove($name);
            $this->packed->write();
        }
    }

    public function looseStore(): LooseRefStore
    {
        return $this->loose;
    }

    public function packedStore(): PackedRefStore
    {
        return $this->packed;
    }

    public function commonDir(): string
    {
        return $this->commonDir;
    }

    private function isUsableHead(?SymbolicRef $head): bool
    {
        if ($head === null) {
            return false;
        }

        if ($head->target === 'refs/heads/.invalid') {
            return false;
        }

        return true;
    }
}
