<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Protocol\PktLine;

$stream = PktLine::encode("hello\n") . PktLine::delimiter() . PktLine::flush();
$payload = [
    'encoded' => PktLine::encode("hello\n"),
    'flush' => PktLine::flush(),
    'delimiter' => PktLine::delimiter(),
    'decoded' => PktLine::decode($stream),
];

file_put_contents(getcwd() . '/.pktline.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
