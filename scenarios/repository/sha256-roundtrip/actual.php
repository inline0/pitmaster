<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

putenv('PITMASTER_AUTHOR_NAME=Test User');
putenv('PITMASTER_AUTHOR_EMAIL=test@pitmaster.dev');
putenv('PITMASTER_AUTHOR_DATE=2024-01-15T00:00:10+0000');
putenv('PITMASTER_COMMITTER_NAME=Test User');
putenv('PITMASTER_COMMITTER_EMAIL=test@pitmaster.dev');
putenv('PITMASTER_COMMITTER_DATE=2024-01-15T00:00:10+0000');

$repo = Pitmaster::init(getcwd(), 'sha256');
file_put_contents(getcwd() . '/tracked.txt', "tracked\n");
$repo->add('tracked.txt');
$repo->commit('Initial commit');
$repo->createTag('v1', "Release 1\n");
