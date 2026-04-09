<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Config\GitConfig;

$config = GitConfig::fromFile($argv[1]);

$lines = [
    'core.filemode=' . ($config->get('core.filemode') ?? ''),
    'core.logallrefupdates=' . ($config->get('core.logallrefupdates') ?? ''),
    'alias.lg=' . ($config->get('alias.lg') ?? ''),
    'remote.origin.url=' . ($config->get('remote.origin.url') ?? ''),
    'remote.origin.fetch=' . implode('|', $config->getAll('remote.origin.fetch')),
    'branch.main.merge=' . ($config->get('branch.main.merge') ?? ''),
];

echo implode("\n", $lines) . "\n";
