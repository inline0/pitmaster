<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Object\ObjectId;
use Pitmaster\Protocol\PktLine;
use Pitmaster\Protocol\ProtocolV1;

$headHex = trim((string) file_get_contents(getcwd() . '/projects/remote.git/refs/heads/main'));
$body = ProtocolV1::buildFetchRequest([ObjectId::fromHex($headHex)]);
$lines = [];

foreach (PktLine::decode($body) as $line) {
    $normalized = $line === null ? '0000' : rtrim((string) $line, "\n");
    $lines[] = preg_replace('/agent=[^ ]+/', 'agent=<normalized>', $normalized) ?? $normalized;
}

file_put_contents(getcwd() . '/.upload-pack-lines', implode("\n", $lines) . "\n");
