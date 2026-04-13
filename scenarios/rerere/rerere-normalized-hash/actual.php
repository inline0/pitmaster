<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Merge\Rerere;

$repoDir = getcwd();
$conflicted = (string) file_get_contents($repoDir . '/a.txt');
$rerere = new Rerere($repoDir . '/.git');
$rerere->record($conflicted, "line1\nresolved\n");
$hashes = $rerere->listRecorded();
sort($hashes);

if ($hashes === []) {
    throw new RuntimeException('Expected a recorded rerere hash');
}

file_put_contents($repoDir . '/.hash.txt', $hashes[0] . "\n");
