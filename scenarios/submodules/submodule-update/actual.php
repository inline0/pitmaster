<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;
use Pitmaster\Submodule\SubmoduleManager;

$depDir = getcwd() . '/dep';
$superDir = getcwd() . '/super';
$cloneDir = getcwd() . '/pit-clone';

git('init --initial-branch=main ' . escapeshellarg($depDir), getcwd());
git('config user.email test@pitmaster.dev', $depDir);
git('config user.name "Test User"', $depDir);
file_put_contents($depDir . '/dep.txt', "dependency\n");
git('add dep.txt', $depDir);
git('commit -m dep', $depDir);

git('init --initial-branch=main ' . escapeshellarg($superDir), getcwd());
git('config user.email test@pitmaster.dev', $superDir);
git('config user.name "Test User"', $superDir);
git('-c protocol.file.allow=always submodule add ' . escapeshellarg($depDir) . ' vendor/lib', $superDir);
git('commit -am "Add submodule"', $superDir);
git('clone ' . escapeshellarg($superDir) . ' ' . escapeshellarg($cloneDir), getcwd());

$repo = Pitmaster::open($cloneDir);
$manager = new SubmoduleManager($repo->objectDatabase(), $repo->workDir(), $repo->gitDir());
$manager->init();
$manager->update($repo->head()->tree);

file_put_contents(
    getcwd() . '/.submodule-status.txt',
    trim(preg_replace('/^[ +-]?[0-9a-f]{40} /', '', git('submodule status --cached', $cloneDir))) . "\n",
);
file_put_contents(getcwd() . '/.submodule-head.txt', git('show HEAD:dep.txt', $cloneDir . '/vendor/lib'));
file_put_contents(getcwd() . '/.submodule-branch.txt', git('branch --show-current', $cloneDir . '/vendor/lib'));

function git(string $command, string $dir): string
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command), $output, $exitCode);
    $result = implode("\n", $output);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed in {$dir}:\n{$result}");
    }

    return $result . ($result === '' ? '' : "\n");
}
