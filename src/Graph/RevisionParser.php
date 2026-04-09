<?php

declare(strict_types=1);

namespace Pitmaster\Graph;

use Pitmaster\Object\Commit;
use Pitmaster\Object\ObjectId;
use Pitmaster\Storage\ObjectDatabase;
use Pitmaster\Ref\RefDatabase;

/**
 * Parse revision expressions: HEAD~3, main^2, tag@{0}.
 */
final class RevisionParser
{
    public function __construct(
        private readonly ObjectDatabase $objects,
        private readonly RefDatabase $refs,
    ) {
    }

    /**
     * Resolve a revision expression to an ObjectId.
     */
    public function resolve(string $expression): ?ObjectId
    {
        // Split into base and modifiers: "HEAD~3" -> base="HEAD", modifiers="~3"
        if (preg_match('/^([^~^@]+)((?:[~^](?:\d+)?|@\{\d+\})*)$/', $expression, $matches)) {
            $base = $matches[1];
            $modifiers = $matches[2];
        } else {
            $base = $expression;
            $modifiers = '';
        }

        // Resolve base
        $id = $this->resolveBase($base);

        if ($id === null) {
            return null;
        }

        // Apply modifiers
        if ($modifiers !== '') {
            $id = $this->applyModifiers($id, $modifiers);
        }

        return $id;
    }

    private function resolveBase(string $base): ?ObjectId
    {
        // Direct hash
        if (ObjectId::looksLikeHex($base)) {
            return ObjectId::fromHex($base);
        }

        // HEAD
        if ($base === 'HEAD') {
            return $this->refs->resolveHead();
        }

        // Branch or tag
        return $this->refs->resolve("refs/heads/{$base}")
            ?? $this->refs->resolve("refs/tags/{$base}")
            ?? $this->refs->resolve($base);
    }

    private function applyModifiers(ObjectId $id, string $modifiers): ?ObjectId
    {
        preg_match_all('/([~^])(\d*)/', $modifiers, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $op = $match[1];
            $count = $match[2] !== '' ? (int) $match[2] : 1;

            if ($op === '~') {
                // ~ follows first parent N times
                for ($i = 0; $i < $count; $i++) {
                    $commit = $this->objects->read($id);

                    if (!$commit instanceof Commit || $commit->parents === []) {
                        return null;
                    }

                    $id = $commit->parents[0];
                }
            } elseif ($op === '^') {
                // ^ selects the Nth parent
                $commit = $this->objects->read($id);

                if (!$commit instanceof Commit) {
                    return null;
                }

                $parentIdx = $count - 1;

                if ($parentIdx < 0 || $parentIdx >= count($commit->parents)) {
                    return null;
                }

                $id = $commit->parents[$parentIdx];
            }
        }

        return $id;
    }
}
