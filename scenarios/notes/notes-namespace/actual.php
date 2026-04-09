<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;
use Pitmaster\Ref\Notes;

$repo = Pitmaster::open(getcwd());
$notes = new Notes($repo->objectDatabase(), $repo->refDatabase());
putenv('GIT_AUTHOR_NAME=Test User');
putenv('GIT_AUTHOR_EMAIL=test@pitmaster.dev');
putenv('GIT_AUTHOR_DATE=@1700000100 +0000');
putenv('GIT_COMMITTER_NAME=Test User');
putenv('GIT_COMMITTER_EMAIL=test@pitmaster.dev');
putenv('GIT_COMMITTER_DATE=@1700000100 +0000');
$notes->set($repo->head()->id, 'Pitmaster review', 'refs/notes/review');
putenv('GIT_AUTHOR_NAME');
putenv('GIT_AUTHOR_EMAIL');
putenv('GIT_AUTHOR_DATE');
putenv('GIT_COMMITTER_NAME');
putenv('GIT_COMMITTER_EMAIL');
putenv('GIT_COMMITTER_DATE');

git('notes --ref=review show HEAD > .note.txt');
git('rev-parse refs/notes/review > .note-ref.txt');
git('notes --ref=review list > .note-list.txt');

function git(string $command): void
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg(getcwd()), $command), $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed:\n" . implode("\n", $output));
    }
}
