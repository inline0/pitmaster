<?php

declare(strict_types=1);

$files = glob(getcwd() . '/.captures/*git-receive-pack.body') ?: [];
usort($files, static fn (string $a, string $b): int => filesize($b) <=> filesize($a));
$body = $files !== [] ? file_get_contents($files[0]) : false;
$prefix = $body === false ? '' : substr($body, 0, strpos($body, 'PACK') ?: strlen($body));
$lines = [];

foreach (decode_pkt_lines($prefix) as $line) {
    $normalized = $line === null ? '0000' : rtrim((string) $line, "\n");
    $lines[] = preg_replace('/agent=[^ ]+/', 'agent=<normalized>', $normalized) ?? $normalized;
}

echo implode("\n", $lines) . "\n";

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
