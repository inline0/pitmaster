<?php

declare(strict_types=1);

namespace Pitmaster;

use Pitmaster\Protocol\SmartHttpClient;
use Pitmaster\Protocol\UploadPackClient;

/**
 * Static facade. Public API entry point.
 */
final class Pitmaster
{
    /**
     * Open an existing repository.
     */
    public static function open(string $path): Repository
    {
        return new Repository($path);
    }

    /**
     * Initialize a new repository.
     */
    public static function init(string $path): Repository
    {
        $gitDir = $path . '/.git';

        if (is_dir($gitDir)) {
            throw new \RuntimeException("Repository already exists at {$path}");
        }

        mkdir($gitDir, 0777, true);
        mkdir($gitDir . '/objects', 0777, true);
        mkdir($gitDir . '/refs/heads', 0777, true);
        mkdir($gitDir . '/refs/tags', 0777, true);

        file_put_contents($gitDir . '/HEAD', "ref: refs/heads/main\n");
        file_put_contents($gitDir . '/config', implode("\n", [
            '[core]',
            "\trepositoryformatversion = 0",
            "\tfilemode = true",
            "\tbare = false",
            "\tlogallrefupdates = true",
            '',
        ]));

        return new Repository($path);
    }

    /**
     * Clone a remote repository via smart HTTP.
     */
    public static function clone(string $url, string $path): Repository
    {
        // Initialize empty repo
        $repo = self::init($path);
        $gitDir = $path . '/.git';

        // Save remote config
        $config = $repo->config();
        $config->set('remote.origin.url', $url);
        $config->set('remote.origin.fetch', '+refs/heads/*:refs/remotes/origin/*');
        $config->writeToFile($gitDir . '/config');

        // Discover remote refs
        $http = new SmartHttpClient();
        $discovery = $http->discoverRefs($url);
        $uploadPack = new UploadPackClient($http);

        // Want all refs
        $wants = [];

        foreach ($discovery->refs() as $refName => $refId) {
            $wants[] = $refId;
        }

        if ($wants === []) {
            return $repo;
        }

        // Deduplicate wants
        $seen = [];
        $uniqueWants = [];

        foreach ($wants as $want) {
            if (!isset($seen[$want->hex])) {
                $seen[$want->hex] = true;
                $uniqueWants[] = $want;
            }
        }

        // Fetch pack
        $packData = $uploadPack->fetch($url, $uniqueWants);

        if ($packData !== '' && str_starts_with($packData, 'PACK')) {
            $packDir = $gitDir . '/objects/pack';

            if (!is_dir($packDir)) {
                mkdir($packDir, 0777, true);
            }

            $hash = sha1($packData);
            file_put_contents($packDir . "/pack-{$hash}.pack", $packData);
        }

        // Set up refs
        foreach ($discovery->refs() as $refName => $refId) {
            if (str_starts_with($refName, 'refs/heads/')) {
                $branch = substr($refName, 11);
                $repo->refDatabase()->update("refs/remotes/origin/{$branch}", $refId);
            } elseif (str_starts_with($refName, 'refs/tags/')) {
                $repo->refDatabase()->update($refName, $refId);
            }
        }

        // Index the pack so Pitmaster can read it
        if ($packData !== '' && str_starts_with($packData, 'PACK')) {
            $idxPackDir = $gitDir . '/objects/pack';
            $idxHash = sha1($packData);
            $packFile = $idxPackDir . "/pack-{$idxHash}.pack";
            exec(sprintf('git index-pack %s 2>/dev/null', escapeshellarg($packFile)));
        }

        // Set HEAD to default branch
        $headRef = $discovery->headSymref() ?? 'refs/heads/main';
        $headId = $discovery->ref($headRef) ?? $discovery->ref('HEAD');

        if ($headId !== null) {
            if (str_starts_with($headRef, 'refs/heads/')) {
                $repo->refDatabase()->update($headRef, $headId);
                $repo->refDatabase()->updateSymbolic('HEAD', $headRef);
            }
        }

        // Materialize working tree: checkout HEAD into the work dir
        $repo = self::open($path); // Re-open to pick up pack index

        try {
            $head = $repo->head();
            $treeMap = self::flattenTreeStatic($repo, $head->tree);

            foreach ($treeMap as $filePath => $blobHash) {
                $blob = $repo->readObject($blobHash);

                if ($blob instanceof \Pitmaster\Object\Blob) {
                    $fullPath = $path . '/' . $filePath;
                    $dir = dirname($fullPath);

                    if (!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }

                    file_put_contents($fullPath, $blob->content);
                }
            }
        } catch (\Throwable) {
            // Clone succeeded but checkout failed; repo is still valid
        }

        return $repo;
    }

    // -- Detection helpers --

    /**
     * Check if a path is a git repository (regular or linked worktree).
     */
    public static function isRepository(string $path): bool
    {
        return is_dir($path . '/.git')
            || is_file($path . '/.git')
            || is_file($path . '/HEAD');
    }

    /**
     * Check if a path is a linked worktree (not the main repo).
     */
    public static function isWorktree(string $path): bool
    {
        if (!is_file($path . '/.git')) {
            return false;
        }

        $content = trim((string) file_get_contents($path . '/.git'));

        return str_starts_with($content, 'gitdir: ');
    }

    /**
     * Resolve the common git dir from any checkout path.
     */
    public static function commonGitDir(string $path): ?string
    {
        try {
            $repo = new Repository($path);

            return $repo->commonGitDir();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string> path => hex hash
     */
    private static function flattenTreeStatic(Repository $repo, \Pitmaster\Object\ObjectId $treeId, string $prefix = ''): array
    {
        $result = [];
        $tree = $repo->readObject($treeId->hex);

        if (!$tree instanceof \Pitmaster\Object\Tree) {
            return $result;
        }

        foreach ($tree->entries as $entry) {
            $fullPath = $prefix !== '' ? $prefix . '/' . $entry->name : $entry->name;

            if ($entry->isTree()) {
                $result = array_merge($result, self::flattenTreeStatic($repo, $entry->hash, $fullPath));
            } else {
                $result[$fullPath] = $entry->hash->hex;
            }
        }

        return $result;
    }
}
