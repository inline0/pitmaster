<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;
use Pitmaster\Stash\Stash;

$repo = Pitmaster::open(getcwd());
$stash = new Stash(
    $repo->objectDatabase(),
    $repo->refDatabase(),
    $repo->gitDir(),
    $repo->workDir(),
);
putenv('GIT_AUTHOR_DATE=@1700000000 +0000');
putenv('GIT_COMMITTER_DATE=@1700000000 +0000');
$stash->push('test stash');
putenv('GIT_AUTHOR_DATE');
putenv('GIT_COMMITTER_DATE');

git('stash list > .stash-list.txt');
git("show -s --format='%s|%P' refs/stash > .stash-commit.txt");
git('show refs/stash:a.txt > .stash-file.txt');
file_put_contents(getcwd() . '/.worktree.txt', (string) file_get_contents(getcwd() . '/a.txt'));

function git(string $command): void
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg(getcwd()), $command), $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed:\n" . implode("\n", $output));
    }
}
