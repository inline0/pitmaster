<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

foreach ([
    'GIT_COMMITTER_NAME' => 'Tagger Test',
    'GIT_COMMITTER_EMAIL' => 'tagger@example.com',
    'GIT_COMMITTER_DATE' => '@1712570400 +0200',
] as $name => $value) {
    putenv("{$name}={$value}");
}

$repo = Pitmaster::open(getcwd());
$repo->createTag('v1.0', "Release 1.0\n");
file_put_contents(getcwd() . '/.tag-state', git('cat-file -p refs/tags/v1.0'));

function git(string $command): string
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg(getcwd()), $command), $output, $exitCode);
    $result = implode("\n", $output);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed:\n{$result}");
    }

    return $result . ($result === '' ? '' : "\n");
}
