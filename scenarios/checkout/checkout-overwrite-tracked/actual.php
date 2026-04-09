<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());

try {
    $repo->checkout('feature');
    file_put_contents(getcwd() . '/.checkout-result.txt', "allowed\n");

    throw new RuntimeException('Expected checkout to reject tracked overwrite');
} catch (RuntimeException $e) {
    file_put_contents(getcwd() . '/.checkout-result.txt', "blocked\n");
}
