<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());
$repo->addWorktree(getcwd() . '/linked-feature', 'linked-feature', name: 'linked-feature');
