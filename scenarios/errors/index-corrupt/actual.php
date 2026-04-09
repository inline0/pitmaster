<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Exceptions\IndexParseException;
use Pitmaster\Pitmaster;

file_put_contents(getcwd() . '/.git/index', "not-an-index\n");

try {
    Pitmaster::open(getcwd())->index();
    $state = "index-corrupt=no\n";
} catch (IndexParseException) {
    $state = "index-corrupt=yes\n";
}

file_put_contents(getcwd() . '/.error-state', $state);
