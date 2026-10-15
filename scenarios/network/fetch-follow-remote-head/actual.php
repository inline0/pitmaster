<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

Pitmaster\Pitmaster::open(getcwd())->fetch();
