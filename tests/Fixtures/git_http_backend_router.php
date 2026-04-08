<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if ($path === '/health') {
    http_response_code(204);
    return;
}

$projectRoot = getenv('PITMASTER_GIT_HTTP_PROJECT_ROOT');
$backend = getenv('PITMASTER_GIT_HTTP_BACKEND');

if ($projectRoot === false || $projectRoot === '' || $backend === false || $backend === '') {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "git-http-backend router is not configured\n";
    return;
}

$requestBody = file_get_contents('php://input');

if ($requestBody === false) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "failed to read request body\n";
    return;
}

$env = [
    'GATEWAY_INTERFACE' => 'CGI/1.1',
    'GIT_PROJECT_ROOT' => $projectRoot,
    'GIT_HTTP_EXPORT_ALL' => '1',
    'PATH_INFO' => $path,
    'PATH_TRANSLATED' => rtrim($projectRoot, '/') . $path,
    'QUERY_STRING' => $_SERVER['QUERY_STRING'] ?? '',
    'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? $path,
    'SCRIPT_FILENAME' => $backend,
    'SCRIPT_NAME' => '',
    'SERVER_NAME' => $_SERVER['SERVER_NAME'] ?? '127.0.0.1',
    'SERVER_PORT' => $_SERVER['SERVER_PORT'] ?? '80',
    'SERVER_PROTOCOL' => $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1',
    'SERVER_SOFTWARE' => 'Pitmaster test router',
];

if (isset($_SERVER['CONTENT_TYPE'])) {
    $env['CONTENT_TYPE'] = (string) $_SERVER['CONTENT_TYPE'];
}

if (isset($_SERVER['CONTENT_LENGTH'])) {
    $env['CONTENT_LENGTH'] = (string) $_SERVER['CONTENT_LENGTH'];
}

$process = proc_open(
    $backend,
    [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ],
    $pipes,
    null,
    $env,
);

if (!is_resource($process)) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "failed to start git-http-backend\n";
    return;
}

fwrite($pipes[0], $requestBody);
fclose($pipes[0]);

$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);

if ($stdout === false) {
    http_response_code(500);
    header('Content-Type: text/plain');
    echo "git-http-backend produced no output\n";
    return;
}

[$rawHeaders, $body] = array_pad(preg_split("/\r?\n\r?\n/", $stdout, 2), 2, '');

foreach (preg_split("/\r?\n/", (string) $rawHeaders) as $headerLine) {
    $headerLine = trim($headerLine);

    if ($headerLine === '') {
        continue;
    }

    if (preg_match('/^Status:\s+(\d{3})\b/', $headerLine, $matches) === 1) {
        http_response_code((int) $matches[1]);
        continue;
    }

    header($headerLine, false);
}

if ($exitCode !== 0 && $stderr !== false && trim($stderr) !== '') {
    header('X-Git-Http-Backend-Stderr: ' . substr(trim($stderr), 0, 200), false);
}

echo $body;
