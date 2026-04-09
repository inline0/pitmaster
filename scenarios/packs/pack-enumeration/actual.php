<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pack\PackEnumerator;
use Pitmaster\Pack\PackFile;

$base = getenv('PITMASTER_ROOT') . '/fixtures/upstream/dulwich/testdata/packs/pack-bc63ddad95e7321ee734ea11a7a62d314e0d7481';
$enumerator = new PackEnumerator(PackFile::open($base . '.pack', $base . '.idx'));
$hashes = [];

foreach ($enumerator->enumerate() as $object) {
    $hashes[] = $object->id->hex;
}

sort($hashes);
file_put_contents(getcwd() . '/.pack-state', implode("\n", $hashes) . "\n");
