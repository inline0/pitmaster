<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Pitmaster\Object\ObjectId;
use Pitmaster\Protocol\ShallowClone;

$head = trim((string) shell_exec('git rev-parse HEAD'));
ShallowClone::writeShallow(getcwd() . '/.git', [ObjectId::fromHex($head)]);

echo trim((string) shell_exec('git rev-parse --is-shallow-repository')) . "\n";
echo trim((string) file_get_contents(getcwd() . '/.git/shallow')) . "\n";
