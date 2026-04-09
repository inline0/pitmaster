#!/usr/bin/env bash
set -euo pipefail

php <<'PHP'
<?php
$projectRoot = getcwd() . '/projects';
$sourceDir = getcwd() . '/source';
$remoteDir = $projectRoot . '/remote.git';
$cloneDir = getcwd() . '/git-clone';
mkdir($projectRoot, 0777, true);

git('init --initial-branch=main ' . escapeshellarg($sourceDir), getcwd());
git('init --bare --initial-branch=main ' . escapeshellarg($remoteDir), getcwd());
git('config user.email test@example.com', $sourceDir);
git('config user.name Test', $sourceDir);
git('config http.receivepack true', $remoteDir);
file_put_contents($sourceDir . '/README.md', "hello push parity\n");
git('add README.md', $sourceDir);
git('commit -m initial', $sourceDir);
git('remote add origin ' . escapeshellarg($remoteDir), $sourceDir);
git('push origin main', $sourceDir);
git('clone ' . escapeshellarg($remoteDir) . ' ' . escapeshellarg($cloneDir), getcwd());
git('config user.email test@example.com', $cloneDir);
git('config user.name Test', $cloneDir);

file_put_contents($sourceDir . '/remote.txt', "remote advance\n");
git('add remote.txt', $sourceDir);
git('commit -m remote-advance', $sourceDir);
git('push origin main', $sourceDir);

file_put_contents($cloneDir . '/local.txt', "local only\n");
git('add local.txt', $cloneDir);
git('commit -m "Pit local"', $cloneDir);

exec(sprintf('cd %s && git push origin main >/dev/null 2>&1', escapeshellarg($cloneDir)), $output, $exitCode);
file_put_contents(getcwd() . '/.rejected.txt', $exitCode === 0 ? "no\n" : "yes\n");
file_put_contents(getcwd() . '/.remote-tree.txt', git('ls-tree -r --full-tree refs/heads/main', $remoteDir));

function git(string $command, string $dir): string
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command), $output, $exitCode);
    $result = implode("\n", $output);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed in {$dir}:\n{$result}");
    }

    return $result . ($result === '' ? '' : "\n");
}
PHP
