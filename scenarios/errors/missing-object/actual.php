<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Exceptions\ObjectNotFoundException;
use Pitmaster\Pitmaster;

$head = trim(git('rev-parse HEAD'));
$path = getcwd() . '/.git/objects/' . substr($head, 0, 2) . '/' . substr($head, 2);
unlink($path);

$repo = Pitmaster::open(getcwd());

try {
    $repo->readObject($head);
    $state = "missing-object=no\n";
} catch (ObjectNotFoundException) {
    $state = "missing-object=yes\n";
}

file_put_contents(getcwd() . '/.error-state', $state);

function git(string $command): string
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg(getcwd()), $command), $output, $exitCode);
    $result = implode("\n", $output);

    if ($exitCode !== 0) {
        throw new RuntimeException("git {$command} failed:\n{$result}");
    }

    return $result;
}
