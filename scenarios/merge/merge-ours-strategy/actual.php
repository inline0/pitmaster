<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

putenv('GIT_AUTHOR_DATE=2024-01-15T00:00:10+0000');
putenv('GIT_COMMITTER_DATE=2024-01-15T00:00:10+0000');

$repo = Pitmaster::open(getcwd());
$repo->merge('feature', 'ours');
