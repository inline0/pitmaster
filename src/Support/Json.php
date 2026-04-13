<?php

declare(strict_types=1);

namespace Pitmaster\Support;

use RuntimeException;

final class Json
{
    public static function decodeFile(string $path): array
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Unable to read JSON file: {$path}");
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new RuntimeException("Invalid JSON in file: {$path}");
        }

        return $decoded;
    }

    public static function encodeFile(string $path, array $payload): void
    {
        $directory = dirname($path);

        if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create directory: {$directory}");
        }

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            throw new RuntimeException("Unable to encode JSON for file: {$path}");
        }

        if (file_put_contents($path, $encoded . "\n") === false) {
            throw new RuntimeException("Unable to write JSON file: {$path}");
        }
    }
}
