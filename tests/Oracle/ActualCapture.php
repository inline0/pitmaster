<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Oracle;

use Pitmaster\Object\Blob;
use Pitmaster\Object\Commit;
use Pitmaster\Object\Tag;
use Pitmaster\Object\Tree;
use Pitmaster\Pitmaster;
use Pitmaster\Support\Json;
use Pitmaster\Tests\Support\Workspace;
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
        $tempDir = Workspace::createDirectory('pitmaster-actual-');

        try {
            $this->runSetup($scenario, $tempDir);

            return $this->captureFromRepo($scenario, $tempDir, true);
        } finally {
            Workspace::remove($tempDir);
        }
    }

    /**
     * Capture actual output from an already-set-up repo directory.
     *
     * @return array{success: bool, outputs: array<string, mixed>, errors: array<int, string>}
     */
    public function captureFromRepo(Scenario $scenario, string $repoDir, bool $runActualScript = false): array
    {
        $errors = [];
        $outputs = [];

        try {
            if ($runActualScript) {
                $this->runActualScript($scenario, $repoDir);
            }

            $repo = Pitmaster::open($repoDir);

            // Capture objects
            $outputs['objects'] = $this->captureObjects($repo);

            // Capture refs
            $outputs['refs'] = $this->captureRefs($repo);

            // Capture log
            $outputs['log'] = $this->captureLog($repo);

            // Capture git's validation of the repo that Pitmaster produced
            $outputs['fsck'] = $this->captureFsck($repoDir);

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

            if (isset($outputs['fsck'])) {
                file_put_contents($actualDir . '/fsck.txt', $outputs['fsck']);
            }

            foreach ($scenario->operations() as $operation) {
                $command = $scenario->actualCommands()[$operation] ?? null;

                if ($command === null) {
                    continue;
                }

                $capture = $this->runCommand($scenario, $repoDir, $this->expandCommand($scenario, $command));
                $outputs[$operation] = $capture['combined'];
                $outputs[$operation . '_meta'] = [
                    'exit_code' => $capture['exitCode'],
                    'stdout' => $capture['stdout'],
                    'stderr' => $capture['stderr'],
                ];
                file_put_contents($actualDir . '/' . $operation . '.txt', $capture['combined']);
                Json::encodeFile($actualDir . '/' . $operation . '.meta.json', $outputs[$operation . '_meta']);
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

                $isBinary = !mb_check_encoding($content, 'UTF-8')
                    || str_contains(substr($content, 0, 8192), "\0");

                $objects[] = [
                    'hash' => $hash,
                    'type' => $object->type->value,
                    'size' => $object->size(),
                    'content' => $isBinary ? base64_encode($content) : $content,
                    'encoding' => $isBinary ? 'base64' : 'utf-8',
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
        // Walk from ALL ref tips (like git log --all)
        $tips = [];

        foreach ($repo->allRefs() as $name => $hash) {
            $tips[] = \Pitmaster\Object\ObjectId::fromHex($hash);
        }

        // Also include HEAD
        $headId = $repo->refDatabase()->resolveHead();

        if ($headId !== null) {
            $tips[] = $headId;
        }

        if ($tips === []) {
            return [];
        }

        $walker = new \Pitmaster\Graph\CommitWalker($repo->objectDatabase());
        $allCommits = $walker->walkAll($tips, 1000);

        $commits = [];

        foreach ($allCommits as $commit) {
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
            'cd %s && PITMASTER_ROOT=%s GIT_CEILING_DIRECTORIES=%s bash %s 2>&1',
            escapeshellarg($tempDir),
            escapeshellarg($scenario->rootPath),
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

    private function captureFsck(string $repoDir): string
    {
        $command = sprintf(
            'cd %s && GIT_CEILING_DIRECTORIES=%s git fsck --strict --no-progress 2>&1',
            escapeshellarg($repoDir),
            escapeshellarg($repoDir),
        );

        return shell_exec($command) ?? '';
    }

    /**
     * @return array{stdout: string, stderr: string, exitCode: int, combined: string}
     */
    private function runCommand(Scenario $scenario, string $repoDir, string $command): array
    {
        return CommandCapture::run(
            $command,
            $repoDir,
            [
                'PITMASTER_ROOT' => $scenario->rootPath,
                'GIT_CEILING_DIRECTORIES' => $repoDir,
            ],
        );
    }

    private function expandCommand(Scenario $scenario, string $command): string
    {
        return str_replace(
            ['{{ROOT}}', '{{SCENARIO_DIR}}'],
            [$scenario->rootPath, $scenario->scenarioDir()],
            $command,
        );
    }

    private function runActualScript(Scenario $scenario, string $repoDir): void
    {
        $actualScript = $scenario->actualScriptPath();

        if (!is_file($actualScript)) {
            return;
        }

        $command = sprintf(
            'cd %s && PITMASTER_ROOT=%s GIT_CEILING_DIRECTORIES=%s %s %s 2>&1',
            escapeshellarg($repoDir),
            escapeshellarg($scenario->rootPath),
            escapeshellarg($repoDir),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($actualScript),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                "Actual script failed (exit {$exitCode}): " . implode("\n", $output)
            );
        }
    }
}
