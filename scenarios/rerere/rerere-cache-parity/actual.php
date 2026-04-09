<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Merge\Rerere;

$repoDir = getcwd();
$conflicted = (string) file_get_contents($repoDir . '/a.txt');
$rerere = new Rerere($repoDir . '/.git');
$rerere->record($conflicted, "line1\nresolved\n");
file_put_contents($repoDir . '/a.txt', "line1\nresolved\n");
git('add a.txt');
git('rerere');
$hashes = $rerere->listRecorded();
sort($hashes);
$hash = $hashes[0] ?? null;

if ($hash === null) {
    throw new RuntimeException('Expected a recorded rerere entry');
}

file_put_contents($repoDir . '/.preimage.txt', (string) file_get_contents($repoDir . '/.git/rr-cache/' . $hash . '/preimage'));
file_put_contents($repoDir . '/.postimage.txt', (string) file_get_contents($repoDir . '/.git/rr-cache/' . $hash . '/postimage'));
file_put_contents($repoDir . '/.resolved.txt', (string) $rerere->resolve($conflicted));

function git(string $command): void
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg(getcwd()), $command), $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed:\n" . implode("\n", $output));
    }
}
