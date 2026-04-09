<?php

declare(strict_types=1);

namespace Pitmaster;

use Pitmaster\Exceptions\ProtocolException;
use Pitmaster\Object\Commit;
use Pitmaster\Object\ObjectId;
use Pitmaster\Object\Tag;
use Pitmaster\Object\Tree;
use Pitmaster\Pack\PackIndexer;
use Pitmaster\Protocol\DumbHttpClient;
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

        foreach (
            [
            'hooks',
            'info',
            'objects/info',
            'objects/pack',
            'refs/heads',
            'refs/tags',
            ] as $dir
        ) {
            mkdir($gitDir . '/' . $dir, 0777, true);
        }

        file_put_contents($gitDir . '/HEAD', "ref: refs/heads/main\n");
        file_put_contents(
            $gitDir . '/description',
            "Unnamed repository; edit this file 'description' to name the repository.\n",
        );
        file_put_contents($gitDir . '/info/exclude', implode("\n", [
            '# git ls-files --others --exclude-from=.git/info/exclude',
            "# Lines that start with '#' are comments.",
            '# For a project mostly in C, the following would be a good set of',
            '# exclude patterns (uncomment them if you want to use them):',
            '# *.[oa]',
            '# *~',
            '',
        ]));
        file_put_contents($gitDir . '/config', self::initialConfig($path));

        return new Repository($path);
    }

    /**
     * Clone a remote repository via smart HTTP.
     */
    public static function clone(string $url, string $path): Repository
    {
        $pathExisted = file_exists($path);

        try {
            $repo = self::init($path);
            $gitDir = $path . '/.git';

            $config = $repo->config();
            $config->set('remote.origin.url', $url);
            $config->set('remote.origin.fetch', '+refs/heads/*:refs/remotes/origin/*');
            $config->writeToFile($gitDir . '/config');

            try {
                $http = new SmartHttpClient();
                $discovery = $http->discoverRefs($url);
                $uploadPack = new UploadPackClient($http);

                $wants = [];

                foreach ($discovery->refs() as $refName => $refId) {
                    $wants[] = $refId;
                }

                if ($wants !== []) {
                    $seen = [];
                    $uniqueWants = [];

                    foreach ($wants as $want) {
                        if (!isset($seen[$want->hex])) {
                            $seen[$want->hex] = true;
                            $uniqueWants[] = $want;
                        }
                    }

                    $packData = $uploadPack->fetch($url, $uniqueWants);

                    if ($packData !== '' && str_starts_with($packData, 'PACK')) {
                        self::writePackFile($gitDir, $packData);
                    }
                }

                self::applyRemoteRefs($repo, $discovery->refs());

                return self::finalizeCloneCheckout(
                    $repo,
                    $path,
                    $gitDir,
                    $discovery->headSymref() ?? 'refs/heads/main',
                    $discovery->ref($discovery->headSymref() ?? 'refs/heads/main') ?? $discovery->ref('HEAD'),
                );
            } catch (ProtocolException $smartError) {
                try {
                    $dumb = new DumbHttpClient();
                    $refs = $dumb->fetchRefs($url);

                    if ($refs === []) {
                        return $repo;
                    }

                    self::importDumbHttpObjects($repo, $dumb, $url, $refs);
                    self::applyRemoteRefs($repo, $refs);

                    $headRef = trim($dumb->fetchHead($url));
                    $headRef = str_starts_with($headRef, 'ref: ') ? substr($headRef, 5) : 'refs/heads/main';
                    $headId = $refs[$headRef] ?? null;

                    return self::finalizeCloneCheckout($repo, $path, $gitDir, $headRef, $headId);
                } catch (ProtocolException) {
                    throw $smartError;
                }
            }
        } catch (\Throwable $e) {
            if (!$pathExisted && file_exists($path)) {
                exec('rm -rf ' . escapeshellarg($path));
            }

            throw $e;
        }
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

    private static function initialConfig(string $path): string
    {
        $lines = [
            '[core]',
            "\trepositoryformatversion = 0",
            "\tfilemode = true",
            "\tbare = false",
        ];

        if (self::isCaseInsensitiveFilesystem($path)) {
            $lines[] = "\tignorecase = true";
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            $lines[] = "\tprecomposeunicode = true";
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    private static function isCaseInsensitiveFilesystem(string $path): bool
    {
        $probe = $path . '/.git/.pitmaster-case-check-aBc';
        file_put_contents($probe, "x\n");

        try {
            return is_file($path . '/.git/.PITMASTER-CASE-CHECK-ABC');
        } finally {
            @unlink($probe);
        }
    }

    private static function writePackFile(string $gitDir, string $packData, ?string $packName = null): void
    {
        $packDir = $gitDir . '/objects/pack';

        if (!is_dir($packDir)) {
            mkdir($packDir, 0777, true);
        }

        $packName ??= 'pack-' . sha1($packData) . '.pack';
        $packFile = $packDir . '/' . $packName;
        file_put_contents($packFile, $packData);

        if (str_ends_with($packName, '.pack')) {
            $idxPath = substr($packFile, 0, -5) . '.idx';

            if (!is_file($idxPath)) {
                PackIndexer::writeIndex($packFile);
            }
        }
    }

    /**
     * @param array<string, ObjectId> $refs
     */
    private static function applyRemoteRefs(Repository $repo, array $refs): void
    {
        foreach ($refs as $refName => $refId) {
            if (str_starts_with($refName, 'refs/heads/')) {
                $branch = substr($refName, 11);
                $repo->refDatabase()->update("refs/remotes/origin/{$branch}", $refId);
            } elseif (str_starts_with($refName, 'refs/tags/') && !str_ends_with($refName, '^{}')) {
                $repo->refDatabase()->update($refName, $refId);
            }
        }
    }

    private static function finalizeCloneCheckout(
        Repository $repo,
        string $path,
        string $gitDir,
        string $headRef,
        ?ObjectId $headId,
    ): Repository {
        if ($headId !== null) {
            if (str_starts_with($headRef, 'refs/heads/')) {
                $repo->refDatabase()->update($headRef, $headId);
                $repo->refDatabase()->updateSymbolic('HEAD', $headRef);
                $branch = substr($headRef, 11);
                $config = $repo->config();
                $config->set("branch.{$branch}.remote", 'origin');
                $config->set("branch.{$branch}.merge", $headRef);
                $config->writeToFile($gitDir . '/config');
            } else {
                $repo->refDatabase()->update('HEAD', $headId);
            }
        }

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
     * @param array<string, ObjectId> $refs
     */
    private static function importDumbHttpObjects(
        Repository $repo,
        DumbHttpClient $client,
        string $url,
        array $refs,
    ): void {
        $packs = $client->fetchPackList($url);

        if ($packs !== []) {
            foreach ($packs as $packName) {
                self::writePackFile($repo->gitDir(), $client->fetchPack($url, $packName), $packName);
                $idxName = preg_replace('/\.pack$/', '.idx', $packName) ?? $packName . '.idx';
                self::writePackFile($repo->gitDir(), $client->fetchPack($url, $idxName), $idxName);
            }

            $repo->objectDatabase()->packStore()->refresh();

            return;
        }

        $seen = [];

        foreach ($refs as $id) {
            self::downloadReachableObject($repo, $client, $url, $id, $seen);
        }
    }

    /**
     * @param array<string, bool> $seen
     */
    private static function downloadReachableObject(
        Repository $repo,
        DumbHttpClient $client,
        string $url,
        ObjectId $id,
        array &$seen,
    ): void {
        if (isset($seen[$id->hex]) || $repo->objectDatabase()->exists($id)) {
            return;
        }

        $seen[$id->hex] = true;
        $repo->objectDatabase()->looseStore()->writeEncoded($id, $client->fetchObject($url, $id->hex));
        $object = $repo->objectDatabase()->read($id);

        if ($object instanceof Commit) {
            self::downloadReachableObject($repo, $client, $url, $object->tree, $seen);

            foreach ($object->parents as $parent) {
                self::downloadReachableObject($repo, $client, $url, $parent, $seen);
            }

            return;
        }

        if ($object instanceof Tree) {
            foreach ($object->entries as $entry) {
                self::downloadReachableObject($repo, $client, $url, $entry->hash, $seen);
            }

            return;
        }

        if ($object instanceof Tag) {
            self::downloadReachableObject($repo, $client, $url, $object->object, $seen);
        }
    }
}
