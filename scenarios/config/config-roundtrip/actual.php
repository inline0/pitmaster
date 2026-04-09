<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Config\GitConfig;

$config = GitConfig::parse('');
$config->set('core.filemode', 'false');
$config->set('core.logallrefupdates', 'true');
$config->set('alias.lg', 'log --oneline');
$config->set('remote.origin.url', 'https://example.com/repo.git');
$config->append('remote.origin.fetch', '+refs/heads/*:refs/remotes/origin/*');
$config->append('remote.origin.fetch', '^refs/heads/tmp');
$config->set('branch.main.merge', 'refs/heads/main');
$config->writeToFile(getcwd() . '/written.config');
