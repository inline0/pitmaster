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
$repo->createTag('v1.0', "Unsigned release\n");
$result = git_allow_failure('verify-tag v1.0');
file_put_contents(getcwd() . '/.verify-state', 'verify.failed=' . ($result['exitCode'] !== 0 ? 'yes' : 'no') . "\n");

/**
 * @return array{exitCode: int, output: string}
 */
function git_allow_failure(string $command): array
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg(getcwd()), $command), $output, $exitCode);

    return ['exitCode' => $exitCode, 'output' => implode("\n", $output)];
}
