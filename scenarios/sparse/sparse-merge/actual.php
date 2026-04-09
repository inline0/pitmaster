<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Checkout\SparseCheckout;
use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());
$sparse = new SparseCheckout(getcwd() . '/.git');
$sparse->init();
$sparse->set(['src']);
$repo->reset('HEAD', 'hard');
$repo->merge('feature');

run('git status --porcelain=v2 > .status.txt');
run("find . -type f ! -path './.git/*' ! -name '.status.txt' ! -name '.worktree-files.txt' | sed 's#^\\./##' | sort > .worktree-files.txt");
run('git show HEAD:docs/guide.txt > .head-docs.txt');

function run(string $command): void
{
    exec(sprintf('cd %s && %s 2>&1', escapeshellarg(getcwd()), $command), $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException("command failed: {$command}\n" . implode("\n", $output));
    }
}
