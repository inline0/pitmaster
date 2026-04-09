<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pack\PackIndex;

$path = getenv('PITMASTER_ROOT') . '/fixtures/upstream/dulwich/testdata/packs/pack-bc63ddad95e7321ee734ea11a7a62d314e0d7481.idx';
$index = PackIndex::open($path);
file_put_contents(getcwd() . '/.pack-state', implode("\n", $index->allHashes()) . "\n");
