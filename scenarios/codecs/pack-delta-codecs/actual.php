<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Encoding\BinaryReader;
use Pitmaster\Encoding\Leb128;
use Pitmaster\Encoding\VarInt;

$indexPath = glob(getcwd() . '/.git/objects/pack/*.idx')[0] ?? null;
$packPath = glob(getcwd() . '/.git/objects/pack/*.pack')[0] ?? null;
$entries = [];
exec('git verify-pack -v ' . escapeshellarg((string) $indexPath) . ' 2>/dev/null', $output, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "git verify-pack failed\n");
    exit($exitCode);
}

foreach ($output as $line) {
    if (preg_match('/^([0-9a-f]+)\s+\w+\s+(\d+)\s+\d+\s+(\d+)(?:\s+\d+\s+([0-9a-f]+))?$/', trim($line), $matches) !== 1) {
        continue;
    }

    $entries[$matches[1]] = [
        'hash' => $matches[1],
        'size' => (int) $matches[2],
        'offset' => (int) $matches[3],
        'baseHash' => isset($matches[4]) && $matches[4] !== '' ? $matches[4] : null,
    ];
}

$delta = null;
foreach ($entries as $entry) {
    if ($entry['baseHash'] !== null) {
        $delta = $entry;
        break;
    }
}

if ($delta === null) {
    fwrite(STDERR, "no delta entry found\n");
    exit(1);
}

$reader = new BinaryReader((string) file_get_contents((string) $packPath));
$reader->seek($delta['offset']);
$firstByte = $reader->readByte();
$size = ($firstByte & 0x80) !== 0
    ? VarInt::decodePackSize($reader, $firstByte & 0x0F)
    : ($firstByte & 0x0F);
$distance = VarInt::decodeOfsOffset($reader);
$baseOffset = $delta['offset'] - $distance;

$context = inflate_init(ZLIB_ENCODING_RAW);
$inflated = @inflate_add($context, substr($reader->rawData(), $reader->position()), ZLIB_FINISH);
if ($inflated === false) {
    $inflated = zlib_decode(substr($reader->rawData(), $reader->position()));
}

$deltaReader = new BinaryReader((string) $inflated);
$baseSize = Leb128::decodeUnsigned($deltaReader);
$resultSize = Leb128::decodeUnsigned($deltaReader);

$payload = [
    'size' => $size,
    'baseOffset' => $baseOffset,
    'baseSize' => $baseSize,
    'resultSize' => $resultSize,
];

file_put_contents(getcwd() . '/.codec.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
