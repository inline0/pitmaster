#!/usr/bin/env bash
set -euo pipefail

php <<'PHP'
<?php

$payload = [
    'encoded' => "000ahello\n",
    'flush' => '0000',
    'delimiter' => '0001',
    'decoded' => ['hello', false, null],
];

file_put_contents('.pktline.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
PHP
