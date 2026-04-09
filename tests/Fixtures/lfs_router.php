<?php

declare(strict_types=1);

$storageDir = getenv('LFS_STORAGE_DIR');

if ($storageDir === false || $storageDir === '') {
    http_response_code(500);
    echo "LFS_STORAGE_DIR not configured\n";
    return;
}

if (!is_dir($storageDir)) {
    mkdir($storageDir, 0777, true);
}

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';

if ($uri === '/__health') {
    header('Content-Type: text/plain');
    echo "ok\n";
    return;
}

if (preg_match('#^/.+/info/lfs/objects/batch$#', $uri) === 1) {
    $payload = file_get_contents('php://input');
    $request = json_decode($payload ?: '[]', true);

    if (!is_array($request) || !isset($request['operation'], $request['objects']) || !is_array($request['objects'])) {
        http_response_code(400);
        header('Content-Type: application/vnd.git-lfs+json');
        echo json_encode(['message' => 'invalid batch request']);
        return;
    }

    $operation = (string) $request['operation'];
    $responseObjects = [];

    foreach ($request['objects'] as $object) {
        $oid = (string) ($object['oid'] ?? '');
        $size = (int) ($object['size'] ?? 0);
        $path = $storageDir . '/' . $oid;
        $entry = [
            'oid' => $oid,
            'size' => $size,
        ];

        if ($operation === 'upload') {
            if (!is_file($path)) {
                $entry['actions']['upload'] = [
                    'href' => "http://{$host}/objects/{$oid}",
                ];
            }

            $entry['actions']['download'] = [
                'href' => "http://{$host}/objects/{$oid}",
            ];
        } elseif ($operation === 'download') {
            if (!is_file($path)) {
                $entry['error'] = [
                    'code' => 404,
                    'message' => "object {$oid} not found",
                ];
            } else {
                $entry['actions']['download'] = [
                    'href' => "http://{$host}/objects/{$oid}",
                ];
            }
        } else {
            $entry['error'] = [
                'code' => 400,
                'message' => "unsupported operation {$operation}",
            ];
        }

        $responseObjects[] = $entry;
    }

    header('Content-Type: application/vnd.git-lfs+json');
    echo json_encode(['transfer' => 'basic', 'objects' => $responseObjects], JSON_UNESCAPED_SLASHES);
    return;
}

if (preg_match('#^/objects/([0-9a-f]{64})$#', $uri, $matches) === 1) {
    $oid = $matches[1];
    $path = $storageDir . '/' . $oid;

    if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $content = file_get_contents('php://input');
        file_put_contents($path, $content !== false ? $content : '');
        http_response_code(200);
        return;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!is_file($path)) {
            http_response_code(404);
            return;
        }

        header('Content-Type: application/octet-stream');
        readfile($path);
        return;
    }
}

http_response_code(404);
header('Content-Type: text/plain');
echo "not found\n";
