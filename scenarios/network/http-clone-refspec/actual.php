<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$url = trim((string) file_get_contents(getcwd() . '/.remote-url'));
$cloneDir = getcwd() . '/pit-clone';
$repo = Pitmaster::clone($url, $cloneDir);
$config = $repo->config();
$config->set('remote.origin.fetch', '+refs/heads/main:refs/remotes/origin/main');
$config->writeToFile($cloneDir . '/.git/config');
$sourceDir = getcwd() . '/source';
$remoteDir = getcwd() . '/projects/remote.git';

git('remote set-url origin ' . escapeshellarg($remoteDir), $sourceDir);
git('config user.email test@pitmaster.dev', $sourceDir);
git('config user.name "Test User"', $sourceDir);
file_put_contents($sourceDir . '/main.txt', "main branch\n");
git('add main.txt', $sourceDir);
git('commit -m main-update', $sourceDir);
git('push origin main', $sourceDir);
git('checkout -b feature', $sourceDir);
file_put_contents($sourceDir . '/feature.txt', "feature branch\n");
git('add feature.txt', $sourceDir);
git('commit -m feature-update', $sourceDir);
git('push origin feature', $sourceDir);
git('checkout main', $sourceDir);

$repo->fetch();

$lines = [
    'remote.origin.fetch=' . trim(git('config --get remote.origin.fetch', $cloneDir)),
    'branch.main.remote=' . trim(git('config --get branch.main.remote', $cloneDir)),
    'branch.main.merge=' . trim(git('config --get branch.main.merge', $cloneDir)),
    'origin.main=' . trim(git('rev-parse refs/remotes/origin/main', $cloneDir)),
    'origin.feature=' . (is_file($cloneDir . '/.git/refs/remotes/origin/feature') ? 'present' : 'absent'),
];

file_put_contents(getcwd() . '/.clone-refspec-state', implode("\n", $lines) . "\n");

function git(string $command, string $dir): string
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command), $output, $exitCode);
    $result = implode("\n", $output);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed in {$dir}:\n{$result}");
    }

    return $result;
}
