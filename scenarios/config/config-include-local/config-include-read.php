<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Config\GitConfig;

$config = GitConfig::fromFile(getcwd() . '/.git/config');

printf("core.editor=%s\n", (string) $config->get('core.editor'));
printf("alias.lg=%s\n", (string) $config->get('alias.lg'));
