<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;
use Pitmaster\Ref\Notes;

$repo = Pitmaster::open(getcwd());
$log = $repo->log(10);
$latest = $log[0]->id;
$initial = $log[1]->id;
$notes = new Notes($repo->objectDatabase(), $repo->refDatabase());
$notes->set($initial, 'Main note');
$notes->set($latest, 'Review note', 'refs/notes/review');
$notes->merge('refs/notes/review');

git('notes list > .notes-list.txt');
git('notes show ' . $latest->hex . ' > .latest-note.txt');

function git(string $command): void
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg(getcwd()), $command), $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed:\n" . implode("\n", $output));
    }
}
