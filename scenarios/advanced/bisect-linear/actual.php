<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());
$repo->bisectStart('bad', 'good');
$repo->bisectGood();
$repo->bisectBad();
