<?php

declare(strict_types=1);

use Pitmaster\Protocol\PktLine;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (str_contains($_SERVER['REQUEST_URI'], '/info/refs')) {
    header('Content-Type: application/x-git-upload-pack-advertisement');
    echo PktLine::encode("# service=git-upload-pack\n") . PktLine::flush();
    echo file_get_contents(getenv('PITMASTER_TEST_INCOMPLETE_REFS'));
} elseif (str_contains($_SERVER['REQUEST_URI'], '/git-upload-pack')) {
    header('Content-Type: application/x-git-upload-pack-result');
    echo PktLine::encode("NAK\n");
    echo PktLine::encode("\x01" . file_get_contents(getenv('PITMASTER_TEST_INCOMPLETE_PACK'))) . PktLine::flush();
} else {
    echo 'ready';
}
