<?php

declare(strict_types=1);

namespace Pitmaster\Tests\Oracle;

use Pitmaster\Support\Json;
use Pitmaster\Tests\Support\Workspace;
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
        $tempDir = Workspace::createDirectory('pitmaster-oracle-');

        try {
            $this->runSetup($scenario, $tempDir);

            return $this->captureFromRepo($scenario, $tempDir, true);
        } finally {
            Workspace::remove($tempDir);
        }
    }

    /**
     * Capture oracle output from an already-set-up repo directory.
     *
     * @return array{success: bool, outputs: array<string, mixed>, errors: array<int, string>}
     */
    public function captureFromRepo(
        Scenario $scenario,
        string $repoDir,
        bool $runOracleScript = false,
        bool $persist = true,
    ): array {
        $errors = [];
        $outputs = [];

        try {
            if ($runOracleScript) {
                $this->runOracleScript($scenario, $repoDir);
            }

            // Always capture these baseline outputs
            $outputs['objects'] = $this->captureObjects($repoDir);
            $outputs['refs'] = $this->captureRefs($repoDir);
            $outputs['log'] = $this->captureLog($repoDir);
            $outputs['fsck'] = $this->captureFsck($repoDir);

            // Capture operation-specific outputs
            foreach ($scenario->operations() as $operation) {
                $command = $scenario->oracleCommands()[$operation] ?? null;

                if ($command !== null) {
                    $outputs[$operation] = $this->runCommand($scenario, $repoDir, $this->expandCommand($scenario, $command));
                }
            }

            // Write to oracle directory
            if ($persist) {
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

                foreach ($scenario->operations() as $operation) {
                    if (isset($outputs[$operation]) && is_string($outputs[$operation])) {
                        file_put_contents(
                            $oracleDir . '/' . $operation . '.txt',
                            $outputs[$operation]
                        );
                    }
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
            'cd %s && PITMASTER_ROOT=%s bash %s 2>&1',
            escapeshellarg($tempDir),
            escapeshellarg($scenario->rootPath),
            escapeshellarg($setupScript),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                "Setup script failed (exit {$exitCode}): " . implode("\n", $output)
            );
        }
    }

    private function runOracleScript(Scenario $scenario, string $repoDir): void
    {
        $oracleScript = $scenario->oracleScriptPath();

        if (!is_file($oracleScript)) {
            return;
        }

        $command = sprintf(
            'cd %s && PITMASTER_ROOT=%s bash %s 2>&1',
            escapeshellarg($repoDir),
            escapeshellarg($scenario->rootPath),
            escapeshellarg($oracleScript),
        );

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new RuntimeException(
                "Oracle script failed (exit {$exitCode}): " . implode("\n", $output)
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

            // Base64-encode binary content so JSON can handle it
            $isBinary = !mb_check_encoding($contentOutput, 'UTF-8')
                || str_contains(substr($contentOutput, 0, 8192), "\0");

            $objects[] = [
                'hash' => $hash,
                'type' => $type,
                'size' => (int) $size,
                'content' => $isBinary ? base64_encode($contentOutput) : $contentOutput,
                'encoding' => $isBinary ? 'base64' : 'utf-8',
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

    private function runCommand(Scenario $scenario, string $repoDir, string $command): string
    {
        $fullCommand = sprintf(
            'cd %s && PITMASTER_ROOT=%s %s 2>&1',
            escapeshellarg($repoDir),
            escapeshellarg($scenario->rootPath),
            $command,
        );

        return shell_exec($fullCommand) ?? '';
    }

    private function expandCommand(Scenario $scenario, string $command): string
    {
        return str_replace(
            ['{{ROOT}}', '{{SCENARIO_DIR}}'],
            [$scenario->rootPath, $scenario->scenarioDir()],
            $command,
        );
    }
}
