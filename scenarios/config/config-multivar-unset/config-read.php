<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Config\GitConfig;

$config = GitConfig::fromFile($argv[1]);

$lines = [
    'alias.keep=' . ($config->get('alias.keep') ?? ''),
    'alias.drop=' . ($config->get('alias.drop') ?? ''),
    'remote.origin.url=' . ($config->get('remote.origin.url') ?? ''),
    'remote.origin.fetch=' . implode('|', $config->getAll('remote.origin.fetch')),
];

echo implode("\n", $lines) . "\n";
