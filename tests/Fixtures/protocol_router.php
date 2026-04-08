<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Pitmaster\Protocol\PktLine;

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/health') {
    http_response_code(204);
    return;
}

$head = str_repeat('a', 40);
$advertisement = PktLine::encode("# service=git-upload-pack\n")
    . PktLine::flush()
    . PktLine::encode($head . " HEAD\0symref=HEAD:refs/heads/main multi_ack_detailed\n")
    . PktLine::encode($head . " refs/heads/main\n")
    . PktLine::flush();

switch ($path) {
    case '/discover-ok/info/refs':
        header('Content-Type: application/x-git-upload-pack-advertisement');
        echo $advertisement;
        return;

    case '/discover-wrong-type/info/refs':
        header('Content-Type: text/plain');
        echo $advertisement;
        return;

    case '/discover-404/info/refs':
        http_response_code(404);
        header('Content-Type: text/plain');
        echo "not found\n";
        return;

    case '/upload-error/git-upload-pack':
        header('Content-Type: application/x-git-upload-pack-result');
        echo PktLine::encode("\x03fatal: upload-pack rejected\n") . PktLine::flush();
        return;

    case '/receive-ng/git-receive-pack':
        header('Content-Type: application/x-git-receive-pack-result');
        echo PktLine::encode("unpack ok\n")
            . PktLine::encode("ng refs/heads/main non-fast-forward\n")
            . PktLine::flush();
        return;

    case '/receive-ok/git-receive-pack':
        header('Content-Type: application/x-git-receive-pack-result');
        echo PktLine::encode("unpack ok\n")
            . PktLine::encode("ok refs/heads/main\n")
            . PktLine::flush();
        return;
}

http_response_code(404);
header('Content-Type: text/plain');
echo "unknown route\n";
