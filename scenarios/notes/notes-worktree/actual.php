<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;
use Pitmaster\Ref\Notes;

$repo = Pitmaster::open(getcwd());
$linked = '../linked-worktree-' . getmypid();
git('worktree add -b linked ' . escapeshellarg($linked) . ' >/dev/null');
$notes = new Notes($repo->objectDatabase(), $repo->refDatabase());
$notes->set($repo->head()->id, 'Visible everywhere');

$target = trim(shell('git rev-parse main'));
git('-C ' . escapeshellarg($linked) . ' notes show ' . escapeshellarg($target) . ' > .linked-note.txt');

function git(string $command): void
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg(getcwd()), $command), $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed:\n" . implode("\n", $output));
    }
}

function shell(string $command): string
{
    exec(sprintf('cd %s && %s 2>&1', escapeshellarg(getcwd()), $command), $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException("shell {$command} failed:\n" . implode("\n", $output));
    }

    return implode("\n", $output);
}
