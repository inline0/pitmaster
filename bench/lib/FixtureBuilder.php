<?php

declare(strict_types=1);

namespace Pitmaster\Bench;

final class FixtureBuilder
{
    private const VERSION = '2026-04-10-v1';

    private int $commitSequence = 0;

    public function __construct(private readonly string $root)
    {
    }

    public function fixturesRoot(): string
    {
        return $this->root . '/bench/fixtures';
    }

    public function reposRoot(): string
    {
        return $this->fixturesRoot() . '/repos';
    }

    /**
     * @return array<string, mixed>
     */
    public function ensure(string $name): array
    {
        $path = $this->repoPath($name);
        $manifestPath = $path . '/bench-manifest.json';

        if (is_file($manifestPath)) {
            $decoded = json_decode((string) file_get_contents($manifestPath), true);

            if (is_array($decoded) && ($decoded['version'] ?? null) === self::VERSION) {
                return $decoded;
            }
        }

        $this->build($name, $path);

        $decoded = json_decode((string) file_get_contents($manifestPath), true);

        if (!is_array($decoded)) {
            throw new \RuntimeException("Fixture manifest is missing or invalid: {$manifestPath}");
        }

        return $decoded;
    }

    public function repoPath(string $name): string
    {
        return $this->reposRoot() . '/' . $name;
    }

