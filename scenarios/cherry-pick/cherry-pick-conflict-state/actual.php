<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());

try {
    $repo->cherryPick(trim((string) file_get_contents(getcwd() . '/.pick-id')));
} catch (RuntimeException) {
}
