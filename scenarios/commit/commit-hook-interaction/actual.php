<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Hooks\HookRunner;
use Pitmaster\Pitmaster;

foreach ([
    'GIT_AUTHOR_DATE' => '@1712563200 +0200',
    'GIT_COMMITTER_DATE' => '@1712566800 +0200',
] as $name => $value) {
    putenv("{$name}={$value}");
}

$runner = new HookRunner(getcwd() . '/.git');
$runner->install('pre-commit', "#!/bin/sh\necho pre-commit >> .hook-log\n");
$runner->install(
    'prepare-commit-msg',
    "#!/bin/sh\n" .
    "echo \"prepare-commit-msg:\${2:-}\" >> .hook-log\n" .
    "printf '\\nPrepared-by: hook\\n' >> \"$1\"\n",
);
$runner->install(
    'commit-msg',
    "#!/bin/sh\n" .
    "echo commit-msg >> .hook-log\n" .
    "grep -q '^Prepared-by: hook$' \"$1\"\n",
);
$runner->install('post-commit', "#!/bin/sh\necho post-commit >> .hook-log\n");

$repo = Pitmaster::open(getcwd());
$repo->commit("Hook subject\n");

$state = "[log]\n"
    . file_get_contents(getcwd() . '/.hook-log')
    . "[message]\n"
    . git('log -1 --format=%B');
file_put_contents(getcwd() . '/.hook-state', $state);

function git(string $command): string
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg(getcwd()), $command), $output, $exitCode);
    $result = implode("\n", $output);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed:\n{$result}");
    }

    return $result . ($result === '' ? '' : "\n");
}
