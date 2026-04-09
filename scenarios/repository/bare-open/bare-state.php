<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Object\Tree;
use Pitmaster\Pitmaster;

$repo = Pitmaster::open(getcwd());
$headTree = $repo->readObject($repo->head()->tree->hex);

if (!$headTree instanceof Tree) {
    throw new RuntimeException('HEAD tree did not resolve to a tree object');
}

$readme = $headTree->entry('README.md');

if ($readme === null) {
    throw new RuntimeException('README.md missing from HEAD tree');
}

$lines = [
    'is_bare=' . ($repo->isBare() ? 'true' : 'false'),
    'branch=' . ($repo->branch() ?? ''),
    'head=' . $repo->head()->id->hex,
    'branches=' . implode('|', $repo->branches()),
    'log=' . implode('|', $repo->logOneline(20)),
    'readme=' . rtrim($repo->catFile($readme->hash->hex), "\n"),
];

echo implode("\n", $lines) . "\n";
