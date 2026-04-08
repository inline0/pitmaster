<?php

declare(strict_types=1);

namespace Pitmaster;

use Pitmaster\Pack\PackIndexer;
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
            $packFile = $packDir . "/pack-{$hash}.pack";
            file_put_contents($packFile, $packData);
            PackIndexer::writeIndex($packFile);
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

        // Set HEAD to default branch
        $headRef = $discovery->headSymref() ?? 'refs/heads/main';
        $headId = $discovery->ref($headRef) ?? $discovery->ref('HEAD');

        if ($headId !== null) {
            if (str_starts_with($headRef, 'refs/heads/')) {
                $repo->refDatabase()->update($headRef, $headId);
                $repo->refDatabase()->updateSymbolic('HEAD', $headRef);
            } else {
                $repo->refDatabase()->update('HEAD', $headId);
            }
        }

        // Materialize working tree and index.
        $repo = self::open($path);

        try {
            if ($headId !== null) {
                if (str_starts_with($headRef, 'refs/heads/')) {
                    $repo->checkout(substr($headRef, 11));
                } else {
                    $repo->checkout($headId->hex);
                }
            }
        } catch (\Throwable) {
            // Clone succeeded but checkout failed; repo is still valid
        }

        return $repo;
    }

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
}
