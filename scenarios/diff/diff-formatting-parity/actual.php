<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Diff\ColorDiff;
use Pitmaster\Diff\WordDiff;
use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());
$parentContent = (string) shell_exec('git show HEAD~1:article.txt');
$headContent = (string) shell_exec('git show HEAD:article.txt');
$result = $repo->show('HEAD');
$plain = implode('', array_map(static fn ($entry) => $entry->format(), $result['diff']));

$payload = [
    'word' => WordDiff::diff($parentContent, $headContent),
    'plain' => $plain,
    'color' => base64_encode(ColorDiff::colorize($plain)),
];

file_put_contents(getcwd() . '/.diff-format.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
