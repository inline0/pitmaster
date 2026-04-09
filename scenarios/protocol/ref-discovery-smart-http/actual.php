<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Protocol\RefDiscovery;
use Pitmaster\Protocol\SmartHttpClient;

$url = trim((string) file_get_contents(getcwd() . '/.remote-url'));
$client = new SmartHttpClient();
$discovery = $client->discoverRefs($url);
file_put_contents(getcwd() . '/.discovery-state', serialize_discovery($discovery));

function serialize_discovery(RefDiscovery $discovery): string
{
    $lines = [
        'head=' . ($discovery->headSymref() ?? '<detached>'),
    ];

    $capabilities = $discovery->capabilities()?->all() ?? [];
    ksort($capabilities);

    foreach ($capabilities as $name => $value) {
        if ($name === 'agent' && $value !== null) {
            $value = '<normalized>';
        }

        $lines[] = 'cap ' . $name . ($value !== null ? '=' . $value : '');
    }

    $refs = $discovery->refs();
    ksort($refs);

    foreach ($refs as $name => $id) {
        $lines[] = 'ref ' . $name . '=' . $id->hex;
    }

    return implode("\n", $lines) . "\n";
}
