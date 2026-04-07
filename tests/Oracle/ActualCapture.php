<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Oracle;

use Pitmaster\Object\Blob;
use Pitmaster\Object\Commit;
use Pitmaster\Object\Tag;
use Pitmaster\Object\Tree;
use Pitmaster\Pitmaster;
use Pitmaster\Support\Json;
use RuntimeException;

/**
 * Runs Pitmaster operations on a scenario and captures output.
 *
 * The repo must already be set up (setup.sh already executed).
 */
final class ActualCapture
{
    /**
     * Capture actual output by running setup.sh in a fresh temp dir.
     *
     * @return array{success: bool, outputs: array<string, mixed>, errors: array<int, string>}
     */
    public function capture(Scenario $scenario): array
    {
        $tempDir = sys_get_temp_dir() . '/pitmaster-actual-' . bin2hex(random_bytes(8));
        mkdir($tempDir, 0777, true);

        try {
            $this->runSetup($scenario, $tempDir);

            return $this->captureFromRepo($scenario, $tempDir);
        } finally {
            exec(sprintf('rm -rf %s', escapeshellarg($tempDir)));
        }
    }

    /**
     * Capture actual output from an already-set-up repo directory.
     *
     * @return array{success: bool, outputs: array<string, mixed>, errors: array<int, string>}
     */
    public function captureFromRepo(Scenario $scenario, string $repoDir): array
    {
        $errors = [];
        $outputs = [];

        try {
            $repo = Pitmaster::open($repoDir);

            // Capture objects
            $outputs['objects'] = $this->captureObjects($repo);

            // Capture refs
            $outputs['refs'] = $this->captureRefs($repo);

            // Capture log
            $outputs['log'] = $this->captureLog($repo);

            // Write to actual directory
            $actualDir = $scenario->actualDir();

            if (!is_dir($actualDir)) {
                mkdir($actualDir, 0777, true);
            }

            if (isset($outputs['objects'])) {
                Json::encodeFile($actualDir . '/objects.json', $outputs['objects']);
            }

            if (isset($outputs['refs'])) {
                Json::encodeFile($actualDir . '/refs.json', $outputs['refs']);
            }

            if (isset($outputs['log'])) {
                Json::encodeFile($actualDir . '/log.json', $outputs['log']);
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        return [
            'success' => $errors === [],
            'outputs' => $outputs,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<int, array{hash: string, type: string, size: int, content: string}>
     */
    private function captureObjects(\Pitmaster\Repository $repo): array
    {
        $objects = [];

        foreach ($repo->listObjects() as $hash) {
            try {
                $object = $repo->readObject($hash);

                $content = match (true) {
                    $object instanceof Tree => $this->formatTreeContent($object),
                    default => $object->content,
                };

                $objects[] = [
                    'hash' => $hash,
                    'type' => $object->type->value,
                    'size' => $object->size(),
                    'content' => $content,
                ];
            } catch (\Throwable $e) {
                $objects[] = [
                    'hash' => $hash,
                    'type' => 'error',
                    'size' => 0,
                    'content' => $e->getMessage(),
                ];
            }
        }

        usort($objects, static fn (array $a, array $b): int => strcmp($a['hash'], $b['hash']));

        return $objects;
    }

    /**
     * Format tree content to match git cat-file -p output.
     */
    private function formatTreeContent(Tree $tree): string
    {
        $lines = [];

        foreach ($tree->entries as $entry) {
            $type = $entry->isTree() ? 'tree' : 'blob';
            $mode = str_pad($entry->mode, 6, '0', STR_PAD_LEFT);
            $lines[] = "{$mode} {$type} {$entry->hash->hex}\t{$entry->name}";
        }

        return implode("\n", $lines) . ($lines !== [] ? "\n" : '');
    }

    /**
     * @return array<int, array{hash: string, name: string}>
     */
    private function captureRefs(\Pitmaster\Repository $repo): array
    {
        $refs = [];

        foreach ($repo->allRefs() as $name => $hash) {
            $refs[] = ['hash' => $hash, 'name' => $name];
        }

        usort($refs, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $refs;
    }

    /**
     * @return array<int, array{hash: string, tree: string, parents: array<int, string>, author: string, committer: string, message: string}>
     */
    private function captureLog(\Pitmaster\Repository $repo): array
    {
        $commits = [];

        foreach ($repo->log(1000) as $commit) {
            $parents = array_map(
                static fn ($p) => $p->hex,
                $commit->parents,
            );

            $commits[] = [
                'hash' => $commit->id->hex,
                'tree' => $commit->tree->hex,
                'parents' => $parents,
                'author' => $commit->author,
                'committer' => $commit->committer,
                'message' => $commit->message,
            ];
        }

        return $commits;
    }

    private function runSetup(Scenario $scenario, string $tempDir): void
    {
        $setupScript = $scenario->setupScriptPath();

        if (!is_file($setupScript)) {
            throw new RuntimeException("Setup script not found: {$setupScript}");
        }

        $command = sprintf(
            'cd %s && bash %s 2>&1',
            escapeshellarg($tempDir),
            escapeshellarg($setupScript),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                "Setup script failed (exit {$exitCode}): " . implode("\n", $output)
            );
        }
    }
}
