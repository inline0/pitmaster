<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;
use Pitmaster\Submodule\SubmoduleManager;

$repo = Pitmaster::open(getcwd());
$manager = new SubmoduleManager($repo->objectDatabase(), $repo->workDir(), $repo->gitDir());
$list = $manager->list();
$status = $manager->status($repo->head()->tree);

file_put_contents(getcwd() . '/.submodule-list.txt', sprintf("%s|%s\n", $list[0]->path, $list[0]->url));
file_put_contents(
    getcwd() . '/.submodule-status.txt',
    sprintf(
        "%s|%s|%s|%s\n",
        $status[0]['expected'],
        $status[0]['actual'],
        $status[0]['dirty'] ? 'true' : 'false',
        $status[0]['path'],
    ),
);
