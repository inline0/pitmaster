<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

try {
    Pitmaster\Pitmaster::open(getcwd())->deleteBranch('main');
    file_put_contents('.branch-protection', "deleted\n");
} catch (RuntimeException) {
    file_put_contents('.branch-protection', "protected\n");
}
