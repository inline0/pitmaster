<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

putenv('GIT_AUTHOR_DATE=2024-01-14T00:00:10+0000');
putenv('GIT_COMMITTER_DATE=2024-01-14T00:00:10+0000');

$repo = Pitmaster::open(getcwd());
$repo->rebase('main');
file_put_contents(getcwd() . '/a.txt', "line 1\nresolved\nline 3\n");
$repo->add('a.txt');
$repo->rebaseContinue();
