<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$config = Pitmaster\Pitmaster::open(getcwd())->config();
file_put_contents('.git/conditional-values', implode("\n", $config->getAll('demo.value')) . "\n");
