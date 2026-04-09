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
    . PktLine::encode($head . " HEAD\0symref=HEAD:refs/heads/main multi_ack_detailed side-band-64k\n")
    . PktLine::encode($head . " refs/heads/main\n")
    . PktLine::flush();

switch ($path) {
    case '/discover-invalid-hex/info/refs':
        header('Content-Type: application/x-git-upload-pack-advertisement');
        echo "zzzz";
        return;

    case '/discover-truncated/info/refs':
        header('Content-Type: application/x-git-upload-pack-advertisement');
        echo PktLine::encode("# service=git-upload-pack\n")
            . PktLine::flush()
            . "003f"
            . substr($head . " HEAD\0symref=HEAD:refs/heads/main multi_ack_detailed\n", 0, 20);
        return;

    case '/upload-truncated/git-upload-pack':
        header('Content-Type: application/x-git-upload-pack-result');
        echo "0008NAK\n000d\x01PACK12";
        return;

    case '/upload-no-pack/git-upload-pack':
        header('Content-Type: application/x-git-upload-pack-result');
        echo PktLine::encode("\x02counting objects: 1\n") . PktLine::flush();
        return;

    case '/receive-missing-status/git-receive-pack':
        header('Content-Type: application/x-git-receive-pack-result');
        echo PktLine::encode("unpack ok\n") . PktLine::flush();
        return;

    case '/receive-truncated/git-receive-pack':
        header('Content-Type: application/x-git-receive-pack-result');
        echo "000f\x01unpack ok\n000a";
        return;
}

http_response_code(404);
header('Content-Type: text/plain');
echo "unknown route\n";
