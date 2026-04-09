<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());
$detachId = trim((string) file_get_contents(getcwd() . '/.detach-id'));
$repo->checkout($detachId);
file_put_contents(getcwd() . '/detached.txt', "detached\n");
$repo->add('detached.txt');
putenv('GIT_AUTHOR_DATE=2024-01-15T00:00:10+0000');
putenv('GIT_COMMITTER_DATE=2024-01-15T00:00:10+0000');
$repo->commit("detached\n");
