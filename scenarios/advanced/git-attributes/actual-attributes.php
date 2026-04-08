<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Config\GitAttributes;

$attributes = GitAttributes::forRepo(getcwd());
$checks = [
    ['readme.txt', ['text', 'eol']],
    ['guide.md', ['diff']],
    ['docs/file.bin', ['custom']],
    ['archive.dat', ['diff']],
];

foreach ($checks as [$path, $names]) {
    $values = $attributes->getAttributes($path);

    foreach ($names as $name) {
        $value = $values[$name] ?? 'unspecified';

        if ($value === true) {
            $value = 'set';
        } elseif ($value === false) {
            $value = 'unset';
        }

        echo "{$path}: {$name}: {$value}\n";
    }
}
