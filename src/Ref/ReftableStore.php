<?php

declare(strict_types=1);

namespace Pitmaster\Ref;

use Pitmaster\Object\ObjectId;

/**
 * Read stacked Git reftable files from .git/reftable/tables.list.
 *
 * Git uses reftable as an alternative shared ref backend. Each table in the
 * stack overrides entries from earlier tables, similar to how loose refs
 * override packed refs in the files backend.
 */
final class ReftableStore
{
    /** @var array<string, ObjectId> */
    private array $refs = [];

    /** @var array<string, string> */
    private array $symrefs = [];

    private function __construct()
    {
    }

    public static function open(string $gitDir): ?self
    {
        $tablesList = $gitDir . '/reftable/tables.list';

        if (!is_file($tablesList)) {
            return null;
        }

        $lines = file($tablesList, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false || $lines === []) {
            return null;
        }

        $store = new self();

        foreach ($lines as $line) {
            $tableName = trim($line);

            if ($tableName === '') {
                continue;
            }

            $table = Reftable::open($gitDir . '/reftable/' . $tableName);

            if ($table === null) {
                continue;
            }

            foreach ($table->refs() as $name => $id) {
                $store->refs[$name] = $id;
                unset($store->symrefs[$name]);
            }

            foreach ($table->symrefs() as $name => $target) {
                $store->symrefs[$name] = $target;
                unset($store->refs[$name]);
            }
        }

        return $store;
    }

    public function exists(string $name): bool
    {
        return isset($this->refs[$name]) || isset($this->symrefs[$name]);
    }

    public function resolve(string $name, int $depth = 0): ?ObjectId
    {
        if ($depth > 10) {
            return null;
        }

        if (isset($this->refs[$name])) {
            return $this->refs[$name];
        }

        if (isset($this->symrefs[$name])) {
            return $this->resolve($this->symrefs[$name], $depth + 1);
        }

        return null;
    }

    /**
     * @return array<string, ObjectId>
     */
    public function list(): array
    {
        $refs = $this->refs;

        foreach ($this->symrefs as $name => $target) {
            if ($name === 'HEAD') {
                continue;
            }

            $resolved = $this->resolve($target);

            if ($resolved !== null) {
                $refs[$name] = $resolved;
            }
        }

        return $refs;
    }

    public function readHead(): ?SymbolicRef
    {
        $target = $this->symrefs['HEAD'] ?? null;

        return $target !== null ? new SymbolicRef('HEAD', $target) : null;
    }

    public function resolveSymbolic(string $name): ?string
    {
        return $this->symrefs[$name] ?? null;
    }
}
