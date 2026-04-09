<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());
$repo->checkout('left');
putenv('GIT_AUTHOR_DATE=2024-01-08T00:00:07+0000');
putenv('GIT_COMMITTER_DATE=2024-01-08T00:00:07+0000');
$repo->merge('right', 'ort');
