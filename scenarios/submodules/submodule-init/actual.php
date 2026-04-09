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

file_put_contents(
    getcwd() . '/.submodule-config.txt',
    normalizeConfig(git("config --file .git/config --get-regexp '^submodule\\.'", $cloneDir)),
);
file_put_contents(
    getcwd() . '/.submodule-gitdir.txt',
    is_file($cloneDir . '/vendor/lib/.git') ? "yes\n" : "no\n",
);

function git(string $command, string $dir): string
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command), $output, $exitCode);
    $result = implode("\n", $output);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed in {$dir}:\n{$result}");
    }

    return $result . ($result === '' ? '' : "\n");
}

function normalizeConfig(string $config): string
{
    return str_replace(getcwd(), '<root>', $config);
}
