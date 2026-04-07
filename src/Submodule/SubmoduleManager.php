<?php

declare(strict_types=1);

namespace Pitmaster\Submodule;

use Pitmaster\Object\ObjectId;
use Pitmaster\Object\Tree;
use Pitmaster\Pitmaster;
use Pitmaster\Storage\ObjectDatabase;

/**
 * Manages git submodules.
 *
 * Submodules are declared in .gitmodules and represented in trees as
 * gitlink entries (mode 160000) pointing to the submodule's commit hash.
 */
final class SubmoduleManager
{
    public function __construct(
        private readonly ObjectDatabase $objects,
        private readonly string $workDir,
        private readonly string $gitDir,
    ) {
    }

    /**
     * Parse .gitmodules and return all submodule definitions.
     *
     * @return array<int, Submodule>
     */
    public function list(): array
    {
        $path = $this->workDir . '/.gitmodules';

        if (!is_file($path)) {
            return [];
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return [];
        }

        return self::parseGitmodules($content);
    }

    /**
     * Parse .gitmodules content.
     *
     * Format:
     *   [submodule "name"]
     *       path = some/path
     *       url = https://example.com/repo.git
     *       branch = main
     *
     * @return array<int, Submodule>
     */
    public static function parseGitmodules(string $content): array
    {
        $submodules = [];
        $current = null;

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (preg_match('/^\[submodule\s+"([^"]+)"\]$/', $line, $matches)) {
                if ($current !== null && isset($current['path'], $current['url'])) {
                    $submodules[] = new Submodule(
                        name: $current['name'],
                        path: $current['path'],
                        url: $current['url'],
                        branch: $current['branch'] ?? null,
                    );
                }

                $current = ['name' => $matches[1]];
                continue;
            }

            if ($current !== null && preg_match('/^(\w+)\s*=\s*(.+)$/', $line, $matches)) {
                $current[strtolower($matches[1])] = trim($matches[2]);
            }
        }

        if ($current !== null && isset($current['path'], $current['url'])) {
            $submodules[] = new Submodule(
                name: $current['name'],
                path: $current['path'],
                url: $current['url'],
                branch: $current['branch'] ?? null,
            );
        }

        return $submodules;
    }

    /**
     * Initialize submodules: create .git/modules/<name>/ directories.
     */
    public function init(): void
    {
        foreach ($this->list() as $submodule) {
            $moduleDir = $this->gitDir . '/modules/' . $submodule->name;

            if (!is_dir($moduleDir)) {
                mkdir($moduleDir, 0777, true);
            }

            // Write .git file in submodule path pointing to modules dir
            $submodulePath = $this->workDir . '/' . $submodule->path;

            if (!is_dir($submodulePath)) {
                mkdir($submodulePath, 0777, true);
            }

            $relativeGitDir = $this->relativePath($submodulePath, $moduleDir);
            file_put_contents($submodulePath . '/.git', "gitdir: {$relativeGitDir}\n");
        }
    }

    /**
     * Get the commit hash a submodule is pinned to in the current tree.
     */
    public function pinnedCommit(string $submodulePath, ObjectId $treeId): ?ObjectId
    {
        $parts = explode('/', $submodulePath);
        $currentTree = $treeId;

        foreach ($parts as $i => $part) {
            $tree = $this->objects->read($currentTree);

            if (!$tree instanceof Tree) {
                return null;
            }

            $entry = $tree->entry($part);

            if ($entry === null) {
                return null;
            }

            if ($i === count($parts) - 1) {
                return $entry->isGitlink() ? $entry->hash : null;
            }

            $currentTree = $entry->hash;
        }

        return null;
    }

    /**
     * Get status of all submodules.
     *
     * @return array<int, array{name: string, path: string, expected: ?string, actual: ?string, dirty: bool}>
     */
    public function status(ObjectId $treeId): array
    {
        $result = [];

        foreach ($this->list() as $submodule) {
            $expected = $this->pinnedCommit($submodule->path, $treeId);
            $actual = null;
            $dirty = false;

            $submodulePath = $this->workDir . '/' . $submodule->path;

            if (is_dir($submodulePath . '/.git') || is_file($submodulePath . '/.git')) {
                try {
                    $subRepo = Pitmaster::open($submodulePath);
                    $headId = $subRepo->refDatabase()->resolveHead();
                    $actual = $headId?->hex;
                } catch (\Throwable) {
                    // Submodule not initialized
                }
            }

            $result[] = [
                'name' => $submodule->name,
                'path' => $submodule->path,
                'expected' => $expected?->hex,
                'actual' => $actual,
                'dirty' => $expected !== null && $actual !== null && $expected->hex !== $actual,
            ];
        }

        return $result;
    }

    private function relativePath(string $from, string $to): string
    {
        $fromParts = explode('/', realpath($from) ?: $from);
        $toParts = explode('/', realpath($to) ?: $to);

        $common = 0;

        while ($common < count($fromParts) && $common < count($toParts) && $fromParts[$common] === $toParts[$common]) {
            $common++;
        }

        $ups = count($fromParts) - $common;
        $downs = array_slice($toParts, $common);

        return str_repeat('../', $ups) . implode('/', $downs);
    }
}
