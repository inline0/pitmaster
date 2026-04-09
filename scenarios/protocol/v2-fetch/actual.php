<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Protocol\ProtocolV2;
use Pitmaster\Protocol\SmartHttpClient;
use Pitmaster\Protocol\UploadPackClient;

$url = trim((string) file_get_contents(getcwd() . '/.remote-url'));
$client = new SmartHttpClient();
$discovery = $client->discoverRefsV2($url);
$uploadPack = new UploadPackClient($client);
$packData = $uploadPack->fetchV2($url, array_values($discovery->refs()));
$body = ProtocolV2::buildFetchRequest(array_values($discovery->refs()));
$lines = normalize_v2_body($body);
file_put_contents(getcwd() . '/.v2-fetch-lines', implode("\n", $lines) . "\n");
file_put_contents(getcwd() . '/.pack-header', substr($packData, 0, 4) . "\n");

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
