<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Pitmaster;
use Pitmaster\Object\ObjectId;
use Pitmaster\Protocol\ProtocolV1;

$url = trim((string) file_get_contents(getcwd() . '/.remote-url'));
$cloneDir = getcwd() . '/pit-clone';
$repo = Pitmaster::clone($url, $cloneDir);
file_put_contents($cloneDir . '/pit-push.txt', "pitmaster push\n");
$repo->add('pit-push.txt');
$newId = $repo->commit("Pitmaster push\n");
$oldHex = trim((string) file_get_contents(getcwd() . '/projects/remote.git/refs/heads/main'));
$prefix = ProtocolV1::buildPushRequest([
    [
        'old' => ObjectId::fromHex($oldHex),
        'new' => ObjectId::fromHex($newId->hex),
        'ref' => 'refs/heads/main',
    ],
]);
$lines = [];

foreach (decode_pkt_lines($prefix) as $line) {
    $normalized = $line === null ? '0000' : rtrim((string) $line, "\n");
    $normalized = str_replace("\0", '\\0', $normalized);
    $normalized = preg_replace(
        '/^[0-9a-f]{40} [0-9a-f]{40}/',
        '<old> <new>',
        $normalized,
    ) ?? $normalized;
    $lines[] = preg_replace('/agent=[^ ]+/', 'agent=<normalized>', $normalized) ?? $normalized;
}

file_put_contents(getcwd() . '/.receive-pack-lines', implode("\n", $lines) . "\n");

/**
 * @return array<int, string|null>
 */
function decode_pkt_lines(string $data): array
{
    $lines = [];
    $offset = 0;
    $length = strlen($data);

    while ($offset + 4 <= $length) {
        $hexLen = substr($data, $offset, 4);

        if (!ctype_xdigit($hexLen)) {
            break;
        }

        $lineLen = (int) hexdec($hexLen);

        if ($lineLen === 0) {
            $lines[] = null;
            $offset += 4;
            continue;
        }

        if ($lineLen < 4 || $offset + $lineLen > $length) {
            break;
        }

        $lines[] = substr($data, $offset + 4, $lineLen - 4);
        $offset += $lineLen;
    }

    return $lines;
}
