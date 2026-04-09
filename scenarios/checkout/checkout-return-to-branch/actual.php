<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());
$detachId = trim((string) file_get_contents(getcwd() . '/.detach-id'));
$repo->checkout($detachId);
$repo->checkout('main');