    private function build(string $name, string $path): void
    {
        BenchmarkFilesystem::ensureDirectory($this->reposRoot());
        BenchmarkFilesystem::removeDirectory($path);
        BenchmarkFilesystem::ensureDirectory($path);

        $manifest = match ($name) {
            'repo-small' => $this->buildRepoSmall($path),
            'repo-medium-clean' => $this->buildRepoMediumClean($path),
            'repo-medium-dirty' => $this->buildRepoMediumDirty($path),
            'repo-large-tree' => $this->buildRepoLargeTree($path),
            'history-long' => $this->buildHistoryLong($path),
            'rename-heavy' => $this->buildRenameHeavy($path),
            'objects-loose' => $this->buildObjectsLoose($path),
            'objects-packed' => $this->buildObjectsPacked($path),
            'objects-mixed' => $this->buildObjectsMixed($path),
            'refs-many' => $this->buildRefsMany($path),
            default => throw new \InvalidArgumentException("Unknown benchmark fixture: {$name}"),
        };

        $manifest['version'] = self::VERSION;
        $manifest['name'] = $name;
        BenchmarkFilesystem::writeFile($path . '/bench-manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRepoSmall(string $path): array
    {
        $this->initRepo($path);
        $paths = $this->seedFiles($path, 3, 5, 'small');
        $this->commitAll($path, 'Initial small tree');

        for ($i = 0; $i < 12; $i++) {
            $target = $paths[$i % count($paths)];
            file_put_contents($path . '/' . $target, $this->content("small-update-{$i}", 8), FILE_APPEND);
            $this->commitAll($path, "Update {$i}");
        }

        return $this->collectManifest($path, [
            'paths' => array_slice($paths, 0, 20),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRepoMediumClean(string $path): array
    {
        $this->initRepo($path);
        $paths = $this->seedFiles($path, 20, 10, 'medium');
        $this->commitAll($path, 'Initial medium tree');

        for ($i = 0; $i < 60; $i++) {
            foreach ($this->selectPaths($paths, $i * 3, 6) as $target) {
                file_put_contents($path . '/' . $target, $this->content("medium-update-{$i}-{$target}", 6), FILE_APPEND);
            }

            if ($i % 6 === 0) {
                $extra = sprintf('extras/extra-%03d.txt', $i);
                BenchmarkFilesystem::writeFile($path . '/' . $extra, $this->content("extra-{$i}", 10));
                $paths[] = $extra;
            }

            $this->commitAll($path, "Medium update {$i}");
        }

        $this->writeCommitGraph($path);

        return $this->collectManifest($path, [
            'paths' => array_slice($paths, 0, 120),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRepoMediumDirty(string $path): array
    {
        $base = $this->ensure('repo-medium-clean');
        BenchmarkFilesystem::copyDirectory($this->repoPath('repo-medium-clean'), $path);
        $paths = $base['paths'];

        foreach ($this->selectPaths($paths, 0, 20) as $target) {
            file_put_contents($path . '/' . $target, $this->content("dirty-{$target}", 5), FILE_APPEND);
        }

        foreach ($this->selectPaths($paths, 25, 10) as $target) {
            file_put_contents($path . '/' . $target, $this->content("staged-{$target}", 4), FILE_APPEND);
        }

        foreach ($this->selectPaths($paths, 60, 6) as $target) {
            @unlink($path . '/' . $target);
        }

        for ($i = 0; $i < 12; $i++) {
            BenchmarkFilesystem::writeFile(
                $path . sprintf('/untracked/untracked-%02d.txt', $i),
                $this->content("untracked-{$i}", 8),
            );
        }

        BenchmarkShell::git('add ' . implode(' ', array_map('escapeshellarg', $this->selectPaths($paths, 25, 10))), $path);

        return $this->collectManifest($path, [
            'paths' => array_slice($paths, 0, 120),
            'dirty' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRepoLargeTree(string $path): array
    {
        $this->initRepo($path);
        $paths = $this->seedFiles($path, 40, 20, 'large');
        $this->commitAll($path, 'Initial large tree');

        for ($i = 0; $i < 25; $i++) {
            foreach ($this->selectPaths($paths, $i * 11, 24) as $target) {
                file_put_contents($path . '/' . $target, $this->content("large-{$i}-{$target}", 4), FILE_APPEND);
            }

            $this->commitAll($path, "Large update {$i}");
        }

        return $this->collectManifest($path, [
            'paths' => array_slice($paths, 0, 200),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHistoryLong(string $path): array
    {
        $this->initRepo($path);
        BenchmarkFilesystem::writeFile($path . '/history.txt', "root\n");
        BenchmarkFilesystem::writeFile($path . '/src/app.txt', "app\n");
        $this->commitAll($path, 'Root history commit');

        for ($i = 0; $i < 180; $i++) {
            file_put_contents($path . '/history.txt', "main {$i}\n", FILE_APPEND);

            if ($i % 10 === 0) {
                BenchmarkFilesystem::writeFile($path . sprintf('/notes/note-%03d.txt', $i), $this->content("main-note-{$i}", 4));
            }

            $this->commitAll($path, "Main {$i}");
        }

        $branchPoint = trim(BenchmarkShell::git('rev-parse HEAD~120', $path));
        BenchmarkShell::git('checkout -b feature ' . escapeshellarg($branchPoint), $path);

        for ($i = 0; $i < 80; $i++) {
            file_put_contents($path . '/src/app.txt', "feature {$i}\n", FILE_APPEND);

            if ($i % 16 === 0) {
                BenchmarkShell::git('tag -a ' . escapeshellarg(sprintf('v0.%d', intdiv($i, 16) + 1)) . ' -m ' . escapeshellarg("Tag {$i}"), $path, $this->commitEnv());
            }

            $this->commitAll($path, "Feature {$i}");
        }

        $featureHead = trim(BenchmarkShell::git('rev-parse HEAD', $path));
        BenchmarkShell::git('checkout main', $path);

        for ($i = 0; $i < 90; $i++) {
            file_put_contents($path . '/history.txt', "tail {$i}\n", FILE_APPEND);
            $this->commitAll($path, "Tail {$i}");
        }

        $mainHead = trim(BenchmarkShell::git('rev-parse HEAD', $path));
        $mergeBase = trim(BenchmarkShell::git('merge-base ' . escapeshellarg($mainHead) . ' ' . escapeshellarg($featureHead), $path));
        $this->writeCommitGraph($path);

        return $this->collectManifest($path, [
            'main_head' => $mainHead,
            'feature_head' => $featureHead,
            'merge_base' => $mergeBase,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRenameHeavy(string $path): array
    {
        $this->initRepo($path);
        $paths = $this->seedFiles($path, 10, 10, 'rename');
        $this->commitAll($path, 'Initial rename tree');
        $fromCommit = trim(BenchmarkShell::git('rev-parse HEAD', $path));

        for ($i = 0; $i < 40; $i++) {
            $source = $paths[$i];
            $destination = sprintf('renamed/batch-%02d/%s', intdiv($i, 5), basename($source));
            BenchmarkFilesystem::ensureDirectory(dirname($path . '/' . $destination));
            rename($path . '/' . $source, $path . '/' . $destination);
            $paths[$i] = $destination;
        }

        foreach ($this->selectPaths($paths, 30, 25) as $target) {
            file_put_contents($path . '/' . $target, $this->content("rename-content-{$target}", 7), FILE_APPEND);
        }

        $this->commitAll($path, 'Rename heavy update');
        $toCommit = trim(BenchmarkShell::git('rev-parse HEAD', $path));

        return $this->collectManifest($path, [
            'paths' => array_slice($paths, 0, 100),
            'from_commit' => $fromCommit,
            'to_commit' => $toCommit,
            'from_tree' => trim(BenchmarkShell::git('rev-parse ' . escapeshellarg($fromCommit . '^{tree}'), $path)),
            'to_tree' => trim(BenchmarkShell::git('rev-parse ' . escapeshellarg($toCommit . '^{tree}'), $path)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildObjectsLoose(string $path): array
    {
        $this->initRepo($path);
        $paths = $this->seedFiles($path, 8, 8, 'objects');
        $this->commitAll($path, 'Initial object corpus');

        for ($i = 0; $i < 80; $i++) {
            foreach ($this->selectPaths($paths, $i * 2, 4) as $target) {
                file_put_contents($path . '/' . $target, $this->content("objects-{$i}-{$target}", 6), FILE_APPEND);
            }

            if ($i % 4 === 0) {
                $payloadPath = $path . sprintf('/.bench-loose-blob-%02d.tmp', $i);
                BenchmarkFilesystem::writeFile($payloadPath, $this->content("loose-blob-{$i}", 20));
                BenchmarkShell::git('hash-object -w ' . escapeshellarg($payloadPath), $path);
                @unlink($payloadPath);
            }

            $this->commitAll($path, "Objects {$i}");
        }

        return $this->collectManifest($path, [
            'paths' => array_slice($paths, 0, 64),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildObjectsPacked(string $path): array
    {
        $this->ensure('objects-loose');
        BenchmarkFilesystem::copyDirectory($this->repoPath('objects-loose'), $path);
        BenchmarkShell::git('gc --aggressive --prune=now', $path);
        $this->writeCommitGraph($path);

        return $this->collectManifest($path);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildObjectsMixed(string $path): array
    {
        $this->ensure('objects-packed');
        BenchmarkFilesystem::copyDirectory($this->repoPath('objects-packed'), $path);

        for ($i = 0; $i < 16; $i++) {
            BenchmarkFilesystem::writeFile(
                $path . sprintf('/mixed/loose-%02d.txt', $i),
                $this->content("mixed-{$i}", 10),
            );
            $this->commitAll($path, "Mixed {$i}");
        }

        return $this->collectManifest($path);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRefsMany(string $path): array
    {
        $history = $this->ensure('history-long');
        BenchmarkFilesystem::copyDirectory($this->repoPath('history-long'), $path);
        $commits = $history['commits'];

        for ($i = 0; $i < 120; $i++) {
            $target = $commits[$i % count($commits)];
            BenchmarkShell::git('branch ' . escapeshellarg(sprintf('bench/branch-%03d', $i)) . ' ' . escapeshellarg($target), $path);
        }

        for ($i = 0; $i < 120; $i++) {
            $target = $commits[($i * 2) % count($commits)];
            BenchmarkShell::git('tag ' . escapeshellarg(sprintf('bench-tag-%03d', $i)) . ' ' . escapeshellarg($target), $path);
        }

        BenchmarkShell::git('pack-refs --all', $path);

        return $this->collectManifest($path, [
            'refs' => $this->gitLines('for-each-ref --format=' . escapeshellarg('%(refname)'), $path),
        ]);
    }

    private function initRepo(string $path): void
    {
        BenchmarkShell::git('init --initial-branch=main ' . escapeshellarg($path), $this->root);
        BenchmarkShell::git('config user.name ' . escapeshellarg('Pitmaster Bench'), $path);
        BenchmarkShell::git('config user.email ' . escapeshellarg('bench@pitmaster.dev'), $path);
        BenchmarkShell::git('config commit.gpgSign false', $path);
        BenchmarkShell::git('config gc.auto 0', $path);
    }

    /**
     * @return list<string>
     */
    private function seedFiles(string $path, int $directoryCount, int $filesPerDirectory, string $prefix): array
    {
        $paths = [];

        for ($directory = 0; $directory < $directoryCount; $directory++) {
            for ($file = 0; $file < $filesPerDirectory; $file++) {
                $relative = sprintf('%s/dir-%02d/file-%03d.txt', $prefix, $directory, $file);
                BenchmarkFilesystem::writeFile($path . '/' . $relative, $this->content("{$prefix}-{$directory}-{$file}", 12));
                $paths[] = $relative;
            }
        }

        return $paths;
    }

    /**
     * @param list<string> $paths
     * @return list<string>
     */
    private function selectPaths(array $paths, int $offset, int $count): array
    {
        $selected = [];
        $pathCount = count($paths);

        for ($i = 0; $i < $count; $i++) {
            $selected[] = $paths[($offset + $i) % $pathCount];
        }

        return $selected;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectManifest(string $path, array $extra = []): array
    {
        $objects = [];

        foreach ($this->gitLines('rev-list --objects --all', $path) as $line) {
            $hex = strtok($line, ' ');

            if (is_string($hex) && preg_match('/^[0-9a-f]{40}$/', $hex) === 1) {
                $objects[] = $hex;
            }

            if (count($objects) >= 240) {
                break;
            }
        }

        return array_merge([
            'head' => trim(BenchmarkShell::git('rev-parse HEAD', $path)),
            'commits' => array_slice($this->gitLines('rev-list --max-count=240 --all', $path), 0, 240),
            'objects' => $objects,
        ], $extra);
    }

    private function commitAll(string $path, string $message): string
    {
        BenchmarkShell::git('add -A', $path);
        BenchmarkShell::git('commit -m ' . escapeshellarg($message), $path, $this->commitEnv());

        return trim(BenchmarkShell::git('rev-parse HEAD', $path));
    }

    /**
     * @return array<string, string>
     */
    private function commitEnv(): array
    {
        $timestamp = 1_704_067_200 + ($this->commitSequence++ * 60);
        $iso = gmdate('Y-m-d\TH:i:s', $timestamp) . ' +0000';

        return [
            'GIT_AUTHOR_NAME' => 'Pitmaster Bench',
            'GIT_AUTHOR_EMAIL' => 'bench@pitmaster.dev',
            'GIT_COMMITTER_NAME' => 'Pitmaster Bench',
            'GIT_COMMITTER_EMAIL' => 'bench@pitmaster.dev',
            'GIT_AUTHOR_DATE' => $iso,
            'GIT_COMMITTER_DATE' => $iso,
        ];
    }

    /**
     * @return list<string>
     */
    private function gitLines(string $arguments, string $cwd): array
    {
        $output = trim(BenchmarkShell::git($arguments, $cwd));

        if ($output === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $output)), static fn (string $line): bool => $line !== ''));
    }

    private function writeCommitGraph(string $path): void
    {
        try {
            BenchmarkShell::git('commit-graph write --reachable', $path);
        } catch (\Throwable) {
            // Older Git builds may not support every optimization helper. Benchmark fixtures remain usable.
        }
    }

    private function content(string $seed, int $lines): string
    {
        $content = [];

        for ($i = 0; $i < $lines; $i++) {
            $content[] = hash('sha256', "{$seed}:{$i}");
        }

        return implode("\n", $content) . "\n";
    }
}
