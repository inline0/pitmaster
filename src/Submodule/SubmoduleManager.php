<?php

declare(strict_types=1);

namespace Pitmaster\Submodule;

use Pitmaster\Config\GitConfig;
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
    /** @var array<int, Submodule>|null */
    private ?array $submodulesCache = null;

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
        if ($this->submodulesCache !== null) {
            return $this->submodulesCache;
        }

        $path = $this->workDir . '/.gitmodules';

        if (!is_file($path)) {
            return $this->submodulesCache = [];
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return $this->submodulesCache = [];
        }

        return $this->submodulesCache = self::parseGitmodules($content);
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
            $this->initSubmodule($submodule);
        }
    }

    /**
     * Initialize and update submodules to the commits pinned in the given tree.
     */
    public function update(ObjectId $treeId): void
    {
        foreach ($this->list() as $submodule) {
            $expected = $this->pinnedCommit($submodule->path, $treeId);

            if ($expected === null) {
                continue;
            }

            $moduleDir = $this->initSubmodule($submodule, true);
            $submodulePath = $this->workDir . '/' . $submodule->path;
            $sourcePath = $this->resolveSubmoduleUrl($submodule);
            $sourceHead = $this->resolveSubmoduleHead($sourcePath);

            $this->syncModuleRepository($sourcePath, $moduleDir);
            $this->syncWorktreeFiles($sourcePath, $submodulePath);
            $this->writeModuleMetadata($moduleDir, $submodulePath, $submodule->url);

            if ($sourceHead !== null && $sourceHead->hex === $expected->hex) {
                $this->detachModuleHead($moduleDir, $expected);
                continue;
            }

            $repo = Pitmaster::open($submodulePath);
            $repo->checkout($expected->hex);
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
                    $actual = $this->resolveSubmoduleHead($submodulePath)?->hex;
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

    private function resolveSubmoduleHead(string $submodulePath): ?ObjectId
    {
        $gitDir = $this->resolveLinkedGitDir($submodulePath);

        if ($gitDir === null) {
            return null;
        }

        $headPath = $gitDir . '/HEAD';

        if (!is_file($headPath)) {
            return null;
        }

        $head = trim((string) file_get_contents($headPath));

        if (\Pitmaster\Object\ObjectId::looksLikeHex($head)) {
            return ObjectId::fromHex($head);
        }

        if (str_starts_with($head, 'ref: ')) {
            $refPath = $gitDir . '/' . substr($head, 5);

            if (is_file($refPath)) {
                $ref = trim((string) file_get_contents($refPath));

                if (ObjectId::looksLikeHex($ref)) {
                    return ObjectId::fromHex($ref);
                }
            }
        }

        return (new \Pitmaster\Ref\RefDatabase($gitDir))->resolveHead();
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

    private function initSubmodule(Submodule $submodule, bool $materialize = false): string
    {
        $moduleDir = $this->gitDir . '/modules/' . $submodule->name;
        $submodulePath = $this->workDir . '/' . $submodule->path;

        if (!is_dir($moduleDir)) {
            mkdir($moduleDir, 0777, true);
        }

        if (!is_dir($submodulePath)) {
            mkdir($submodulePath, 0777, true);
        }

        $config = GitConfig::fromFile($this->gitDir . '/config');
        $config->set("submodule.{$submodule->name}.active", 'true');
        $config->set("submodule.{$submodule->name}.url", $submodule->url);
        $config->writeToFile($this->gitDir . '/config');

        if ($materialize) {
            $this->writeGitdirPointer($submodulePath, $moduleDir);
        }

        return $moduleDir;
    }

    private function writeGitdirPointer(string $submodulePath, string $moduleDir): void
    {
        $relativeGitDir = $this->relativePath($submodulePath, $moduleDir);
        file_put_contents($submodulePath . '/.git', "gitdir: {$relativeGitDir}\n");
    }

    private function resolveSubmoduleUrl(Submodule $submodule): string
    {
        $url = $submodule->url;

        if (str_starts_with($url, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $url) === 1) {
            return $url;
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:\/\//', $url) === 1) {
            throw new \RuntimeException("Submodule update currently supports only local paths: {$url}");
        }

        $resolved = realpath($this->workDir . '/' . $url);

        if ($resolved === false) {
            throw new \RuntimeException("Submodule source not found: {$url}");
        }

        return $resolved;
    }

    private function syncModuleRepository(string $sourcePath, string $moduleDir): void
    {
        $sourceGitDir = $this->resolveGitDir($sourcePath);
        $this->removePath($moduleDir);
        mkdir($moduleDir, 0777, true);
        $this->copyTree($sourceGitDir, $moduleDir);
    }

    private function syncWorktreeFiles(string $sourcePath, string $submodulePath): void
    {
        foreach (scandir($submodulePath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.git') {
                continue;
            }

            $this->removePath($submodulePath . '/' . $entry);
        }

        foreach (scandir($sourcePath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.git') {
                continue;
            }

            $this->copyTree($sourcePath . '/' . $entry, $submodulePath . '/' . $entry);
        }
    }

    private function writeModuleMetadata(string $moduleDir, string $submodulePath, string $url): void
    {
        file_put_contents($moduleDir . '/commondir', ".\n");

        $config = GitConfig::fromFile($moduleDir . '/config');
        $config->set('core.bare', 'false');
        $config->set('core.worktree', $submodulePath);
        $config->set('remote.origin.url', $url);
        $config->writeToFile($moduleDir . '/config');
    }

    private function detachModuleHead(string $moduleDir, ObjectId $expected): void
    {
        file_put_contents($moduleDir . '/HEAD', $expected->hex . "\n");
    }

    private function resolveGitDir(string $path): string
    {
        if (is_dir($path . '/.git')) {
            return $path . '/.git';
        }

        if (is_file($path . '/.git')) {
            $content = trim((string) file_get_contents($path . '/.git'));

            if (!str_starts_with($content, 'gitdir: ')) {
                throw new \RuntimeException("Invalid submodule source gitdir at {$path}");
            }

            $gitDir = substr($content, 8);

            if (!str_starts_with($gitDir, '/')) {
                $gitDir = $path . '/' . $gitDir;
            }

            return $gitDir;
        }

        if (is_file($path . '/HEAD')) {
            return $path;
        }

        throw new \RuntimeException("Not a repository: {$path}");
    }

    private function resolveLinkedGitDir(string $path): ?string
    {
        if (is_dir($path . '/.git')) {
            return $path . '/.git';
        }

        if (!is_file($path . '/.git')) {
            return null;
        }

        $content = trim((string) file_get_contents($path . '/.git'));

        if (!str_starts_with($content, 'gitdir: ')) {
            return null;
        }

        $gitDir = substr($content, 8);

        if (!str_starts_with($gitDir, '/')) {
            $gitDir = $path . '/' . $gitDir;
        }

        return $gitDir;
    }

    private function copyTree(string $source, string $target): void
    {
        if (is_file($source)) {
            $dir = dirname($target);

            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }

            copy($source, $target);

            return;
        }

        if (!is_dir($source)) {
            return;
        }

        if (!is_dir($target)) {
            mkdir($target, 0777, true);
        }

        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->copyTree($source . '/' . $entry, $target . '/' . $entry);
        }
    }

    private function removePath(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->removePath($path . '/' . $entry);
        }

        rmdir($path);
    }
}
