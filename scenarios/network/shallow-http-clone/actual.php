<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$url = trim((string) file_get_contents(getcwd() . '/.remote-url'));
$cloneDir = getcwd() . '/pit-clone';

Pitmaster::clone($url, $cloneDir, 1);

$lines = [
    'is_shallow=' . trim(git('rev-parse --is-shallow-repository', $cloneDir)),
    'head=' . trim(git('rev-parse HEAD', $cloneDir)),
    'commit_count=' . trim(git('rev-list --count HEAD', $cloneDir)),
    'shallow=' . trim((string) file_get_contents($cloneDir . '/.git/shallow')),
];

file_put_contents(getcwd() . '/.shallow-state', implode("\n", $lines) . "\n");

function git(string $command, string $dir): string
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command), $output, $exitCode);
    $result = implode("\n", $output);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed in {$dir}:\n{$result}");
    }

    return $result;
}
