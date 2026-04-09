<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

foreach ([
    'GIT_AUTHOR_NAME' => 'Alice Author',
    'GIT_AUTHOR_EMAIL' => 'alice@example.com',
    'GIT_AUTHOR_DATE' => '@1712563200 +0200',
    'GIT_COMMITTER_NAME' => 'Chris Committer',
    'GIT_COMMITTER_EMAIL' => 'chris@example.com',
    'GIT_COMMITTER_DATE' => '@1712566800 +0200',
] as $name => $value) {
    putenv("{$name}={$value}");
}

$message = "Subject line\n\nBody paragraph\nSigned-off-by: Trailer <trailer@example.com>\n";
$repo = Pitmaster::open(getcwd());
$repo->commit($message);
file_put_contents(getcwd() . '/.commit-state', git('cat-file -p HEAD'));

function git(string $command): string
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg(getcwd()), $command), $output, $exitCode);
    $result = implode("\n", $output);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed:\n{$result}");
    }

    return $result . ($result === '' ? '' : "\n");
}
