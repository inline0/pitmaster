<?php

declare(strict_types=1);

require getenv('PITMASTER_ROOT') . '/vendor/autoload.php';

use Pitmaster\Pitmaster;

$repoDir = getcwd() . '/repo';
$fixture = getenv('PITMASTER_ROOT') . '/fixtures/upstream/jgit/org.eclipse.jgit.test/tst-rsrc/org/eclipse/jgit/test/resources/gitgit.index.v3';
mkdir($repoDir, 0777, true);
git_in(getcwd(), 'init --initial-branch=main ' . escapeshellarg($repoDir));
copy($fixture, $repoDir . '/.git/index');

$repo = Pitmaster::open($repoDir);
$entries = [];

foreach ($repo->index()->allEntries() as $entry) {
    $entries[] = [
        'path' => $entry->path,
        'stage' => $entry->stage(),
        'line' => sprintf('%06o %s %d	%s', $entry->mode, $entry->hash->hex, $entry->stage(), $entry->path),
    ];
}

usort($entries, static function (array $a, array $b): int {
    $pathCmp = strcmp($a['path'], $b['path']);

    if ($pathCmp !== 0) {
        return $pathCmp;
    }

    return $a['stage'] <=> $b['stage'];
});

file_put_contents(
    getcwd() . '/.index-state',
    implode("\n", array_map(static fn (array $entry): string => $entry['line'], $entries)) . "\n",
);

function git_in(string $dir, string $command): void
{
    exec(sprintf('cd %s && git %s 2>&1', escapeshellarg($dir), $command), $output, $exitCode);

    if ($exitCode !== 0) {
        throw new RuntimeException(implode("\n", $output));
    }
}
