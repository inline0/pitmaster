<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Pitmaster\Index\Index;

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php tests/Fixtures/index_extension_hex.php <index-path> <signature>\n");
    exit(1);
}

$index = Index::open($argv[1]);
$signature = $argv[2];

foreach ($index->extensions() as $extension) {
    if (($extension['signature'] ?? '') !== $signature) {
        continue;
    }

    echo bin2hex((string) ($extension['data'] ?? ''));
    exit(0);
}

exit(0);
