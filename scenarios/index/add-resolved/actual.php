<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

Pitmaster\Pitmaster::open(getcwd())->addResolved();
