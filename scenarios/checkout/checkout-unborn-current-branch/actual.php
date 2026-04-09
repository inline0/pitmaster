<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());

try {
    $repo->checkout('main');
    file_put_contents(getcwd() . '/.checkout-result.txt', "allowed\n");
} catch (RuntimeException) {
    file_put_contents(getcwd() . '/.checkout-result.txt', "blocked\n");
}
