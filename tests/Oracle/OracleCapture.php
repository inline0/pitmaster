<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Oracle;

use Pitmaster\Support\Json;
use RuntimeException;

/**
 * Runs git commands to capture oracle output for a scenario.
 *
 * The repo must already be set up (setup.sh already executed).
 */
final class OracleCapture
{
    /**
     * Capture oracle output for a scenario by running setup.sh in a fresh temp dir.
     *
     * @return array{success: bool, outputs: array<string, mixed>, errors: array<int, string>}
     */
    public function capture(Scenario $scenario): array
    {
        $tempDir = sys_get_temp_dir() . '/pitmaster-oracle-' . bin2hex(random_bytes(8));
        mkdir($tempDir, 0777, true);

        try {
            $this->runSetup($scenario, $tempDir);

            return $this->captureFromRepo($scenario, $tempDir);
        } finally {
            exec(sprintf('rm -rf %s', escapeshellarg($tempDir)));
        }
    }

    /**
     * Capture oracle output from an already-set-up repo directory.
     *
     * @return array{success: bool, outputs: array<string, mixed>, errors: array<int, string>}
     */
    public function captureFromRepo(Scenario $scenario, string $repoDir): array
    {
        $errors = [];
        $outputs = [];

        try {
            // Always capture these baseline outputs
            $outputs['objects'] = $this->captureObjects($repoDir);
            $outputs['refs'] = $this->captureRefs($repoDir);
            $outputs['log'] = $this->captureLog($repoDir);
            $outputs['fsck'] = $this->captureFsck($repoDir);

            // Capture operation-specific outputs
            foreach ($scenario->operations() as $operation) {
                $command = $scenario->oracleCommands()[$operation] ?? null;

                if ($command !== null) {
                    $outputs[$operation] = $this->runGitCommand($repoDir, $command);
                }
            }

            // Write to oracle directory
            $oracleDir = $scenario->oracleDir();

            if (!is_dir($oracleDir)) {
                mkdir($oracleDir, 0777, true);
            }

            if (isset($outputs['objects'])) {
                Json::encodeFile($oracleDir . '/objects.json', $outputs['objects']);
            }

            if (isset($outputs['refs'])) {
                Json::encodeFile($oracleDir . '/refs.json', $outputs['refs']);
            }

            if (isset($outputs['log'])) {
                Json::encodeFile($oracleDir . '/log.json', $outputs['log']);
            }

            if (isset($outputs['fsck'])) {
                file_put_contents($oracleDir . '/fsck.txt', $outputs['fsck']);
            }

            // Write operation-specific text outputs
            foreach ($scenario->operations() as $operation) {
                if (isset($outputs[$operation]) && is_string($outputs[$operation])) {
                    file_put_contents(
                        $oracleDir . '/' . $operation . '.txt',
                        $outputs[$operation]
                    );
                }
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

    /**
     * @return array<int, array{hash: string, type: string, size: int, content: string}>
     */
    private function captureObjects(string $repoDir): array
    {
        $command = sprintf(
            'cd %s && git cat-file --batch-all-objects --batch-check="%%(objectname) %%(objecttype) %%(objectsize)" 2>&1',
            escapeshellarg($repoDir),
        );

        $hashOutput = [];
        exec($command, $hashOutput, $exitCode);

        if ($exitCode !== 0) {
            return [];
        }

        $objects = [];

        foreach ($hashOutput as $line) {
            $parts = explode(' ', trim($line), 3);

            if (count($parts) !== 3) {
                continue;
            }

            [$hash, $type, $size] = $parts;

            $contentCommand = sprintf(
                'cd %s && git cat-file -p %s 2>&1',
                escapeshellarg($repoDir),
                escapeshellarg($hash),
            );

            $contentOutput = shell_exec($contentCommand) ?? '';

            $objects[] = [
                'hash' => $hash,
                'type' => $type,
                'size' => (int) $size,
                'content' => $contentOutput,
            ];
        }

        return $objects;
    }

    /**
     * @return array<int, array{hash: string, name: string}>
     */
    private function captureRefs(string $repoDir): array
    {
        $command = sprintf(
            'cd %s && git for-each-ref --format="%%(objectname) %%(refname)" 2>&1',
            escapeshellarg($repoDir),
        );

        $output = [];
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            return [];
        }

        $refs = [];

        foreach ($output as $line) {
            $parts = explode(' ', trim($line), 2);

            if (count($parts) !== 2) {
                continue;
            }

            $refs[] = ['hash' => $parts[0], 'name' => $parts[1]];
        }

        return $refs;
    }

    /**
     * @return array<int, array{hash: string, tree: string, parents: array<int, string>, author: string, committer: string, message: string}>
     */
    private function captureLog(string $repoDir): array
    {
        $command = sprintf(
            'cd %s && git log --all --format="%%H%%n%%T%%n%%P%%n%%an <%%ae> %%at %%ai%%n%%cn <%%ce> %%ct %%ci%%n%%B%%x00" 2>&1',
            escapeshellarg($repoDir),
        );

        $raw = shell_exec($command) ?? '';

        if (trim($raw) === '') {
            return [];
        }

        $commits = [];
        $entries = explode("\0", $raw);

        foreach ($entries as $entry) {
            $entry = trim($entry);

            if ($entry === '') {
                continue;
            }

            $lines = explode("\n", $entry);

            if (count($lines) < 5) {
                continue;
            }

            $hash = trim($lines[0]);
            $tree = trim($lines[1]);
            $parentLine = trim($lines[2]);
            $parents = $parentLine !== '' ? explode(' ', $parentLine) : [];
            $author = trim($lines[3]);
            $committer = trim($lines[4]);
            $message = trim(implode("\n", array_slice($lines, 5)));

            $commits[] = [
                'hash' => $hash,
                'tree' => $tree,
                'parents' => $parents,
                'author' => $author,
                'committer' => $committer,
                'message' => $message,
            ];
        }

        return $commits;
    }

    private function captureFsck(string $repoDir): string
    {
        $command = sprintf(
            'cd %s && git fsck --strict --no-progress 2>&1',
            escapeshellarg($repoDir),
        );

        return shell_exec($command) ?? '';
    }

    private function runGitCommand(string $repoDir, string $command): string
    {
        $fullCommand = sprintf(
            'cd %s && %s 2>&1',
            escapeshellarg($repoDir),
            $command,
        );

        return shell_exec($fullCommand) ?? '';
    }
}
