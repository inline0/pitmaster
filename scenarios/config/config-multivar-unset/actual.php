<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Config\GitConfig;

$config = GitConfig::fromFile(getcwd() . '/.git/config');
$config->unset('alias.drop');
$config->append('remote.origin.fetch', 'refs/tags/*:refs/tags/*');
$config->writeToFile(getcwd() . '/rewritten.config');
