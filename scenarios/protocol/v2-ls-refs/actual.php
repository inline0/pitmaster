<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Protocol\ProtocolV2;
use Pitmaster\Protocol\SmartHttpClient;

$url = trim((string) file_get_contents(getcwd() . '/.remote-url'));
$client = new SmartHttpClient();
$client->discoverRefsV2($url);
$body = ProtocolV2::buildLsRefsRequest();
$lines = normalize_v2_body($body);
file_put_contents(getcwd() . '/.v2-ls-refs-lines', implode("\n", $lines) . "\n");

/**
 * @return list<string>
 */
function normalize_v2_body(string $body): array
{
    $lines = [];

    foreach (ProtocolV2::decode($body) as $packet) {
        if ($packet['type'] === 'data') {
            $line = $packet['payload'] ?? '';
            $line = preg_replace('/agent=[^ ]+/', 'agent=<normalized>', $line) ?? $line;
            $lines[] = $line;
            continue;
        }

        $lines[] = match ($packet['type']) {
            'delimiter' => '0001',
            'response-end' => '0002',
            default => '0000',
        };
    }

    return $lines;
}
