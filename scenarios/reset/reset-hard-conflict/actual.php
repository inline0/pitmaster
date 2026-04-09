<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Exceptions\MergeConflictException;
use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());
$pickId = trim((string) file_get_contents(getcwd() . '/.pick-id'));

try {
    $repo->cherryPick($pickId);
    throw new RuntimeException('Expected cherry-pick conflict');
} catch (MergeConflictException) {
}

$repo->reset('HEAD', 'hard');
