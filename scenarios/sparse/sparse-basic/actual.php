<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Checkout\SparseCheckout;
use Pitmaster\Config\GitConfig;

$sparse = new SparseCheckout(getcwd() . '/.git');
$sparse->init();
$sparse->set(['src', 'docs']);

copy(getcwd() . '/.git/info/sparse-checkout', getcwd() . '/.sparse-file.txt');
file_put_contents(
    getcwd() . '/.sparse-config-worktree.json',
    json_encode(GitConfig::fromFile(getcwd() . '/.git/config.worktree')->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
);
