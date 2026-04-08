<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Protocol\Bundle;

$bundle = Bundle::open(getcwd() . '/source.bundle');
$bundle->writeTo(getcwd() . '/source.bundle');
